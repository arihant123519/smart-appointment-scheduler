<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controlled overbooking (PRD "controlled overbooking for high no-show
 * slots"): for a small, explicitly configured set of high-no-show visit
 * types, allow a strictly limited number of EXTRA bookings at the exact same
 * provider/start_at — never silent, never broad.
 *
 * This does NOT weaken the double-booking guarantee for the default case.
 * The normal path (`overbook_slot = 0`) is still protected by a hard unique
 * constraint exactly as before — the guarantee that "the first booking for a
 * given provider+time always wins, uniquely" is untouched. What changes is
 * that a SECOND (overbook_slot = 1), THIRD (= 2), etc. booking at that same
 * provider+time can now exist *only* when Service::overbooking_enabled is
 * explicitly on for that visit type AND SchedulingService's capacity check
 * allows it — every such slot still gets its own uniquely-guarded row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedTinyInteger('overbook_slot')->default(0)->after('resource_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_provider_slot');
            $table->unique(['provider_id', 'start_at', 'overbook_slot', 'deleted_at'], 'uniq_provider_slot_overbook');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('uniq_provider_slot_overbook');
            $table->unique(['provider_id', 'start_at', 'deleted_at'], 'uniq_provider_slot');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('overbook_slot');
        });
    }
};
