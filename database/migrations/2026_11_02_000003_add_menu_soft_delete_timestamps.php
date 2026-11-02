<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENTRY_COLUMNS = [
        'hidden_from_form_entries' => 'form_entries_deleted_at',
        'hidden_from_payment_status_lists' => 'payment_status_lists_deleted_at',
        'hidden_from_review_applications' => 'entrance_statistics_deleted_at',
        'hidden_from_exam_results' => 'passed_entrance_deleted_at',
        'hidden_from_exit_exam_results' => 'passed_exit_deleted_at',
    ];

    public function up(): void
    {
        $deletedAt = now();

        if (Schema::hasTable('custom_form_entries')) {
            foreach (self::ENTRY_COLUMNS as $column) {
                if (! Schema::hasColumn('custom_form_entries', $column)) {
                    Schema::table('custom_form_entries', function (Blueprint $table) use ($column): void {
                        $table->timestamp($column)->nullable()->index();
                    });
                }
            }

            // Decode legacy JSON in PHP so the same backfill works on PostgreSQL and SQLite.
            // Query-builder updates preserve the application data and its original timestamps.
            DB::table('custom_form_entries')->select(['id', 'data'])->chunkById(500, function ($entries) use ($deletedAt): void {
                $deletedIds = array_fill_keys(array_values(self::ENTRY_COLUMNS), []);

                foreach ($entries as $entry) {
                    $data = is_array($entry->data) ? $entry->data : json_decode((string) $entry->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    foreach (self::ENTRY_COLUMNS as $flag => $column) {
                        if (array_key_exists($flag, $data)
                            && ! in_array($data[$flag], [null, false, 0, '0', 'false'], true)) {
                            $deletedIds[$column][] = $entry->id;
                        }
                    }
                }

                foreach ($deletedIds as $column => $ids) {
                    if ($ids !== []) {
                        DB::table('custom_form_entries')->whereIn('id', $ids)->whereNull($column)->update([$column => $deletedAt]);
                    }
                }
            });
        }

        if (Schema::hasTable('candidate_exit_statistics')) {
            if (! Schema::hasColumn('candidate_exit_statistics', 'statistics_deleted_at')) {
                Schema::table('candidate_exit_statistics', function (Blueprint $table): void {
                    $table->timestamp('statistics_deleted_at')->nullable()->index();
                });
            }

            if (Schema::hasColumn('candidate_exit_statistics', 'hidden_from_statistics')) {
                DB::table('candidate_exit_statistics')
                    ->where('hidden_from_statistics', true)
                    ->whereNull('statistics_deleted_at')
                    ->update(['statistics_deleted_at' => $deletedAt]);
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'custom_form_entries' => array_values(self::ENTRY_COLUMNS),
            'candidate_exit_statistics' => ['statistics_deleted_at'],
        ];

        foreach ($tables as $name => $columns) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($name, $column)) {
                    Schema::table($name, function (Blueprint $table) use ($column): void {
                        $table->dropIndex([$column]);
                        $table->dropColumn($column);
                    });
                }
            }
        }
    }
};
