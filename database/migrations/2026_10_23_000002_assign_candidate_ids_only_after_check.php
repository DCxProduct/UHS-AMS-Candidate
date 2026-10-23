<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('custom_form_entries')
            || ! Schema::hasColumn('custom_form_entries', 'candidate_id')
        ) {
            return;
        }

        DB::table('custom_form_entries')
            ->where(function ($query): void {
                $query->whereNotIn('review_status', ['accepted', 'approved', 'passed'])
                    ->orWhereNull('review_status');
            })
            ->whereNotNull('candidate_id')
            ->update(['candidate_id' => null]);

        if (! Schema::hasTable('candidate_id_sequences')) {
            return;
        }

        DB::table('candidate_id_sequences')->update(['last_sequence' => 0, 'updated_at' => now()]);

        $abbreviations = Schema::hasTable('majors')
            ? DB::table('majors')->whereNotNull('abbreviation')->pluck('abbreviation')->map(
                fn ($abbreviation): string => strtoupper(trim((string) $abbreviation))
            )->filter()->unique()->sortByDesc(fn (string $abbreviation): int => strlen($abbreviation))->values()
            : collect();

        DB::table('custom_form_entries')
            ->whereIn('review_status', ['accepted', 'approved', 'passed'])
            ->whereNotNull('candidate_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($abbreviations): void {
                $maxSequences = [];

                foreach ($entries as $entry) {
                    $candidateId = strtoupper((string) $entry->candidate_id);
                    $abbreviation = $abbreviations->first(fn (string $item): bool => str_starts_with($candidateId, $item));

                    if (! $abbreviation) {
                        continue;
                    }

                    $suffix = substr($candidateId, strlen($abbreviation));

                    if (! preg_match('/^\d{2}(\d{4,})$/', $suffix, $matches)) {
                        continue;
                    }

                    $year = (int) substr($suffix, 0, 2);
                    $sequence = (int) $matches[1];
                    $key = $abbreviation.':'.$year;
                    $maxSequences[$key] = max($maxSequences[$key] ?? 0, $sequence);
                }

                foreach ($maxSequences as $key => $lastSequence) {
                    [$abbreviation, $year] = explode(':', $key, 2);
                    DB::table('candidate_id_sequences')->insertOrIgnore([
                        'major_abbreviation' => $abbreviation,
                        'submission_year' => (int) $year,
                        'last_sequence' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('candidate_id_sequences')
                        ->where('major_abbreviation', $abbreviation)
                        ->where('submission_year', (int) $year)
                        ->update(['last_sequence' => $lastSequence, 'updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // Existing candidate IDs are preserved when rolling back this data correction.
    }
};
