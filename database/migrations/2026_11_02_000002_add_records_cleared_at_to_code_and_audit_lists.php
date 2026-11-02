<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mef_invoice_codes', 'audit_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->timestamp('records_cleared_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['mef_invoice_codes', 'audit_logs'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['records_cleared_at']);
                $table->dropColumn('records_cleared_at');
            });
        }
    }
};
