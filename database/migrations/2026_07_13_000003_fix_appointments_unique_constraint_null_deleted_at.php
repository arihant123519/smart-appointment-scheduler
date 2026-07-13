<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixes two pre-existing correctness gaps in the double-booking guarantee
 * (both predate the overbooking feature — discovered while stress-testing
 * it):
 *
 * 1. Standard SQL treats NULL as never equal to NULL, so a UNIQUE INDEX that
 *    includes the nullable `deleted_at` column never actually rejected two
 *    active (non-deleted) rows sharing the same provider/start/overbook_slot
 *    — only the application-level `lockForUpdate()` check in
 *    SchedulingService was preventing it.
 *
 * 2. Cancelling an appointment does NOT soft-delete it anywhere in this app
 *    (BookingController::cancel / AppointmentController::updateStatus /
 *    ReminderActionController::cancel all just set status='cancelled',
 *    matching Appointment::scopeActive()'s "status <> cancelled" definition
 *    of "occupies this slot") — so a naive NULL-based fix would make a
 *    cancelled-but-not-deleted row keep blocking that slot from ever being
 *    rebooked, a regression this migration must not introduce.
 *
 * The fix: a generated `deleted_at_key` column that collapses every row
 * scopeActive() would call active (deleted_at IS NULL AND status <>
 * 'cancelled') to the literal 'active' — genuinely rejecting duplicates
 * among rows that actually occupy the slot, exactly matching the
 * application's own definition of "active" — while every other row
 * (soft-deleted, or cancelled-but-not-deleted) gets a value disambiguated by
 * deleted_at + status + created_at, so booking/cancel/rebook history keeps
 * working. MySQL generated columns can't reference the AUTO_INCREMENT `id`,
 * so `created_at` is the next-best always-available-at-insert disambiguator.
 *
 * Known residual limitation (not a double-booking risk): both `deleted_at`
 * and `created_at` are second-precision, so two cancellations of the exact
 * same slot within the exact same wall-clock second could theoretically
 * collide and raise a DB error on the second cancel. This requires
 * scripted/automated rapid-fire action to trigger — no realistic
 * human-paced usage hits it — and it fails safe (a retryable error), never
 * silently. Full elimination would need microsecond-precision timestamp
 * columns app-wide, a much larger change than this fix warrants.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        $expr = $driver === 'sqlite'
            ? "CASE WHEN deleted_at IS NULL AND status <> 'cancelled' THEN 'active' ELSE IFNULL(deleted_at, 'x') || '-' || status || '-' || created_at END"
            : "IF(deleted_at IS NULL AND status <> 'cancelled', 'active', CONCAT(IFNULL(deleted_at, 'x'), '-', status, '-', created_at))";

        DB::statement("ALTER TABLE appointments ADD COLUMN deleted_at_key VARCHAR(80) GENERATED ALWAYS AS ($expr) STORED");

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_provider_slot_overbook');
            $table->unique(['provider_id', 'start_at', 'overbook_slot', 'deleted_at_key'], 'uniq_provider_slot_overbook');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_provider_slot_overbook');
            $table->unique(['provider_id', 'start_at', 'overbook_slot', 'deleted_at'], 'uniq_provider_slot_overbook');
        });

        DB::statement('ALTER TABLE appointments DROP COLUMN deleted_at_key');
    }
};
