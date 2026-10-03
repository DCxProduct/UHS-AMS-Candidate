<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exit_statistics', function (Blueprint $table): void {
            $table->boolean('hidden_from_statistics')->default(false)->after('candidate_reviewed_at');
            $table->index('hidden_from_statistics');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_exit_statistics', function (Blueprint $table): void {
            $table->dropIndex(['hidden_from_statistics']);
            $table->dropColumn('hidden_from_statistics');
        });
    }
};
