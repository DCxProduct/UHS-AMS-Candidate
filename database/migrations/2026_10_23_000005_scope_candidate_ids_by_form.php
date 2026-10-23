<?php

use App\Models\Major;
use App\Support\FormEntryData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_form_entries') || ! Schema::hasColumn('custom_form_entries', 'candidate_id')) {
            return;
        }

        Schema::dropIfExists('candidate_account_ids');
        Schema::create('candidate_account_ids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('major_abbreviation', 50);
            $table->unsignedSmallInteger('year_suffix');
            $table->string('candidate_id', 100)->nullable();
            $table->timestamps();
            $table->unique(
                ['custom_form_id', 'user_id', 'major_abbreviation', 'year_suffix'],
                'candidate_form_account_major_year_unique'
            );
            $table->unique(['custom_form_id', 'candidate_id'], 'candidate_form_id_unique');
        });

        Schema::dropIfExists('candidate_id_sequences');
        Schema::create('candidate_id_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_id')->constrained('custom_forms')->cascadeOnDelete();
            $table->string('major_abbreviation', 50);
            $table->unsignedSmallInteger('submission_year');
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
            $table->unique(
                ['custom_form_id', 'major_abbreviation', 'submission_year'],
                'candidate_form_major_year_sequence_unique'
            );
        });

        $groups = [];
        DB::table('custom_form_entries')
            ->whereNotNull('candidate_id')
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->chunkById(500, function ($entries) use (&$groups): void {
                foreach ($entries as $entry) {
                    $data = json_decode((string) $entry->data, true);
                    $majorValue = FormEntryData::firstFilled(
                        is_array($data) ? $data : [],
                        FormEntryData::majorKeys(),
                    );
                    $major = $this->resolveMajor($majorValue);
                    $abbreviation = strtoupper(trim((string) $major?->abbreviation));
                    if (! $major || $abbreviation === '') {
                        continue;
                    }

                    $entryAt = $entry->reviewed_at ?: $entry->created_at;
                    $yearSuffix = ((int) date('Y', strtotime((string) $entryAt ?: 'now'))) % 100;
                    $key = implode(':', [(int) $entry->custom_form_id, (int) $entry->created_by, $abbreviation, $yearSuffix]);
                    $groups[$key] ??= [
                        'form_id' => (int) $entry->custom_form_id,
                        'user_id' => (int) $entry->created_by,
                        'abbreviation' => $abbreviation,
                        'year_suffix' => $yearSuffix,
                        'candidate_id' => strtoupper((string) $entry->candidate_id),
                    ];
                }
            });

        foreach ($groups as $group) {
            DB::table('candidate_account_ids')->insertOrIgnore([
                'custom_form_id' => $group['form_id'],
                'user_id' => $group['user_id'],
                'major_abbreviation' => $group['abbreviation'],
                'year_suffix' => $group['year_suffix'],
                'candidate_id' => $group['candidate_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sequences = [];
        DB::table('custom_form_entries')
            ->whereNotNull('candidate_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries) use (&$sequences): void {
                foreach ($entries as $entry) {
                    $candidateId = strtoupper((string) $entry->candidate_id);
                    if (! preg_match('/^([A-Z0-9]+)(\d{2})(\d{4,})$/', $candidateId, $matches)) {
                        continue;
                    }

                    $data = json_decode((string) $entry->data, true);
                    $majorValue = FormEntryData::firstFilled(
                        is_array($data) ? $data : [],
                        FormEntryData::majorKeys(),
                    );
                    $major = $this->resolveMajor($majorValue);
                    $abbreviation = strtoupper(trim((string) $major?->abbreviation));
                    if ($abbreviation === '' || $abbreviation !== $matches[1]) {
                        continue;
                    }

                    $key = implode(':', [(int) $entry->custom_form_id, $abbreviation, (int) $matches[2]]);
                    $sequences[$key] = max($sequences[$key] ?? 0, (int) $matches[3]);
                }
            });

        foreach ($sequences as $key => $lastSequence) {
            [$formId, $abbreviation, $yearSuffix] = explode(':', $key, 3);
            DB::table('candidate_id_sequences')->insertOrIgnore([
                'custom_form_id' => (int) $formId,
                'major_abbreviation' => $abbreviation,
                'submission_year' => (int) $yearSuffix,
                'last_sequence' => $lastSequence,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_account_ids');
        Schema::dropIfExists('candidate_id_sequences');
    }

    private function resolveMajor(mixed $value): ?Major
    {
        if (is_array($value)) {
            $value = count($value) === 1 ? reset($value) : null;
        }

        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);
        if (ctype_digit($value) && ($major = Major::query()->find((int) $value))) {
            return $major;
        }

        $abbreviation = strtoupper((string) str($value)->before('-')->trim());

        return Major::query()->whereRaw('UPPER(abbreviation) = ?', [$abbreviation])->first();
    }
};
