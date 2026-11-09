<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Schemas;

use App\Support\UserTypeOptions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CandidateTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('candidate_types.form.section_title'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('label_en')
                                    ->label(__('candidate_types.fields.label_en'))
                                    ->placeholder(__('candidate_types.placeholders.label_en'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('candidate_types.validation.label_en_required'),
                                    ]),

                                TextInput::make('label_kh')
                                    ->label(__('candidate_types.fields.label_kh'))
                                    ->placeholder(__('candidate_types.placeholders.label_kh'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('candidate_types.validation.label_kh_required'),
                                    ]),

                                Select::make('group_name')
                                    ->label(__('candidate_types.fields.group_name'))
                                    ->placeholder(__('candidate_types.placeholders.group_name'))
                                    ->options(fn (): array => UserTypeOptions::groupOptions())
                                    ->searchable()
                                    ->preload()
                                    ->native(false),
                            ]),

                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Hidden::make('color')
                                    ->default('blue')
                                    ->afterStateHydrated(fn ($state, callable $set): mixed => $set('color', 'blue'))
                                    ->dehydrateStateUsing(fn (): string => 'blue'),

                                Toggle::make('is_active')
                                    ->label(__('candidate_types.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
