<?php

namespace App\Filament\Admin\Resources\PaymentTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PaymentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('payment_types.form.section_title'))
                    ->schema([
                        Grid::make([
                            'default' => 2,
                            'lg' => 2,
                        ])
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('payment_types.fields.key'))
                                    ->placeholder(__('payment_types.placeholders.key'))
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
                                    ])
                                    ->validationMessages([
                                        'required' => __('payment_types.validation.key_required'),
                                        'unique' => __('payment_types.validation.key_unique'),
                                        'regex' => __('payment_types.validation.key_format'),
                                    ]),

                                TextInput::make('name_en')
                                    ->label(__('payment_types.fields.name_en'))
                                    ->placeholder(__('payment_types.placeholders.name_en'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('payment_types.validation.name_en_required'),
                                    ]),

                                TextInput::make('name_kh')
                                    ->label(__('payment_types.fields.name_kh'))
                                    ->placeholder(__('payment_types.placeholders.name_kh'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('payment_types.validation.name_kh_required'),
                                    ]),

                                Toggle::make('is_active')
                                    ->label(__('payment_types.fields.is_active'))
                                    ->default(true)
                                    ->required(),
                                ]),
                            ]),
            ]);
    }
}
