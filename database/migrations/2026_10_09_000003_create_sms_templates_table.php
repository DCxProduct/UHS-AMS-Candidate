<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per system SMS; empty texts fall back to the defaults in App\Models\SmsTemplate.
        Schema::create('sms_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('app_name')->nullable();
            $table->json('custom_variables')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_km')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_templates');
    }
};
