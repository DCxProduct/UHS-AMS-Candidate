<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL still permits legacy standalone receipts. Existing duplicates
        // deliberately fail this migration instead of deleting business data.
        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('custom_form_entry_id', 'payments_application_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_application_unique');
        });
    }
};
