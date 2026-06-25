<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->json('schema')->nullable();    // field definitions
            $table->json('responses')->nullable(); // patient answers
            $table->text('ai_summary')->nullable();
            $table->string('status')->default('pending'); // pending | completed | reviewed
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_forms');
    }
};
