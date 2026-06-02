<?php

namespace App\Filament\Admin\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class Forms extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'forms';

    protected string $view = 'filament.admin.pages.forms';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'ប្រភេទតម្រង់បញ្ជី';
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
                Section::make('សូមជ្រើសរើសប្រភេទបញ្ជី')
                    ->schema([
                        Select::make('form_type')
                            ->label('ប្រភេទបញ្ជី')
                            ->placeholder('សូមជ្រើសរើសបញ្ជី')
                            ->options([
                                'document_condition' => 'លិខិតយល់ព្រមប្រគល់ឯកសារ',
                                'new_student_registration' => 'ពាក្យសុំចុះឈ្មោះចូលរៀននិសិ្សតថ្មី',
                                'des_candidate_application' => 'ពាក្យសុំជ្រើសរើសជាបេក្ខជន DES',
                            ])
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->required(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function getSelectedFormView(): ?string
    {
        return match (data_get($this->data, 'form_type')) {
            'document_condition' => 'filament.forms.document-condition',
            'new_student_registration' => 'filament.forms.new-student-registration',
            'des_candidate_application' => 'filament.forms.des-candidate-application',
            default => null,
        };
    }

    public function getSelectedFormTitle(): ?string
    {
        return match (data_get($this->data, 'form_type')) {
            'document_condition' => 'លិខិតយល់ព្រមប្រគល់ឯកសារ',
            'new_student_registration' => 'ពាក្យសុំចុះឈ្មោះចូលរៀននិសិ្សតថ្មី',
            'des_candidate_application' => 'ពាក្យសុំជ្រើសរើសជាបេក្ខជន DES',
            default => null,
        };
    }
}
