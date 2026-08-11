<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic-wide prescription letterhead. Name, address, phone, and logo_path
 * already exist on this table and are reused as-is; these two fields are the
 * only pieces specific to the prescription template (an optional tagline
 * under the header, and footer disclaimer/contact text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->text('prescription_header_note')->nullable()->after('primary_color');
            $table->text('prescription_footer_text')->nullable()->after('prescription_header_note');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['prescription_header_note', 'prescription_footer_text']);
        });
    }
};
