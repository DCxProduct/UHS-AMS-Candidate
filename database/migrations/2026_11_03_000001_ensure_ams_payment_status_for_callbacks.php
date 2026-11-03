<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enable callbacks independently of the older AMS receipt-cleanup migration.
        // This migration adds schema only; it never removes financial records.
        if (! Schema::hasColumn('ams_payment_requests', 'payment_status')) {
            Schema::table('ams_payment_requests', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->index();
            });
        }

        if (! Schema::hasIndex('ams_payment_requests', 'ams_payment_requests_external_payment_unique')) {
            Schema::table('ams_payment_requests', function (Blueprint $table): void {
                $table->unique('external_payment_id', 'ams_payment_requests_external_payment_unique');
            });
        }
    }

    public function down(): void
    {
        // Shared callback state must survive rollback; the original migration owns it.
    }
};
