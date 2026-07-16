<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('id')->constrained('clinics')->nullOnDelete();
            $table->string('reviewer_name')->nullable()->after('patient_id');
            $table->string('reviewer_phone', 30)->nullable()->after('reviewer_name');
            $table->string('source')->default('portal')->after('sentiment');
        });

        // Anonymous public submissions have no authenticated patient.
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->change();
        });

        // Backfill clinic_id on existing rows from their linked appointment
        // (portable across drivers — avoids MySQL-only UPDATE...JOIN syntax).
        DB::table('reviews')
            ->whereNull('clinic_id')
            ->whereNotNull('appointment_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $clinicId = DB::table('appointments')->where('id', $row->appointment_id)->value('clinic_id');
                    if ($clinicId) {
                        DB::table('reviews')->where('id', $row->id)->update(['clinic_id' => $clinicId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable(false)->change();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropColumn(['reviewer_name', 'reviewer_phone', 'source']);
        });
    }
};
