<?php

namespace App\Filament\Admin\Resources\CandidateSubmitPopupSettings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])->schema([
                            TextInput::make('title_en')
                                ->label(__('candidate_submit_popup_settings.fields.title_en'))
                                ->required(),

                            TextInput::make('title_km')
                                ->label(__('candidate_submit_popup_settings.fields.title_km'))
                                ->required(),

                            Textarea::make('description_en')
                                ->label(__('candidate_submit_popup_settings.fields.description_en'))
                                ->rows(4)
                                ->required(),

                            Textarea::make('description_km')
                                ->label(__('candidate_submit_popup_settings.fields.description_km'))
                                ->rows(4)
                                ->required(),

                            TextInput::make('confirm_label_en')
                                ->label(__('candidate_submit_popup_settings.fields.confirm_label_en'))
                                ->required(),

                            TextInput::make('confirm_label_km')
                                ->label(__('candidate_submit_popup_settings.fields.confirm_label_km'))
                                ->required(),

                            TextInput::make('cancel_label_en')
                                ->label(__('candidate_submit_popup_settings.fields.cancel_label_en'))
                                ->required(),

                            TextInput::make('cancel_label_km')
                                ->label(__('candidate_submit_popup_settings.fields.cancel_label_km'))
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
