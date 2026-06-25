<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_channel')->default('email')->after('locale'); // email|sms|whatsapp
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('recurring_group')->nullable()->after('channel')->index();
            $table->boolean('confirmation_requested')->default(false)->after('confirmed_at');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('response');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('preferred_channel'));
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn(['recurring_group', 'confirmation_requested']));
        Schema::table('reminders', fn (Blueprint $t) => $t->dropColumn('token'));
    }
};
