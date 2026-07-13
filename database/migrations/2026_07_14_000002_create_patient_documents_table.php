<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referral letters, consent forms, and plain-language visit recaps — all
 * follow the same lifecycle per the PRD: AI-drafted, staff-reviewed and
 * edited, then explicitly approved before anything is sent to the patient.
 * One table serves all three document types since the lifecycle is identical.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('type'); // referral_letter | consent_form | visit_recap
            $table->text('content');
            $table->string('status')->default('draft'); // draft | approved | sent
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_documents');
    }
};
