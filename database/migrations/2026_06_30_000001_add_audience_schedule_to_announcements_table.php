<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds audience targeting and scheduling to broadcast announcements.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('audience')->default('patients')->after('channel'); // see Announcement::AUDIENCES
            $table->timestamp('send_at')->nullable()->after('recipients_count'); // null = sent immediately
            $table->string('status')->default('sent')->after('send_at');         // sent | scheduled | cancelled
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['audience', 'send_at', 'status']);
        });
    }
};
