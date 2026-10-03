<?php

use App\Support\CandidateStatisticsSynchronizer;
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

        CustomFormEntry::query()
            ->with(['customForm.parentForm', 'creator'])
            ->chunkById(100, function ($entries): void {
                $entries->each(fn (CustomFormEntry $entry): mixed => CandidateStatisticsSynchronizer::syncEntry($entry));
            });
    }

    public function down(): void
    {
        // Linked records are retained so rolling back does not delete statistics data.
    }
};
