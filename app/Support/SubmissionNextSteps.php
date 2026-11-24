<?php

namespace App\Support;

use App\Filament\Student\Pages\SubmissionNextSteps as SubmissionNextStepsPage;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubmissionNextSteps
{
    public static function content(?CustomForm $form): ?array
    {
        return self::renderSettings($form?->submission_next_steps ?? []);
    }

    public static function contentForEntry(CustomFormEntry $entry): ?array
    {
        if (! self::isSubmitted($entry)) {
            return self::content($entry->customForm);
        }

        $snapshot = self::freeze($entry);

        return $snapshot === null
            ? self::content($entry->customForm)
            : self::renderSettings($snapshot['settings'] ?? []);
    }

    private static function renderSettings(array $settings): ?array
    {
        if (! ($settings['is_active'] ?? false)) {
            return null;
        }

        $locale = app()->getLocale() === 'km' ? 'km' : 'en';
        $fallback = $locale === 'km' ? 'en' : 'km';
        $text = static fn (string $key): string => trim((string) (
            filled($settings[$key.'_'.$locale] ?? null)
                ? $settings[$key.'_'.$locale]
                : ($settings[$key.'_'.$fallback] ?? '')
        ));
        $title = $text('title');
        $message = $text('message');

        if ($title === '' && $message === '') {
            return null;
        }

        return [
            'heading' => $text('heading') ?: __('submission_next_steps.submitted'),
            'title' => $title ?: __('submission_next_steps.title'),
            'message' => $message,
            'continue_label' => $text('continue_label') ?: __('submission_next_steps.continue'),
        ];
    }

    public static function isSubmitted(CustomFormEntry $entry): bool
    {
        return $entry->exists && ! self::isDraft($entry);
    }

    private static function isDraft(CustomFormEntry $entry): bool
    {
        return in_array('draft', [
            strtolower(trim((string) $entry->review_status)),
            strtolower(trim((string) $entry->status)),
            strtolower(trim((string) data_get($entry->data, 'registration_status'))),
        ], true);
    }

    public static function snapshotForForm(?CustomForm $form): array
    {
        return [
            'version' => 1,
            'captured_at' => now()->toIso8601String(),
            'settings' => $form?->submission_next_steps ?? [],
        ];
    }

    /** Capture the first submission and protect it from later form edits or resubmissions. */
    public static function prepareForSave(CustomFormEntry $entry): void
    {
        if (! Schema::hasColumn($entry->getTable(), 'submission_next_steps_snapshot')) {
            return;
        }

        $stored = $entry->exists
            ? DB::table($entry->getTable())->where($entry->getKeyName(), $entry->getKey())->value('submission_next_steps_snapshot')
            : null;

        $entry->submission_next_steps_snapshot = $stored !== null
            ? self::decode($stored)
            : (self::isDraft($entry) ? null : self::snapshotForForm(CustomForm::query()->find($entry->custom_form_id)));
    }

    /** Also handle historical submissions and insert/update paths that bypass model events. */
    private static function freeze(CustomFormEntry $entry): ?array
    {
        if (! Schema::hasColumn($entry->getTable(), 'submission_next_steps_snapshot')) {
            return null;
        }

        $query = DB::table($entry->getTable())->where($entry->getKeyName(), $entry->getKey());
        $stored = (clone $query)->value('submission_next_steps_snapshot');
        if ($stored === null) {
            $snapshot = self::snapshotForForm(CustomForm::query()->find($entry->custom_form_id));
            (clone $query)->whereNull('submission_next_steps_snapshot')->update([
                'submission_next_steps_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $stored = (clone $query)->value('submission_next_steps_snapshot');
        }

        return $entry->submission_next_steps_snapshot = self::decode($stored);
    }

    public static function freezeSubmittedEntries(CustomForm $form): void
    {
        if (! Schema::hasColumn('custom_form_entries', 'submission_next_steps_snapshot')) {
            return;
        }

        $snapshot = json_encode(self::snapshotForForm($form), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        CustomFormEntry::query()->where('custom_form_id', $form->getKey())
            ->whereNull('submission_next_steps_snapshot')
            ->chunkById(200, function ($entries) use ($snapshot): void {
                $ids = $entries->filter(fn (CustomFormEntry $entry): bool => self::isSubmitted($entry))->modelKeys();
                if ($ids !== []) {
                    DB::table('custom_form_entries')->whereIn('id', $ids)->whereNull('submission_next_steps_snapshot')
                        ->update(['submission_next_steps_snapshot' => $snapshot]);
                }
            });
    }

    public static function updateSettings(int $formId, array $settings): CustomForm
    {
        return DB::transaction(function () use ($formId, $settings): CustomForm {
            $form = CustomForm::query()->where('is_active', true)->lockForUpdate()->findOrFail($formId);
            self::freezeSubmittedEntries($form);
            $form->updateQuietly(['submission_next_steps' => $settings]);

            return $form;
        });
    }

    private static function decode(mixed $snapshot): ?array
    {
        return is_string($snapshot) ? json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR) : $snapshot;
    }

    public static function redirectUrl(?CustomFormEntry $entry): ?string
    {
        if (auth()->user()?->registration_type !== 'student' || ! $entry || ! self::isSubmitted($entry)) {
            return null;
        }

        if (! self::contentForEntry($entry)) {
            return null;
        }

        return SubmissionNextStepsPage::getUrl(['record' => $entry->getKey()], panel: 'app');
    }

    public static function continueUrl(CustomFormEntry $entry): string
    {
        if ($entry->customForm?->isProfileForm()) {
            return CustomFormEntryResource::getUrl('edit', [
                'record' => $entry->getKey(),
                'view_submitted' => 1,
            ], panel: 'app');
        }

        return CustomFormEntryResource::getUrl('index', [
            'tableFilters' => ['custom_form_id' => ['value' => $entry->custom_form_id]],
        ], panel: 'app');
    }
}
