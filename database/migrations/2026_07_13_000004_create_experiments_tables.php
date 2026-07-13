<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic A/B testing framework (PRD "testing what actually improves booking
 * completion"): two versions of a specific step shown to different patients,
 * compared on actual completion rates. Assignment is deterministic per
 * subject (same patient always sees the same variant) via a hash split —
 * see App\Services\ExperimentService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // code-defined identifier, e.g. "booking_intro_copy"
            $table->string('name');
            $table->json('variants'); // e.g. ["control", "variant_b"]
            $table->string('status')->default('active'); // active | paused
            $table->timestamps();
        });

        Schema::create('experiment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained('experiments')->cascadeOnDelete();
            $table->string('subject_key'); // e.g. patient id, kept generic so any subject type works
            $table->string('variant');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->unique(['experiment_id', 'subject_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_assignments');
        Schema::dropIfExists('experiments');
    }
};
