<?php

namespace App\Filament\Student\Pages;

use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class Forms extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'forms';

    protected string $view = 'filament.admin.pages.forms';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('student_forms.navigation_label');
    }

    public function getTitle(): string
    {
        return __('student_forms.title');
    }

    public function getHeading(): string
    {
        return __('student_forms.heading');
    }

    public function mount(): void
    {
        $this->form->fill([
            'form_type' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('student_forms.section.title'))
                    ->schema([
                        Select::make('form_type')
                            ->label(__('student_forms.fields.form_type'))
                            ->placeholder(__('student_forms.placeholders.form_type'))
                            ->options($this->getFormTypeOptions())
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getFormTypeOptions(): array
    {
        return [
            'document_condition' => __('student_forms.options.document_condition'),
            'new_student_registration' => __('student_forms.options.new_student_registration'),
            'des_candidate_application' => __('student_forms.options.des_candidate_application'),

            'bachelor_transfer_applications' => __('student_forms.options.bachelor_transfer_applications'),
            'master_science_national_exit_exam_applications' => __('student_forms.options.master_science_national_exit_exam_applications'),
            'national_entrance_exam_applications' => __('student_forms.options.national_entrance_exam_applications'),
            'national_exit_exam_applications' => __('student_forms.options.national_exit_exam_applications'),
            'old_student_registrations' => __('student_forms.options.old_student_registrations'),
        ];
    }

    protected function getFormViewMap(): array
    {
        return [
            'document_condition' => 'filament.forms.document-condition',
            'new_student_registration' => 'filament.forms.new-student-registration',
            'des_candidate_application' => 'filament.forms.des-candidate-application',

            'bachelor_transfer_applications' => 'filament.student.bachelor-transfer-applications.pdf-form',
            'master_science_national_exit_exam_applications' => 'filament.student.master-science-national-exit-exam-applications.pdf-form',
            'national_entrance_exam_applications' => 'filament.student.national-entrance-exam-applications.pdf-form',
            'national_exit_exam_applications' => 'filament.student.national-exit-exam-applications.pdf-form',
            'old_student_registrations' => 'filament.student.old-student-registrations.pdf-form',
        ];
    }

    public function getSelectedFormView(): ?string
    {
        $selected = data_get($this->data, 'form_type');

        $view = $this->getFormViewMap()[$selected] ?? null;

        if (! $view) {
            return null;
        }

        return view()->exists($view) ? $view : null;
    }

    public function getSelectedFormTitle(): ?string
    {
        $selected = data_get($this->data, 'form_type');

        return $this->getFormTypeOptions()[$selected] ?? null;
    }
}
