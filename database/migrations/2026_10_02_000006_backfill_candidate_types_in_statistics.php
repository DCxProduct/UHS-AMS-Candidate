<?php

use App\Models\CandidateExitStatistic;
use App\Support\CandidateTypeResolver;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidate_exit_statistics') || ! Schema::hasTable('custom_form_entries')) {
            return;
        }

        CandidateExitStatistic::query()
            ->whereNotNull('custom_form_entry_id')
            ->chunkById(100, function ($statistics): void {
                $statistics->each(function (CandidateExitStatistic $statistic): void {
                    $entry = CustomFormEntry::query()
                        ->with('creator')
                        ->find($statistic->custom_form_entry_id);

                    if (! $entry) {
                        return;
                    }

                    $candidateType = CandidateTypeResolver::resolve($entry->creator, $entry->data);

                    if (filled($candidateType)) {
                        $statistic->forceFill(['user_type' => $candidateType])->saveQuietly();
                    }
                });
            });
    }

    public function down(): void
    {
        // Existing candidate type values are retained when rolling back.
    }
};
