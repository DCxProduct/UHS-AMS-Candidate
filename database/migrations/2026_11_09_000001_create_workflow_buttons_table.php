<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_buttons', function (Blueprint $table): void {
            $table->id();
            $table->string('label_en');
            $table->string('label_km')->nullable();
            $table->string('action_key')->index();
            $table->string('icon')->default('heroicon-o-cursor-arrow-rays');
            $table->string('color')->default('primary');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_buttons');
    }
};
