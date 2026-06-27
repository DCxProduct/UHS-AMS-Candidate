<?php

namespace App\Filament\Admin\Resources\GeoLocations\Schemas;

use App\Models\GeoLocation;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GeoLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('geo_locations.form.section_title'))
                    ->description(__('geo_locations.form.section_description'))
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('geo_locations.form.name_en'))
                            ->maxLength(255)
                            ->placeholder(__('geo_locations.form.name_en_placeholder'))
                            ->required(),

                        TextInput::make('name_kh')
                            ->label(__('geo_locations.form.name_kh'))
                            ->maxLength(255)
                            ->placeholder(__('geo_locations.form.name_kh_placeholder')),

                        TextInput::make('code')
                            ->label(__('geo_locations.form.code'))
                            ->maxLength(100)
                            ->placeholder(__('geo_locations.form.code_placeholder'))
                            ->unique(ignoreRecord: true),

                        Select::make('type')
                            ->label(__('geo_locations.form.type'))
                            ->options([
                                'province' => __('geo_locations.types.province'),
                                'district' => __('geo_locations.types.district'),
                                'commune' => __('geo_locations.types.commune'),
                                'village' => __('geo_locations.types.village'),
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (callable $set): void {
                                $set('parent_id', null);
                            }),

                        Select::make('parent_id')
                            ->label(__('geo_locations.form.parent_location'))
                            ->placeholder(__('geo_locations.form.parent_location_placeholder'))
                            ->options(fn (callable $get): array => self::parentOptions($get('type')))
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled(fn (callable $get): bool => blank($get('type')) || $get('type') === 'province')
                            ->helperText(__('geo_locations.form.parent_helper')),

                        Toggle::make('is_active')
                            ->label(__('geo_locations.form.active'))
                            ->default(true)
                            ->required(),

                        KeyValue::make('metadata')
                            ->label(__('geo_locations.form.metadata'))
                            ->keyLabel(__('geo_locations.form.key'))
                            ->valueLabel(__('geo_locations.form.value'))
                            ->addActionLabel(__('geo_locations.form.add_metadata'))
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    private static function parentOptions(?string $type): array
    {
        $parentType = match ($type) {
            'district' => 'province',
            'commune' => 'district',
            'village' => 'commune',
            default => null,
        };

        if (! $parentType) {
            return [];
        }

        return GeoLocation::query()
            ->where('type', $parentType)
            ->where('is_active', true)
            ->orderBy('name_en')
            ->orderBy('name_kh')
            ->get(['id', 'name_en', 'name_kh', 'code'])
            ->mapWithKeys(function (GeoLocation $location): array {
                $label = $location->name_en
                    ?: $location->name_kh
                    ?: $location->code
                    ?: __('geo_locations.form.no_name');

                return [
                    $location->id => $label,
                ];
            })
            ->toArray();
    }
}
