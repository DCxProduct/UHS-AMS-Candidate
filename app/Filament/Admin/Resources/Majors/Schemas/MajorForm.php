<?php

namespace App\Filament\Admin\Resources\Majors\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MajorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('majors.form.section_title'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('key')
                        ->label(__('majors.fields.key'))
                        ->placeholder(__('majors.fields.key_placeholder'))
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) => $set('key', Str::lower((string) $state)))
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn ($state): string => Str::of((string) $state)
                            ->trim()
                            ->lower()
                            ->replaceMatches('/[^a-z0-9_-]+/', '_')
                            ->replaceMatches('/_+/', '_')
                            ->trim('_')
                            ->toString()),
                    TextInput::make('abbreviation')
                        ->label(__('majors.fields.abbreviation'))
                        ->placeholder(__('majors.fields.abbreviation_placeholder'))
                        ->required()
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn ($state): string => strtoupper(trim((string) $state))),
                    TextInput::make('name_en')
                        ->label(__('majors.fields.name_en'))
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('name_kh')
                        ->label(__('majors.fields.name_kh'))
                        ->maxLength(255),
                    Toggle::make('is_active')
                        ->label(__('majors.fields.is_active'))
                        ->default(true)
                        ->required(),
                ]),
        ]);
    }
}
