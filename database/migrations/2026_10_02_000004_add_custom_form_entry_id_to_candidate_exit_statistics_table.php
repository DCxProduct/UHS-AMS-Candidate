<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_exit_statistics', function (Blueprint $table): void {
            $table->unsignedBigInteger('custom_form_entry_id')
                ->nullable()
                ->unique()
                ->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_exit_statistics', function (Blueprint $table): void {
            $table->dropUnique(['custom_form_entry_id']);
            $table->dropColumn('custom_form_entry_id');
        });
    }
};
