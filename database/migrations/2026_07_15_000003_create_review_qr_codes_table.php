<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-clinic QR codes / links for anonymous, no-login patient feedback —
 * kept separate from `qr_codes` (booking attribution) since the two track
 * different things (submissions vs. bookings) and aren't service-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('label');
            $table->string('token', 40)->unique();
            $table->unsignedInteger('scans_count')->default(0);
            $table->unsignedInteger('submissions_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_qr_codes');
    }
};
