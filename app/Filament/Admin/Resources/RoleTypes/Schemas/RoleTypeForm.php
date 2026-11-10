<?php

namespace App\Filament\Admin\Resources\RoleTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
