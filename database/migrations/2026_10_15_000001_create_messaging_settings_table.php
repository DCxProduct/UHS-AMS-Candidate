<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gmail and PlasGate settings edited from the admin menu; empty values fall back to .env.
     */
    public function up(): void
    {
        Schema::create('messaging_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('mail_host')->nullable();
            $table->unsignedInteger('mail_port')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();
            $table->text('plasgate_private_key')->nullable();
            $table->text('plasgate_secret')->nullable();
            $table->string('plasgate_sender')->nullable();
            $table->string('plasgate_test_phone')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_settings');
    }
};
