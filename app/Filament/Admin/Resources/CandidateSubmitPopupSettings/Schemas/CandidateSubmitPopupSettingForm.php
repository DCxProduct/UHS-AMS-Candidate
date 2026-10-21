<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Schemas;

use App\Models\CandidateSubmitPopupSetting;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidateSubmitPopupSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('candidate_submit_popup_settings.sections.popup_content'))
                    ->schema([
                        Select::make('custom_form_id')
                            ->label(__('candidate_submit_popup_settings.fields.form'))
                            ->options(fn (): array => CustomForm::query()
                                ->whereNotIn('id', CandidateSubmitPopupSetting::query()
                                    ->whereNotNull('custom_form_id')
                                    ->select('custom_form_id'))
                                ->orderBy('display_order')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (CustomForm $form): array => [
                                    $form->getKey() => $form->display_name ?: $form->name,
                                ])
                                ->all())
                            ->searchable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->unique(ignoreRecord: true)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])->schema([
                            TextInput::make('title_en')
                                ->label(__('candidate_submit_popup_settings.fields.title_en'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->title_en)
                                ->required(),

                            TextInput::make('title_km')
                                ->label(__('candidate_submit_popup_settings.fields.title_km'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->title_km)
                                ->required(),

                            Textarea::make('description_en')
                                ->label(__('candidate_submit_popup_settings.fields.description_en'))
                                ->rows(4)
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->description_en)
                                ->required(),

                            Textarea::make('description_km')
                                ->label(__('candidate_submit_popup_settings.fields.description_km'))
                                ->rows(4)
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->description_km)
                                ->required(),

                            TextInput::make('confirm_label_en')
                                ->label(__('candidate_submit_popup_settings.fields.confirm_label_en'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->confirm_label_en)
                                ->required(),

                            TextInput::make('confirm_label_km')
                                ->label(__('candidate_submit_popup_settings.fields.confirm_label_km'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->confirm_label_km)
                                ->required(),

                            TextInput::make('cancel_label_en')
                                ->label(__('candidate_submit_popup_settings.fields.cancel_label_en'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->cancel_label_en)
                                ->required(),

                            TextInput::make('cancel_label_km')
                                ->label(__('candidate_submit_popup_settings.fields.cancel_label_km'))
                                ->default(fn () => CandidateSubmitPopupSetting::singleton()->cancel_label_km)
                                ->required(),
                            Toggle::make('is_active')
                                ->label(__('candidate_submit_popup_settings.fields.active'))
                                ->default(true)
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
