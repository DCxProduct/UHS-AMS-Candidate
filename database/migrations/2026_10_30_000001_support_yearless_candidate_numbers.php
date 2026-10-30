<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_form_entries') && ! Schema::hasColumn('custom_form_entries', 'candidate_id_year')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->unsignedSmallInteger('candidate_id_year')->nullable();
            });
        }

        if (! Schema::hasTable('candidate_account_ids')) {
            return;
        }

        if (! $this->hasIndex('candidate_form_year_id_unique')) {
            Schema::table('candidate_account_ids', function (Blueprint $table): void {
                $table->unique(['custom_form_id', 'year_suffix', 'candidate_id'], 'candidate_form_year_id_unique');
            });
        }

        if ($this->hasIndex('candidate_form_id_unique')) {
            Schema::table('candidate_account_ids', function (Blueprint $table): void {
                $table->dropUnique('candidate_form_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('candidate_account_ids')) {
            $duplicates = DB::table('candidate_account_ids')
                ->whereNotNull('candidate_id')
                ->select(['custom_form_id', 'candidate_id'])
                ->groupBy('custom_form_id', 'candidate_id')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($duplicates) {
                throw new RuntimeException(
                    'Cannot roll back yearless candidate numbers: the same form has candidate numbers in multiple years. '
                    .'The previous uniqueness constraint cannot be restored without changing historical data. '
                    .'No schema or data has been changed; retain this migration until those records are reconciled.',
                );
            }

            if (! $this->hasIndex('candidate_form_id_unique')) {
                Schema::table('candidate_account_ids', function (Blueprint $table): void {
                    $table->unique(['custom_form_id', 'candidate_id'], 'candidate_form_id_unique');
                });
            }

            if ($this->hasIndex('candidate_form_year_id_unique')) {
                Schema::table('candidate_account_ids', function (Blueprint $table): void {
                    $table->dropUnique('candidate_form_year_id_unique');
                });
            }
        }

        if (Schema::hasTable('custom_form_entries') && Schema::hasColumn('custom_form_entries', 'candidate_id_year')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->dropColumn('candidate_id_year');
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('candidate_account_ids'))
            ->contains(fn (array $index): bool => $index['name'] === $name);
    }
};
