<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('target_key');
            $table->string('label_en')->nullable();
            $table->string('label_km')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['role_id', 'target_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_menus');
    }
};
