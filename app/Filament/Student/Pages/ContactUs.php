<?php

namespace App\Filament\Student\Pages;

use App\Models\ClosingDate;
use BackedEnum;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ContactUs extends Page
{
    protected string $view = 'filament.student.pages.contact-us';

    protected static ?string $slug = 'contact-us';

    protected static bool $shouldRegisterNavigation = false;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPhone;

    public function getTitle(): string | Htmlable
    {
        return $this->getForm()?->name ?? __('app.contact_us');
    }

    public function getHeading(): string | Htmlable
    {
        return $this->getForm()?->name ?? __('app.contact_us');
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

    public function getClosingDate(): ?ClosingDate
    {
        return ClosingDate::getDeadlineByCustomFormId($this->getFormId());
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

        if ($deadline->status === 'closed') {
            return [
                'status' => 'expired',
                'message' => __('app.expired_default_message'),
                'start_date' => $deadline->start_date?->format('d M Y'),
                'end_date' => $deadline->end_date?->format('d M Y'),
            ];
        }

        if ($deadline->status === 'not_open') {
            return [
                'status' => 'not_open',
                'message' => __('app.application_not_open_yet'),
                'start_date' => $deadline->start_date?->format('d M Y'),
                'end_date' => $deadline->end_date?->format('d M Y'),
            ];
        }

        return [
            'status' => 'open',
            'message' => __('app.form_currently_available'),
            'start_date' => $deadline->start_date?->format('d M Y'),
            'end_date' => $deadline->end_date?->format('d M Y'),
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
            [
                'name' => __('app.registrar_office'),
                'short_name' => __('app.registrar_office_short'),
                'position' => __('app.registrar_support_position'),
                'phone' => '+855 23 123 456',
                'email' => 'registrar@uhs.edu.kh',
                'telegram' => '@uhs_registrar',
            ],
        ];
    }
}
