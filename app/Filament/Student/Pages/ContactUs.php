<?php

namespace App\Filament\Student\Pages;

use App\Models\ClosingDate;
use BackedEnum;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ContactUs extends Page
{
    protected string $view = 'filament.student.pages.contact-us';

    protected static ?string $slug = 'contact-us';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    public function getTitle(): string|Htmlable
    {
        return $this->getFormTitle();
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getFormTitle();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getFormId(): ?int
    {
        $formId = request()->integer('form_id');

        return $formId > 0 ? $formId : null;
    }

    public function getForm(): ?CustomForm
    {
        $formId = $this->getFormId();

        if (! $formId) {
            return null;
        }

        return CustomForm::query()->find($formId);
    }

    public function getFormTitle(): string
    {
        $form = $this->getForm();

        if (! $form) {
            return __('app.contact_us');
        }

        return $this->getTranslatedFormName($form);
    }

    public function getClosingDate(): ?ClosingDate
    {
        $formId = $this->getFormId();

        if (! $formId) {
            return null;
        }

        return ClosingDate::getDeadlineByCustomFormId($formId);
    }

    public function getClosingDateStatus(): array
    {
        $deadline = $this->getClosingDate();

        if (! $deadline) {
            return [
                'status' => 'open',
                'message' => __('app.form_currently_available'),
                'start_date' => null,
                'end_date' => null,
            ];
        }

        $today = now()->startOfDay();

        $startDate = $deadline->start_date
            ? Carbon::parse($deadline->start_date)->startOfDay()
            : null;

        $endDate = $deadline->end_date
            ? Carbon::parse($deadline->end_date)->startOfDay()
            : null;

        if (
            $deadline->status === 'not_open'
            || ($startDate && $today->lt($startDate))
        ) {
            return [
                'status' => 'not_open',
                'message' => __('app.application_not_open_yet'),
                'start_date' => $this->formatDate($deadline->start_date),
                'end_date' => $this->formatDate($deadline->end_date),
            ];
        }

        if (
            $deadline->status === 'closed'
            || ($endDate && $today->gt($endDate))
        ) {
            return [
                'status' => 'expired',
                'message' => __('app.expired_default_message'),
                'start_date' => $this->formatDate($deadline->start_date),
                'end_date' => $this->formatDate($deadline->end_date),
            ];
        }

        return [
            'status' => 'open',
            'message' => __('app.form_currently_available'),
            'start_date' => $this->formatDate($deadline->start_date),
            'end_date' => $this->formatDate($deadline->end_date),
        ];
    }

    public function getContacts(): array
    {
        return [
            [
                'name' => __('app.admissions_office'),
                'short_name' => __('app.admissions_office_short'),
                'position' => __('app.admissions_support_position'),
                'phone' => '+855 12 345 678',
                'email' => 'admission@uhs.edu.kh',
                'telegram' => '@uhs_admission',
            ],
        ];
    }

    protected function getTranslatedFormName(CustomForm $form): string
    {
        $locale = app()->getLocale();

        /*
        |--------------------------------------------------------------------------
        | First: check translation key using the saved form slug
        |--------------------------------------------------------------------------
        */
        $slug = trim((string) ($form->slug ?? ''));

        if ($slug !== '') {
            $key = 'app.forms_nav.' . $slug;

            $translated = __($key);

            if ($translated !== $key) {
                return $translated;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Second: check separate language columns
        |--------------------------------------------------------------------------
        | Supports name_en, name_km and name_kh.
        |--------------------------------------------------------------------------
        */
        $languageColumns = match ($locale) {
            'km', 'kh' => ['name_km', 'name_kh', 'name_en'],
            default => ['name_en', 'name_km', 'name_kh'],
        };

        foreach ($languageColumns as $column) {
            $localizedName = $form->{$column} ?? null;

            if (filled($localizedName)) {
                return (string) $localizedName;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Third: decode the JSON stored in the name column
        |--------------------------------------------------------------------------
        | Example:
        | {"en":"Testing Form","km":"ទម្រង់តេស្ត","kh":"ទម្រង់តេស្ត"}
        |--------------------------------------------------------------------------
        */
        $name = $form->name;

        if (is_array($name)) {
            return $this->getNameFromTranslations($name);
        }

        if (is_object($name)) {
            return $this->getNameFromTranslations(
                json_decode(json_encode($name), true) ?: []
            );
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);

            if (
                json_last_error() === JSON_ERROR_NONE
                && is_array($decoded)
            ) {
                return $this->getNameFromTranslations($decoded);
            }

            return $name;
        }

        return __('app.this_form');
    }

    protected function getNameFromTranslations(array $translations): string
    {
        $locale = app()->getLocale();

        if (in_array($locale, ['km', 'kh'], true)) {
            return (string) (
                $translations['km']
                ?? $translations['kh']
                ?? $translations['en']
                ?? __('app.this_form')
            );
        }

        return (string) (
            $translations['en']
            ?? $translations['km']
            ?? $translations['kh']
            ?? __('app.this_form')
        );
    }

    protected function formatDate(mixed $date): ?string
    {
        if (! $date) {
            return null;
        }

        $format = app()->getLocale() === 'km'
            ? 'd/m/Y'
            : 'd M Y';

        return Carbon::parse($date)->format($format);
    }
}
