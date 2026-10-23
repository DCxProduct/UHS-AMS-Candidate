<?php

namespace App\Services;

use App\Models\Major;
use App\Support\FormEntryData;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;

class CandidateIdGenerator
{
    /**
     * Generate the next ID for a selected major in the given submission year.
     * Call from the same transaction that persists the candidate application.
     */
    public function next(mixed $majorValue, int $year, int $formId): ?string
    {
        $major = $this->resolveMajor($majorValue);
        $abbreviation = strtoupper(trim((string) $major?->abbreviation));

        if ($abbreviation === '') {
            return null;
        }

        $yearSuffix = $year % 100;
        DB::table('candidate_id_sequences')->insertOrIgnore([
            'custom_form_id' => $formId,
            'major_abbreviation' => $abbreviation,
            'submission_year' => $yearSuffix,
            'last_sequence' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('candidate_id_sequences')
            ->where('custom_form_id', $formId)
            ->where('major_abbreviation', $abbreviation)
            ->where('submission_year', $yearSuffix)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            return null;
        }

        $next = ((int) $sequence->last_sequence) + 1;
        DB::table('candidate_id_sequences')
            ->where('id', $sequence->id)
            ->update(['last_sequence' => $next, 'updated_at' => now()]);

        return sprintf('%s%02d%04d', $abbreviation, $yearSuffix, $next);
    }

    /** Assign an ID once, at the point the application is checked. */
    public function assignToEntry(CustomFormEntry $entry): ?string
    {
        return DB::transaction(function () use ($entry): ?string {
            $current = DB::table('custom_form_entries')
                ->where('id', $entry->getKey())
                ->lockForUpdate()
                ->first();

            if (! $current) {
                return null;
            }

            $data = json_decode((string) $current->data, true);
            $majorValue = FormEntryData::firstFilled(
                is_array($data) ? $data : [],
                FormEntryData::majorKeys(),
            );
            $major = $this->resolveMajor($majorValue);
            $abbreviation = strtoupper(trim((string) $major?->abbreviation));
            $year = now()->year;
            $formId = (int) $current->custom_form_id;

            if (! $major || $abbreviation === '') {
                return null;
            }

            if (filled($current->created_by)) {
                DB::table('candidate_account_ids')->insertOrIgnore([
                    'custom_form_id' => $formId,
                    'user_id' => $current->created_by,
                    'major_abbreviation' => $abbreviation,
                    'year_suffix' => $year % 100,
                    'candidate_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $accountMajorId = DB::table('candidate_account_ids')
                    ->where('custom_form_id', $formId)
                    ->where('user_id', $current->created_by)
                    ->where('major_abbreviation', $abbreviation)
                    ->where('year_suffix', $year % 100)
                    ->lockForUpdate()
                    ->first();

                if (! $accountMajorId) {
                    return null;
                }

                if (filled($accountMajorId?->candidate_id)) {
                    $candidateId = (string) $accountMajorId->candidate_id;
                } else {
                    $candidateId = $this->next($major->getKey(), $year, $formId);

                    if (! $candidateId) {
                        return null;
                    }

                    DB::table('candidate_account_ids')
                        ->where('id', $accountMajorId->id)
                        ->update(['candidate_id' => $candidateId, 'updated_at' => now()]);
                }

                $this->syncAccountMajorEntries($formId, (int) $current->created_by, $abbreviation, $year, $candidateId, (int) $current->id);
            } else {
                $candidateId = filled($current->candidate_id)
                    && $this->candidateIdMatches($current->candidate_id, $abbreviation, $year)
                    ? (string) $current->candidate_id
                    : $this->next($major->getKey(), $year, $formId);

                DB::table('custom_form_entries')
                    ->where('id', $current->id)
                    ->update(['candidate_id' => $candidateId, 'updated_at' => now()]);
            }

            return $candidateId;
        });
    }

    public function resetFormSequenceIfEmpty(int $formId): void
    {
        if (DB::table('custom_form_entries')->where('custom_form_id', $formId)->exists()) {
            return;
        }

        DB::table('candidate_account_ids')->where('custom_form_id', $formId)->delete();
        DB::table('candidate_id_sequences')->where('custom_form_id', $formId)->delete();
    }

    private function syncAccountMajorEntries(int $formId, int $userId, string $abbreviation, int $year, string $candidateId, int $currentEntryId): void
    {
        DB::table('custom_form_entries')
            ->where('custom_form_id', $formId)
            ->where('created_by', $userId)
            ->orderBy('id')
            ->chunkById(200, function ($entries) use ($abbreviation, $year, $candidateId, $currentEntryId): void {
                foreach ($entries as $entry) {
                    if ((int) $entry->id === $currentEntryId) {
                        DB::table('custom_form_entries')->where('id', $entry->id)->update([
                            'candidate_id' => $candidateId,
                            'updated_at' => now(),
                        ]);

                        continue;
                    }

                    $entryData = json_decode((string) $entry->data, true);
                    $majorValue = FormEntryData::firstFilled(
                        is_array($entryData) ? $entryData : [],
                        FormEntryData::majorKeys(),
                    );
                    $major = $this->resolveMajor($majorValue);
                    $entryYear = (int) date('Y', strtotime((string) ($entry->reviewed_at ?: $entry->created_at) ?: 'now'));

                    if (strtoupper(trim((string) $major?->abbreviation)) !== $abbreviation || $entryYear !== $year) {
                        continue;
                    }

                    DB::table('custom_form_entries')->where('id', $entry->id)->update([
                        'candidate_id' => $candidateId,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function candidateIdMatches(string $candidateId, string $abbreviation, int $year): bool
    {
        return preg_match('/^'.preg_quote($abbreviation, '/').sprintf('%02d', $year % 100).'\d{4,}$/', strtoupper($candidateId)) === 1;
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

        if (ctype_digit($value)) {
            $major = Major::query()->find((int) $value);

            if ($major) {
                return $major;
            }
        }

        $abbreviation = strtoupper((string) str($value)->before('-')->trim());

        return Major::query()
            ->whereRaw('UPPER(abbreviation) = ?', [$abbreviation])
            ->first();
    }
}
