<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('candidate_account_ids')) {
            Schema::create('candidate_account_ids', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('major_abbreviation', 50);
                $table->unsignedSmallInteger('year_suffix');
                $table->string('candidate_id', 100)->nullable()->unique();
                $table->timestamps();
                $table->unique(
                    ['user_id', 'major_abbreviation', 'year_suffix'],
                    'candidate_account_major_year_unique'
                );
            });
        }

        if (! Schema::hasTable('custom_form_entries') || ! Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            $this->dropLegacyUserCandidateId();

            return;
        }

        $majorAbbreviations = DB::table('majors')
            ->whereNotNull('abbreviation')
            ->pluck('abbreviation', 'id')
            ->mapWithKeys(fn ($abbreviation, $id): array => [(string) $id => strtoupper(trim((string) $abbreviation))]);

        $groups = [];
        DB::table('custom_form_entries')
            ->whereNotNull('created_by')
            ->whereIn('review_status', ['accepted', 'approved', 'passed'])
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($majorAbbreviations, &$groups): void {
                foreach ($entries as $entry) {
                    $majorValue = $this->majorValue($entry->data);
                    $abbreviation = $majorAbbreviations[(string) $majorValue]
                        ?? strtoupper((string) strtok((string) $majorValue, '-'));

                    if ($abbreviation === '' || ! preg_match('/^[A-Z0-9]+$/', $abbreviation)) {
                        continue;
                    }

                    $checkedAt = $entry->reviewed_at ?: $entry->updated_at ?: $entry->created_at;
                    $yearSuffix = ((int) date('Y', strtotime((string) $checkedAt ?: 'now'))) % 100;
                    $key = implode(':', [(int) $entry->created_by, $abbreviation, $yearSuffix]);
                    $groups[$key] ??= [
                        'user_id' => (int) $entry->created_by,
                        'abbreviation' => $abbreviation,
                        'year_suffix' => $yearSuffix,
                        'entries' => [],
                        'candidate_id' => null,
                    ];
                    $groups[$key]['entries'][] = (int) $entry->id;

                    $candidateId = strtoupper((string) $entry->candidate_id);
                    if ($groups[$key]['candidate_id'] === null && $this->matches($candidateId, $abbreviation, $yearSuffix)) {
                        $groups[$key]['candidate_id'] = $candidateId;
                    }
                }
            });

        // Respect all sequence values already recorded, then repair IDs whose prefix does not
        // match the major selected on that application.
        $sequences = [];
        foreach (DB::table('candidate_id_sequences')->get() as $sequence) {
            $sequences[strtoupper($sequence->major_abbreviation).':'.(int) $sequence->submission_year] = (int) $sequence->last_sequence;
        }

        $claimed = [];
        foreach ($groups as $group) {
            $id = $group['candidate_id'];
            if ($id !== null && ! isset($claimed[$id])) {
                $claimed[$id] = true;
                $sequence = (int) substr($id, strlen($group['abbreviation']) + 2);
                $sequenceKey = $group['abbreviation'].':'.$group['year_suffix'];
                $sequences[$sequenceKey] = max($sequences[$sequenceKey] ?? 0, $sequence);
            } else {
                $id = $this->nextId($group['abbreviation'], $group['year_suffix'], $sequences);
                $claimed[$id] = true;
            }

            DB::table('candidate_account_ids')->updateOrInsert(
                [
                    'user_id' => $group['user_id'],
                    'major_abbreviation' => $group['abbreviation'],
                    'year_suffix' => $group['year_suffix'],
                ],
                ['candidate_id' => $id, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('custom_form_entries')->whereIn('id', $group['entries'])->update([
                'candidate_id' => $id,
                'updated_at' => now(),
            ]);
        }

        foreach ($sequences as $key => $lastSequence) {
            [$abbreviation, $yearSuffix] = explode(':', $key, 2);
            DB::table('candidate_id_sequences')->updateOrInsert(
                ['major_abbreviation' => $abbreviation, 'submission_year' => (int) $yearSuffix],
                ['last_sequence' => $lastSequence, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Keep a candidate's major/year ID visible on their other applications for that same
        // major/year, without assigning IDs to accounts that have never had a checked entry.
        foreach ($groups as $group) {
            $mapping = DB::table('candidate_account_ids')
                ->where('user_id', $group['user_id'])
                ->where('major_abbreviation', $group['abbreviation'])
                ->where('year_suffix', $group['year_suffix'])
                ->first();

            if (! $mapping) {
                continue;
            }

            DB::table('custom_form_entries')
                ->where('created_by', $group['user_id'])
                ->whereNotIn('id', $group['entries'])
                ->orderBy('id')
                ->chunkById(500, function ($entries) use ($group, $mapping, $majorAbbreviations): void {
                    foreach ($entries as $entry) {
                        $majorValue = $this->majorValue($entry->data);
                        $abbreviation = $majorAbbreviations[(string) $majorValue]
                            ?? strtoupper((string) strtok((string) $majorValue, '-'));
                        $entryAt = $entry->reviewed_at ?: $entry->created_at;
                        $yearSuffix = ((int) date('Y', strtotime((string) $entryAt ?: 'now'))) % 100;

                        if ($abbreviation === $group['abbreviation'] && $yearSuffix === $group['year_suffix']) {
                            DB::table('custom_form_entries')->where('id', $entry->id)->update([
                                'candidate_id' => $mapping->candidate_id,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                });
        }

        $this->dropLegacyUserCandidateId();
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_account_ids');
    }

    private function dropLegacyUserCandidateId(): void
    {
        if (! Schema::hasColumn('users', 'candidate_id')) {
            return;
        }

        $index = collect(Schema::getIndexes('users'))->first(
            fn (array $item): bool => ($item['unique'] ?? false) && ($item['columns'] ?? []) === ['candidate_id']
        );

        Schema::table('users', function (Blueprint $table) use ($index): void {
            if ($index) {
                $table->dropUnique($index['name']);
            }
            $table->dropColumn('candidate_id');
        });
    }

    private function majorValue(mixed $data): mixed
    {
        $data = json_decode((string) $data, true);
        if (! is_array($data)) {
            return null;
        }

        foreach (['selected_major', 'degree_level_major', 'major', 'major_applied'] as $key) {
            if (filled($data[$key] ?? null)) {
                $value = $data[$key];

                return is_array($value) ? (count($value) === 1 ? reset($value) : null) : $value;
            }
        }

        return null;
    }

    private function matches(string $candidateId, string $abbreviation, int $yearSuffix): bool
    {
        return preg_match('/^'.preg_quote($abbreviation, '/').sprintf('%02d', $yearSuffix).'(\d{4,})$/', $candidateId) === 1;
    }

    private function nextId(string $abbreviation, int $yearSuffix, array &$sequences): string
    {
        $key = $abbreviation.':'.$yearSuffix;
        $sequences[$key] = ($sequences[$key] ?? 0) + 1;

        return sprintf('%s%02d%04d', $abbreviation, $yearSuffix, $sequences[$key]);
    }
};
