<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Walk-in queue (PRD "managing walk-in queues") — deliberately separate from
 * the scheduled-appointment calendar. A scheduled appointment always takes
 * priority; walk-ins just fill genuine gaps, tracked here independently so
 * patients get a live position instead of standing around wondering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('walk_in_queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name'); // walk-ins don't need an account — a name is enough
            $table->string('phone')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('status')->default('waiting'); // waiting | serving | done | left
            $table->string('token', 40)->unique();
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_in_queues');
    }
};
