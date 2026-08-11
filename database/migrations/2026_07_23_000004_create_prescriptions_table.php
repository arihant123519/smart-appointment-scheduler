<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One prescription per consultation. patient_id/provider_id/clinic_id are
 * denormalized from the consultation so patient-history and provider queries
 * don't need a join through consultations for basic scoping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->unique()->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->text('notes')->nullable(); // general advice, separate from line items
            $table->string('pdf_path')->nullable(); // cached rendered PDF; regenerated when edited
            $table->timestamps();
            $table->softDeletes();

            $table->index(['patient_id']);
            $table->index(['provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
