<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A live instance of a WhatsappFlow running against one appointment/patient.
 * Inbound Gupshup webhook replies are matched to the active conversation by
 * normalized phone number, and FlowEngine advances `current_node_id` as the
 * conversation proceeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_flow_id')->constrained('whatsapp_flows')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone'); // normalized digits — the inbound lookup key
            $table->string('current_node_id')->nullable();
            $table->json('context')->nullable(); // token => value map (seeded + captured free text)
            $table->string('status')->default('active'); // active|completed|timed_out|escalated|cancelled
            $table->string('outcome')->nullable(); // admin-labeled end-node outcome, informational
            $table->timestamp('started_at');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['phone', 'status']);
            $table->index(['status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
