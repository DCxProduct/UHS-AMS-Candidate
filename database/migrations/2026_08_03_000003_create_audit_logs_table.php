<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('actor');
            $table->string('actor_name')->nullable();
            $table->string('action', 50);
            $table->string('module', 150);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['action', 'created_at']);
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
