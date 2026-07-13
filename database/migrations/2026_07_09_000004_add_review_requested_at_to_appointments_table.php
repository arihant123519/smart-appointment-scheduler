<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks when the automatic post-visit review request was sent, so the
 * reviews:request-dispatch command only asks each completed visit once,
 * and only after it's genuinely completed (never cancelled/no-show).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('review_requested_at')->nullable()->after('missed_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('review_requested_at');
        });
    }
};
