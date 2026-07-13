<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * White-label branding: a clinic's own logo + accent color, shown in place of
 * the generic app branding wherever a signed-in user's clinic is known.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('settings');
            $table->string('primary_color', 7)->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'primary_color']);
        });
    }
};
