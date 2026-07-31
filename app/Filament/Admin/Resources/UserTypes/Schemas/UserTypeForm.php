<?php

namespace App\Filament\Admin\Resources\UserTypes\Schemas;

use App\Support\UserTypeOptions;
use Filament\Forms\Components\Hidden;
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
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('user_types.fields.key'))
                                    ->placeholder(__('user_types.placeholders.key'))
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
                                Hidden::make('color')
                                    ->default('blue')
                                    ->afterStateHydrated(fn ($state, callable $set): mixed => $set('color', 'blue'))
                                    ->dehydrateStateUsing(fn (): string => 'blue'),

                                Toggle::make('is_active')
                                    ->label(__('user_types.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }
}
