<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degree_level_major', function (Blueprint $table): void {
            $table->foreignId('degree_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('major_id')->constrained()->cascadeOnDelete();
            $table->primary(['degree_level_id', 'major_id']);
        });

        Schema::table('closing_dates', function (Blueprint $table): void {
            $table->foreignId('degree_level_id')
                ->nullable()
                ->after('type')
                ->constrained('degree_levels')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('closing_dates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('degree_level_id');
        });

        Schema::dropIfExists('degree_level_major');
    }
};
