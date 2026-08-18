<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'custom_form_entry_id')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->foreignId('custom_form_entry_id')
                    ->nullable()
                    ->after('form_id')
                    ->constrained('custom_form_entries')
                    ->nullOnDelete();
            });
        }

        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();

        if ($ownerColumns === []) {
            return;
        }

        DB::table('payments')
            ->whereNull('custom_form_entry_id')
            ->whereNotNull('users_id')
            ->whereNotNull('form_id')
            ->orderBy('id')
            ->get(['id', 'users_id', 'form_id'])
            ->each(function (object $payment) use ($ownerColumns): void {
                $matches = DB::table('custom_form_entries')
                    ->where('custom_form_id', $payment->form_id)
                    ->where(function (QueryBuilder $query): void {
                        $query
                            ->where('data->candidate_status', 'passed')
                            ->orWhereIn('review_status', ['approved', 'accepted', 'passed'])
                            ->orWhereIn('data->registration_status', ['approved', 'accepted', 'passed']);
                    })
                    ->where(function (QueryBuilder $query) use ($ownerColumns, $payment): void {
                        foreach ($ownerColumns as $index => $column) {
                            if ($index === 0) {
                                $query->where($column, $payment->users_id);

                                continue;
                            }

                            $query->orWhere($column, $payment->users_id);
                        }
                    })
                    ->pluck('id');

                if ($matches->count() !== 1) {
                    return;
                }

                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update(['custom_form_entry_id' => $matches->first()]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'custom_form_entry_id')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('custom_form_entry_id');
        });
    }
};
