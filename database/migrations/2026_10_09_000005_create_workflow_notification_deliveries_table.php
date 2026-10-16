<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {


        Schema::create('workflow_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('custom_form_entry_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('stage_type');
            $table->string('workflow_event')->nullable();
            $table->json('channels');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_notification_deliveries');
    }
};
