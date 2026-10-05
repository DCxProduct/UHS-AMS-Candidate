<?php

namespace App\Support;

use App\Models\CandidateExitStatistic;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class CandidateStatisticsSynchronizer
{
    public static function syncPassedEntry(CustomFormEntry $entry): void
    {
        self::syncEntry($entry);
    }

    public static function syncPendingEntry(CustomFormEntry $entry): void
    {
        self::syncEntry($entry);

        CandidateExitStatistic::query()
            ->where('custom_form_entry_id', $entry->getKey())
            ->update([
                'candidate_status' => 'pending',
                'candidate_reviewed_at' => null,
                'updated_at' => now(),
            ]);
    }

    public static function syncEntry(CustomFormEntry $entry): void
    {
        $entry->loadMissing(['customForm', 'creator']);

        if (self::statisticsMenuFor($entry) !== StatisticsMenuOptions::EXIT_EXAM_STATISTICS) {
            CandidateExitStatistic::query()
                ->where('custom_form_entry_id', $entry->getKey())
                ->delete();

            return;
        }

        $data = is_array($entry->data) ? $entry->data : [];
        $creator = $entry->creator;
        $status = strtolower((string) data_get($data, 'candidate_status', 'pending')) === 'passed'
            ? 'passed'
            : 'pending';

        CandidateExitStatistic::query()->updateOrCreate(
            ['custom_form_entry_id' => $entry->getKey()],
            [
                'form_type' => self::value($data, ['form_type', 'degree_level', 'selected_degree_level', 'form_selection']),
                'academic_year' => FormEntryData::academicYearValue($data, $creator?->academic_year),
                'user_type' => CandidateTypeResolver::resolve($creator, $data),
                'seat_number' => self::value($data, ['seat_number', 'list_number'], $creator?->seat_number),
                'first_name_kh' => self::value($data, ['first_name_kh']),
                'last_name_kh' => self::value($data, ['last_name_kh']),
                'first_name_en' => self::value($data, ['first_name_en'], self::namePart($creator?->name_latin, 0)),
                'last_name_en' => self::value($data, ['last_name_en'], self::namePart($creator?->name_latin, 1)),
                'gender' => self::value($data, ['gender'], $creator?->gender),
                'major' => FormEntryData::majorValue($data, null),
                'date_of_birth' => self::value($data, ['date_of_birth', 'dob'], $creator?->date_of_birth),
                'candidate_status' => $status,
                'candidate_reviewed_at' => $status === 'passed'
                    ? (data_get($data, 'candidate_reviewed_at') ?: $entry->reviewed_at ?: now())
                    : null,
            ],
        );
    }

    public static function syncForm(CustomForm $form): void
    {
        $query = CustomFormEntry::query()
            ->where('custom_form_id', $form->getKey());

        $query->chunkById(100, function ($entries): void {
            $entries->each(fn (CustomFormEntry $entry): mixed => self::syncEntry($entry));
        });
    }

    public static function statisticsMenuFor(CustomFormEntry $entry): string
    {
        $entry->loadMissing('customForm');
        $form = $entry->customForm;

        if (! $form) {
            return StatisticsMenuOptions::default();
        }

        return StatisticsMenuOptions::normalize($form->statistics_menu);
    }

    protected static function value(array $data, array $keys, mixed $fallback = null): mixed
    {
        return FormEntryData::firstFilled($data, $keys, $fallback);
    }

    protected static function namePart(?string $name, int $index): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return $parts[$index] ?? null;
    }
}
