<?php

namespace App\Filament\Student\Pages;

use App\Models\ClosingDate;
use BackedEnum;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class ContactUs extends Page
{
    protected string $view = 'filament.student.pages.contact-us';

    protected static ?string $slug = 'contact-us';

    protected static bool $shouldRegisterNavigation = false;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhone;

    public function getTitle(): string | Htmlable
    {
        return $this->getFormTitle();
    }

    public function getHeading(): string | Htmlable
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
        $slug = (string) ($form->slug ?? Str::slug((string) $form->name));

        $key = 'app.forms_nav.' . $slug;

        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        $locale = app()->getLocale();

        $localizedName = $form->{'name_' . $locale} ?? null;

        if (filled($localizedName)) {
            return (string) $localizedName;
        }

        return (string) $form->name;
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
