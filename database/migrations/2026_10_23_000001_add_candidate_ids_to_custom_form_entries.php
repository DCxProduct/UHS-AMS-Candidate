<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->string('candidate_id', 100)->nullable();
            });
        }

        if (! Schema::hasTable('candidate_id_sequences')) {
            Schema::create('candidate_id_sequences', function (Blueprint $table): void {
                $table->id();
                $table->string('major_abbreviation', 50);
                $table->unsignedSmallInteger('submission_year');
                $table->unsignedInteger('last_sequence')->default(0);
                $table->timestamps();
                $table->unique(['major_abbreviation', 'submission_year'], 'candidate_id_sequences_major_year_unique');
            });
        }

        $this->backfillExistingEntries();
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_id_sequences');

        if (Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->dropUnique(['candidate_id']);
                $table->dropColumn('candidate_id');
            });
        }
    }

    private function backfillExistingEntries(): void
    {
        if (! Schema::hasTable('majors') || ! Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            return;
        }

        $majorAbbreviations = DB::table('majors')
            ->whereNotNull('abbreviation')
            ->pluck('abbreviation', 'id')
            ->mapWithKeys(fn ($abbreviation, $id): array => [(string) $id => strtoupper(trim((string) $abbreviation))]);
        $sequences = [];

        DB::table('custom_form_entries')
            ->whereNull('candidate_id')
            ->whereIn('review_status', ['accepted', 'approved', 'passed'])
            ->orderBy('id')
            ->chunkById(500, function ($entries) use ($majorAbbreviations, &$sequences): void {
                foreach ($entries as $entry) {
                    $data = json_decode((string) $entry->data, true);

                    if (! is_array($data)) {
                        continue;
                    }

                    $majorValue = null;
                    foreach (['selected_major', 'degree_level_major', 'major', 'major_applied'] as $key) {
                        if (filled($data[$key] ?? null)) {
                            $majorValue = $data[$key];
                            break;
                        }
                    }

                    if (is_array($majorValue)) {
                        $majorValue = count($majorValue) === 1 ? reset($majorValue) : null;
                    }

                    if (! is_scalar($majorValue)) {
                        continue;
                    }

                    $majorValue = trim((string) $majorValue);
                    $abbreviation = $majorAbbreviations[$majorValue] ?? strtoupper((string) strtok($majorValue, '-'));

                    if ($abbreviation === '' || ! preg_match('/^[A-Z0-9]+$/', $abbreviation)) {
                        continue;
                    }

                    $checkedAt = $entry->reviewed_at ?: $entry->updated_at ?: $entry->created_at;
                    $year = (int) date('Y', strtotime((string) $checkedAt ?: 'now'));
                    $yearSuffix = $year % 100;
                    $key = $abbreviation.':'.$yearSuffix;
                    $sequences[$key] = ($sequences[$key] ?? 0) + 1;

                    DB::table('custom_form_entries')->where('id', $entry->id)->update([
                        'candidate_id' => sprintf('%s%02d%04d', $abbreviation, $yearSuffix, $sequences[$key]),
                    ]);
                }
            });

        foreach ($sequences as $key => $lastSequence) {
            [$abbreviation, $yearSuffix] = explode(':', $key, 2);
            DB::table('candidate_id_sequences')->insertOrIgnore([
                'major_abbreviation' => $abbreviation,
                'submission_year' => (int) $yearSuffix,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('candidate_id_sequences')
                ->where('major_abbreviation', $abbreviation)
                ->where('submission_year', (int) $yearSuffix)
                ->update(['last_sequence' => $lastSequence, 'updated_at' => now()]);
        }
    }
};
