<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled lead-time notifications for Booked / Confirmed appointments.
 *
 * One row = "send a reminder for appointment X on channel C at time T". The
 * `appointments:notify` command dispatches the due ones via email / WhatsApp.
 * This is separate from the legacy `reminders` table (email-only cadence).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('channel');                 // email | whatsapp
            $table->integer('hours_before')->default(0); // lead time this row was derived from
            $table->timestamp('send_at');
            $table->string('status')->default('scheduled'); // scheduled | sent | failed | cancelled
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_notifications');
    }
};
