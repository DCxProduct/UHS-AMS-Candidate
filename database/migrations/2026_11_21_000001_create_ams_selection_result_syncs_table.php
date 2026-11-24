<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_selection_result_syncs', function (Blueprint $table): void {
            $table->unsignedBigInteger('custom_form_entry_id')->primary();
            $table->json('acknowledgement');
            $table->timestamps();

            $table->foreign('custom_form_entry_id')->references('id')->on('custom_form_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_selection_result_syncs');
    }
};
