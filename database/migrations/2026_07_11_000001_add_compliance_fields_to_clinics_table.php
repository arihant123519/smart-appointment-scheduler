<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Technical scaffolding only — this does not replace an actual signed HIPAA
 * (US) / DPDP (India) agreement. compliance_agreements_signed_at is a hard
 * gate ClinicController enforces before a clinic can be activated; the
 * agreement itself is a legal/business process outside this system.
 * abdm_health_id records a clinic's own ABDM registration once THEY'VE
 * completed it with the government framework — this app doesn't register
 * clinics on their behalf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->timestamp('compliance_agreements_signed_at')->nullable()->after('is_active');
            $table->string('abdm_health_id')->nullable()->after('compliance_agreements_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['compliance_agreements_signed_at', 'abdm_health_id']);
        });
    }
};
