<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_guide_books')) {
            Schema::create('system_guide_books', function (Blueprint $table): void {
                $table->id();
                $table->string('title_en');
                $table->string('title_km');
                $table->text('description_en')->nullable();
                $table->text('description_km')->nullable();
                $table->string('file_path');
                $table->string('file_name')->nullable();
                $table->boolean('is_published')->default(false)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_guide_books');
    }
};
