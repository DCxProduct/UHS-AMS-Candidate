<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cashier (staff user) who recorded the payment.
     */
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'received_by')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('received_by')
                ->nullable()
                ->after('users_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'received_by')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('received_by');
        });
    }
};
