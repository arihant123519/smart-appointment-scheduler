<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('clinic_id')->nullable()->after('id')->constrained('clinics')->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('locale', 10)->default('en')->after('phone');
            $table->date('date_of_birth')->nullable()->after('locale');
            $table->string('gender')->nullable()->after('date_of_birth');
            $table->string('address')->nullable()->after('gender');
            $table->string('avatar')->nullable()->after('address');
            $table->string('mfa_secret')->nullable()->after('avatar');
            $table->boolean('is_active')->default(true)->after('mfa_secret');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
            $table->dropColumn([
                'phone', 'locale', 'date_of_birth', 'gender', 'address',
                'avatar', 'mfa_secret', 'is_active', 'last_login_at', 'deleted_at',
            ]);
        });
    }
};
