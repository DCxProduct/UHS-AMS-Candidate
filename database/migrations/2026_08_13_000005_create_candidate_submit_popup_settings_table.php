<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_submit_popup_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('title_en')->nullable();
            $table->string('title_km')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_km')->nullable();
            $table->string('confirm_label_en')->nullable();
            $table->string('confirm_label_km')->nullable();
            $table->string('cancel_label_en')->nullable();
            $table->string('cancel_label_km')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_submit_popup_settings');
    }
};
