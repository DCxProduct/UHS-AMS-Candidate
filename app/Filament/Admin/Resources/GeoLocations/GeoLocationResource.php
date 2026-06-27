<?php

namespace App\Filament\Admin\Resources\GeoLocations;

use App\Filament\Admin\Resources\GeoLocations\Pages\CreateGeoLocation;
use App\Filament\Admin\Resources\GeoLocations\Pages\EditGeoLocation;
use App\Filament\Admin\Resources\GeoLocations\Pages\ListGeoLocations;
use App\Filament\Admin\Resources\GeoLocations\Schemas\GeoLocationForm;
use App\Filament\Admin\Resources\GeoLocations\Tables\GeoLocationsTable;
use App\Models\GeoLocation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GeoLocationResource extends Resource
{
    protected static ?string $model = GeoLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->registration_type !== 'student';
    }

    public static function getNavigationLabel(): string
    {
        return __('geo_locations.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('geo_locations.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('geo_locations.resource_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('geo_locations.resource_plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return GeoLocationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GeoLocationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGeoLocations::route('/'),
            'create' => CreateGeoLocation::route('/create'),
            'edit' => EditGeoLocation::route('/{record}/edit'),
        ];
    }
}
