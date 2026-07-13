<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-service overbooking policy — opt-in, and only ever applied by
 * SchedulingService when a specific day/hour slot has a demonstrated high
 * no-show rate (see SlotScoringService::noShowRateFor()). Off by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('overbooking_enabled')->default(false)->after('deposit_forfeit_hours');
            // Extra bookings allowed beyond the normal 1-per-slot capacity,
            // e.g. margin=1 means a high-no-show slot can hold 2 bookings total.
            $table->unsignedTinyInteger('overbooking_margin')->default(0)->after('overbooking_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['overbooking_enabled', 'overbooking_margin']);
        });
    }
};
