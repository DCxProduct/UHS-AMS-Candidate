<?php

namespace App\Filament\Admin\Resources\RoleTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RoleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('role_types.form.section_title'))
                    ->schema([
                        Grid::make([
                            'default' => 2,
                            'lg' => 2,
                        ])->schema([
                            TextInput::make('key')
                                ->label(__('role_types.fields.key'))
                                ->placeholder(__('role_types.placeholders.key'))
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
                                        ->toString()),

                            TextInput::make('label_en')
                                ->label(__('role_types.fields.label_en'))
                                ->placeholder(__('role_types.placeholders.label_en'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('label_kh')
                                ->label(__('role_types.fields.label_kh'))
                                ->placeholder(__('role_types.placeholders.label_kh'))
                                ->maxLength(255),

                            Toggle::make('is_active')
                                ->label(__('role_types.fields.is_active'))
                                ->default(true)
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
