<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'exchange_rate')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('exchange_rate', 12, 2)->nullable()->after('type_payment');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('exchange_rate');
        });
    }
};
