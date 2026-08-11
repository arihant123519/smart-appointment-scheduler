<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One encounter record per appointment: the doctor's consultation notes.
 * A prescription (see create_prescriptions_table) is written against a
 * finalized consultation, not the appointment directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->json('vitals')->nullable(); // free-form: bp, pulse, temp, weight, spo2, ...
            $table->text('examination_notes')->nullable();
            $table->text('diagnosis')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_instructions')->nullable();
            $table->string('status')->default('draft'); // draft | finalized
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id']);
            $table->index(['patient_id']);
            $table->index(['clinic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
