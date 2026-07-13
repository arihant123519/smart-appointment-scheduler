<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integration credentials (Email/SMTP, WhatsApp, SMS, Payments) were global —
 * one clinic admin's saved credentials silently applied to every clinic. This
 * makes `settings` clinic-scoped: each clinic gets its own row per key, edited
 * from that clinic's own Settings → Integrations page. `clinic_id` stays
 * nullable for settings that remain intentionally global (e.g. appointment
 * notification lead-times), which keep using the unscoped Setting::get/set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->unique(['clinic_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['clinic_id', 'key']);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->unique('key');
        });
    }
};
