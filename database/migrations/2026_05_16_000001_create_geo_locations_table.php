<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name_kh', 100)->nullable();
            $table->string('name_en', 100);
            $table->string('code', 8)->unique();
            $table->enum('type', ['province', 'district', 'commune', 'village']);
            $table->unsignedInteger('parent_id')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('geo_locations')->nullOnDelete();
            $table->index('type');
        });
    }
    
    public function down(): void
    {
        Schema::dropIfExists('geo_locations');
    }
};