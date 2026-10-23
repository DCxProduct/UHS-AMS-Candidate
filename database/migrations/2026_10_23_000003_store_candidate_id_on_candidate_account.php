<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'candidate_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('candidate_id', 100)->nullable()->unique();
            });
        }

        $entryIndexes = collect(Schema::getIndexes('custom_form_entries'));
        $candidateIdUniqueIndex = $entryIndexes->first(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['candidate_id']
        );

        if ($candidateIdUniqueIndex) {
            Schema::table('custom_form_entries', function (Blueprint $table) use ($candidateIdUniqueIndex): void {
                $table->dropUnique($candidateIdUniqueIndex['name']);
            });
        }

        if (! Schema::hasTable('candidate_id_sequences') || ! Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            return;
        }

        // Preserve existing IDs while consolidating all of a candidate's applications under one account ID.
        DB::table('custom_form_entries')
            ->whereNotNull('created_by')
            ->whereNotNull('candidate_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $user = DB::table('users')->where('id', $entry->created_by)->first();

                    if (! $user) {
                        continue;
                    }

                    $candidateId = filled($user->candidate_id)
                        ? (string) $user->candidate_id
                        : (string) $entry->candidate_id;

                    if (blank($user->candidate_id)) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->whereNull('candidate_id')
                            ->update(['candidate_id' => $candidateId]);
                    }

                    DB::table('custom_form_entries')
                        ->where('created_by', $user->id)
                        ->update(['candidate_id' => $candidateId]);
                }
            });

        $this->refreshSequences();
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'candidate_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(['candidate_id']);
                $table->dropColumn('candidate_id');
            });
        }
    }

    private function refreshSequences(): void
    {
        $abbreviations = DB::table('majors')
            ->whereNotNull('abbreviation')
            ->pluck('abbreviation')
            ->map(fn ($abbreviation): string => strtoupper(trim((string) $abbreviation)))
            ->filter()
            ->unique()
            ->sortByDesc(fn (string $abbreviation): int => strlen($abbreviation))
            ->values();

        DB::table('candidate_id_sequences')->update(['last_sequence' => 0, 'updated_at' => now()]);

        DB::table('users')
            ->whereNotNull('candidate_id')
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($abbreviations): void {
                $maxSequences = [];

                foreach ($users as $user) {
                    $candidateId = strtoupper((string) $user->candidate_id);
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
};
