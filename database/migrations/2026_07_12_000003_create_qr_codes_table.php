<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-poster/per-paperwork QR codes that deep-link into booking (PRD "book
 * via a QR code"). Each code can point at a specific visit type and tracks
 * scans vs. actual completed bookings, so a clinic can tell waiting-room
 * posters apart from discharge paperwork by which one is actually working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('label'); // e.g. "Waiting-room poster", "Discharge paperwork"
            $table->string('token', 40)->unique();
            $table->unsignedInteger('scans_count')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Attribution: which specific QR code (if any) a booking came from.
        // Separate from `channel` (web/api/sms/...) — channel says HOW they
        // booked, source says WHICH physical code/poster drove it.
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('source')->nullable()->after('channel');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('source');
        });
        Schema::dropIfExists('qr_codes');
    }
};
