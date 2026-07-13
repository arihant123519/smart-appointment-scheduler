<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-service deposit policy: whether a deposit is required at booking time,
 * how much, and whether a late cancellation forfeits it. All nullable/opt-in
 * — a service with deposit_required=false books exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('deposit_required')->default(false)->after('recall_cadence_days');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('deposit_required');
            // Cancelling within this many hours of the visit forfeits the deposit.
            // Null = always refunded on cancellation, regardless of timing.
            $table->unsignedSmallInteger('deposit_forfeit_hours')->nullable()->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['deposit_required', 'deposit_amount', 'deposit_forfeit_hours']);
        });
    }
};
