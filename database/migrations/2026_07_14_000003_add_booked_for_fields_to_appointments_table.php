<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a patient book on behalf of a family member without needing a
 * separate login-capable account for them: the appointment stays under the
 * authenticated account (who receives reminders/confirmations), tagged with
 * who it's actually for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booked_for_name')->nullable()->after('notes');
            $table->string('booked_for_relationship')->nullable()->after('booked_for_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['booked_for_name', 'booked_for_relationship']);
        });
    }
};
