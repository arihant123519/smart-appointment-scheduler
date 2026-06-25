<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('channel');   // whatsapp | sms | email | push | voice
            $table->string('type')->default('reminder'); // reminder | confirmation | recall | follow_up
            $table->string('template')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('scheduled'); // scheduled | sent | delivered | failed | cancelled
            $table->string('response')->nullable(); // confirmed | reschedule | cancel
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
