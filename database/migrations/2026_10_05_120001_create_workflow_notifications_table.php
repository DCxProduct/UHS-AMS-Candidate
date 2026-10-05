<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('stages')->nullable();
            $table->timestamps();
        });

        // Link table, so custom_forms is not altered. A form follows one template.
        Schema::create('workflow_notification_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_notification_id')->constrained('workflow_notifications')->cascadeOnDelete();
            $table->foreignId('custom_form_id')->unique()->constrained('custom_forms')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_notification_forms');
        Schema::dropIfExists('workflow_notifications');
    }
};
