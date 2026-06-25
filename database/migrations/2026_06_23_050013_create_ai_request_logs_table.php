<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('feature');  // nlp_booking | no_show_prediction | intake_summary | ...
            $table->string('provider')->nullable(); // gemini | openai | rule_based
            $table->string('prompt_version')->nullable();
            $table->integer('tokens')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->string('status')->default('success'); // success | failed | fallback
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
