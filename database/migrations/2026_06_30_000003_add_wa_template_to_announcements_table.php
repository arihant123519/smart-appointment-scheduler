<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each broadcast carries its own WhatsApp (Gupshup) template, entered alongside
 * the announcement rather than configured globally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('wa_template_id')->nullable()->after('channel');
            $table->string('wa_namespace')->nullable()->after('wa_template_id');
            $table->json('wa_variables')->nullable()->after('wa_namespace');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['wa_template_id', 'wa_namespace', 'wa_variables']);
        });
    }
};
