<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments')) {
            $duplicates = DB::table('payments')
                ->select('receipt_number')
                ->whereNotNull('receipt_number')
                ->groupBy('receipt_number')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('receipt_number')
                ->all();

            if ($duplicates !== []) {
                throw new RuntimeException(
                    'Receipt number uniqueness migration stopped. Duplicate historical receipt numbers require business review: '
                    .implode(', ', array_map('strval', $duplicates))
                );
            }

            Schema::table('payments', function (Blueprint $table): void {
                $table->unique('receipt_number', 'payments_receipt_number_unique');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'actor_role')) {
                    $table->string('actor_role', 100)->nullable()->after('actor_name');
                }

                if (! Schema::hasColumn('audit_logs', 'old_values')) {
                    $table->json('old_values')->nullable()->after('description');
                }

                if (! Schema::hasColumn('audit_logs', 'new_values')) {
                    $table->json('new_values')->nullable()->after('old_values');
                }

                if (! Schema::hasColumn('audit_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('new_values');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->dropUnique('payments_receipt_number_unique');
            });
        }

        if (Schema::hasTable('audit_logs')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                foreach (['actor_role', 'old_values', 'new_values', 'metadata'] as $column) {
                    if (Schema::hasColumn('audit_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
