<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            // booked | confirmed | checked_in | completed | cancelled | no_show | rescheduled
            $table->string('status')->default('booked');
            $table->string('channel')->default('web'); // web | app | phone | walk_in | ai
            $table->unsignedTinyInteger('no_show_score')->default(0); // 0-100 risk
            $table->boolean('is_telehealth')->default(false);
            $table->string('telehealth_link')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider_id', 'start_at']);
            $table->index(['clinic_id', 'start_at']);
            $table->index('status');
            // DB-level guard against exact-slot double-booking for the same provider.
            $table->unique(['provider_id', 'start_at', 'deleted_at'], 'uniq_provider_slot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
