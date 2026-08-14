<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degree_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label_en');
            $table->string('label_kh')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_levels');
    }
};
