<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A prescription must show who actually treated the patient, so the doctor's
 * registration number and signature live on the provider record itself (not
 * the clinic-wide letterhead settings) and are pulled in per prescription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('registration_no')->nullable()->after('credentials');
            $table->string('signature_path')->nullable()->after('registration_no');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn(['registration_no', 'signature_path']);
        });
    }
};
