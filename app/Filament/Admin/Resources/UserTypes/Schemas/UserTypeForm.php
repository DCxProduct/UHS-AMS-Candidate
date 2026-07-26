<?php

namespace App\Filament\Admin\Resources\UserTypes\Schemas;

use App\Support\UserTypeOptions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('user_types.form.section_title'))
                    ->description(__('user_types.form.section_description'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('user_types.fields.key'))
                                    ->placeholder(__('user_types.placeholders.key'))
                                    ->helperText(__('user_types.form.name_helper'))
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

                                                if ($name === UserTypeOptions::BASE_ROLE) {
                                                    $fail(__('user_types.validation.base_role_reserved'));
                                                }
                                            };
                                        },
                                    ])
                                    ->validationMessages([
                                        'required' => __('user_types.validation.name_required'),
                                        'unique' => __('user_types.validation.name_unique'),
                                        'regex' => __('user_types.validation.key_format'),
                                    ]),

                                TextInput::make('label_en')
                                    ->label(__('user_types.fields.label_en'))
                                    ->placeholder(__('user_types.placeholders.label_en'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('user_types.validation.label_en_required'),
                                    ]),

                                TextInput::make('label_kh')
                                    ->label(__('user_types.fields.label_kh'))
                                    ->placeholder(__('user_types.placeholders.label_kh'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('user_types.validation.label_kh_required'),
                                    ]),
                            ]),

                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Select::make('color')
                                    ->label(__('user_types.fields.color'))
                                    ->options(UserTypeOptions::colorOptions())
                                    ->default('blue')
                                    ->required()
                                    ->native(false)
                                    ->afterStateHydrated(function ($state, callable $set): void {
                                        $set('color', UserTypeOptions::canonicalColor($state));
                                    })
                                    ->dehydrateStateUsing(fn ($state): string => UserTypeOptions::canonicalColor($state))
                                    ->validationMessages([
                                        'required' => __('user_types.validation.color_required'),
                                    ]),

                                Toggle::make('is_active')
                                    ->label(__('user_types.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),

                        Placeholder::make('preview')
                            ->label(__('user_types.fields.preview'))
                            ->content(function (callable $get): string {
                                $labelEn = trim((string) ($get('label_en') ?? ''));
                                $labelKh = trim((string) ($get('label_kh') ?? ''));
                                $color = UserTypeOptions::canonicalColor($get('color'));

                                $preview = $labelEn !== '' ? $labelEn : __('user_types.preview.empty');

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
