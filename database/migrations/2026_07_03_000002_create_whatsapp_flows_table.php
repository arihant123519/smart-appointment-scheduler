<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A staff-authored WhatsApp conversation flow (built visually in the flow
 * builder UI). `canvas_json` is the raw Drawflow export, kept only so the
 * layout can be re-opened for editing; `graph` is the normalized
 * {start, nodes:{id:{type,data,next}}} structure FlowEngine actually executes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->string('name');
            $table->string('trigger_event')->nullable(); // App\Support\WhatsappTemplate::EVENTS key
            $table->string('status')->default('draft');  // draft | active | archived
            $table->json('canvas_json')->nullable();
            $table->json('graph')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_event', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};
