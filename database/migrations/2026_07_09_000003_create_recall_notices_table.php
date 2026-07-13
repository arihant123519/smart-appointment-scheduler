<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "due for outreach" ledger for the retention loop — mirrors
 * AppointmentNotification's scheduled-row pattern. One row per planned
 * recall / care-gap / follow-through nudge; the recall:dispatch command
 * queries the due() scope and sends via RetentionService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recall_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('type'); // recall | care_gap | follow_through
            $table->dateTime('due_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending'); // pending | sent | booked | skipped
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recall_notices');
    }
};
