<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Schemas;

use App\Support\CandidateTypeOptions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CandidateTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('candidate_types.form.section_title'))
                    ->description(__('candidate_types.form.section_description'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('candidate_types.fields.key'))
                                    ->placeholder(__('candidate_types.placeholders.key'))
                                    ->helperText(__('candidate_types.form.name_helper'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(column: 'key', ignoreRecord: true)
                                    ->dehydrateStateUsing(fn ($state): ?string => blank($state)
                                        ? null
                                        : Str::of((string) $state)
                                            ->trim()
                                            ->lower()
                                            ->replaceMatches('/[^a-z0-9_-]+/', '_')
                                            ->replaceMatches('/_+/', '_')
                                            ->trim('_')
                                            ->toString()
                                    )
                                    ->rules([
                                        'required',
                                        'string',
                                        'regex:/^[a-z0-9_-]+$/',
                                        function (): \Closure {
                                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                                $name = Str::lower(trim((string) $value));

                                                if ($name === CandidateTypeOptions::BASE_ROLE) {
                                                    $fail(__('candidate_types.validation.base_role_reserved'));
                                                }
                                            };
                                        },
                                    ])
                                    ->validationMessages([
                                        'required' => __('candidate_types.validation.name_required'),
                                        'unique' => __('candidate_types.validation.name_unique'),
                                        'regex' => __('candidate_types.validation.key_format'),
                                    ]),

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
                            ]),

                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Select::make('color')
                                    ->label(__('candidate_types.fields.color'))
                                    ->options(CandidateTypeOptions::colorOptions())
                                    ->default('blue')
                                    ->required()
                                    ->native(false)
                                    ->afterStateHydrated(function ($state, callable $set): void {
                                        $set('color', CandidateTypeOptions::canonicalColor($state));
                                    })
                                    ->dehydrateStateUsing(fn ($state): string => CandidateTypeOptions::canonicalColor($state))
                                    ->validationMessages([
                                        'required' => __('candidate_types.validation.color_required'),
                                    ]),

                                Toggle::make('is_active')
                                    ->label(__('candidate_types.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),

                        Placeholder::make('preview')
                            ->label(__('candidate_types.fields.preview'))
                            ->content(function (callable $get): string {
                                $labelEn = trim((string) ($get('label_en') ?? ''));
                                $labelKh = trim((string) ($get('label_kh') ?? ''));
                                $color = CandidateTypeOptions::canonicalColor($get('color'));

                                $preview = $labelEn !== '' ? $labelEn : __('candidate_types.preview.empty');

                                if ($labelKh !== '') {
                                    $preview .= ' / ' . $labelKh;
                                }

                                return '[' . Str::upper($color) . '] ' . $preview;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
