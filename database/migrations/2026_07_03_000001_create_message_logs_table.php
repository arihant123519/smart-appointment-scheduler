<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single queryable log of every message/email the app sends or receives —
 * reminders, appointment notifications, announcements, flow conversations,
 * and inbound WhatsApp replies all land here via MessageService (outbound)
 * or the Gupshup webhook (inbound), regardless of which feature triggered them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('direction')->default('outbound'); // outbound | inbound
            $table->string('channel');                        // email | whatsapp
            $table->string('status')->default('sent');        // queued|sent|delivered|read|failed|received
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('address')->nullable();  // email or normalized phone actually used
            $table->string('source')->nullable();    // reminder|appointment_notification|announcement|flow|manual_test|webhook_inbound
            $table->string('event_key')->nullable(); // App\Support\WhatsappTemplate::EVENTS key, when applicable
            $table->string('template_id')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('provider')->nullable(); // log | gupshup | smtp
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['channel', 'direction', 'status']);
            $table->index('provider_message_id');
            $table->index('appointment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
