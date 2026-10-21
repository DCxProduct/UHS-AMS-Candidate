<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_submit_popup_settings', function (Blueprint $table): void {
            $table->foreignId('custom_form_id')
                ->nullable()
                ->after('id')
                ->unique()
                ->constrained('custom_forms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_submit_popup_settings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('custom_form_id');
        });
    }
};
