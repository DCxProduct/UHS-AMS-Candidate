<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every SMS handed to PlasGate, for the read-only SMS History page.
        Schema::create('sms_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('source', 40)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('sent_to', 30)->nullable();
            $table->text('content');
            $table->string('status', 20);
            $table->text('response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
