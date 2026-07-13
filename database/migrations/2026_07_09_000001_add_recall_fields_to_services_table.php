<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-service retention cadence, set by a clinic admin:
 *   - recall_window_days: days after a completed visit to nudge for a
 *     one-off follow-up (PRD "recall campaigns for overdue follow-ups").
 *   - recall_cadence_days: for services with an ongoing treatment plan,
 *     the expected gap between visits (PRD "care-gap outreach").
 * Both are nullable — retention outreach is opt-in per service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedSmallInteger('recall_window_days')->nullable()->after('is_active');
            $table->unsignedSmallInteger('recall_cadence_days')->nullable()->after('recall_window_days');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['recall_window_days', 'recall_cadence_days']);
        });
    }
};
