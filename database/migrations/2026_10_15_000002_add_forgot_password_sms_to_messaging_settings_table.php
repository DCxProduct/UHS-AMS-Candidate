<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turns "Forgot password by phone (SMS OTP)" on or off; on by default, as before.
     */
    public function up(): void
    {
        Schema::table('messaging_settings', function (Blueprint $table): void {
            $table->boolean('forgot_password_sms')->default(true)->after('plasgate_test_phone');
        });
    }

    public function down(): void
    {
        Schema::table('messaging_settings', function (Blueprint $table): void {
            $table->dropColumn('forgot_password_sms');
        });
    }
};
