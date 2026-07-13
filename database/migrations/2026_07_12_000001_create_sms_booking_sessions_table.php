<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State for the SMS booking protocol (PRD "book by text message"): a cold
 * text gets offered the next 3 open slots; the patient replies with a single
 * digit to lock one in. This is a deliberately tiny 2-step state machine, not
 * the full WhatsApp FlowEngine — SMS has no interactive buttons, so the whole
 * point is "reply with a number", not a multi-turn conversation graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_booking_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone')->index();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->json('offered_slots'); // [{provider_id, start, label}, ...] in reply-number order
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('status')->default('pending'); // pending | booked | expired
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_booking_sessions');
    }
};
