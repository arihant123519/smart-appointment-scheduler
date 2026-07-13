<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks a referral from the moment it's made (a patient sharing their
 * booking link, or a provider referring someone out) until it's either
 * redeemed by a completed booking or expires. Supports both directions the
 * PRD describes: patient-to-patient referral links, and provider referrals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('referrer_patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referrer_provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('referred_name')->nullable();
            $table->string('referred_phone')->nullable();
            $table->string('referred_email')->nullable();
            $table->string('token', 40)->unique();
            $table->string('status')->default('pending'); // pending | booked | expired
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
