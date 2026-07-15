<?php

namespace App\Filament\Admin\Resources\GeoLocations\Pages;

use App\Filament\Admin\Resources\GeoLocations\GeoLocationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeoLocations extends ListRecords
{
    protected static string $resource = GeoLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('geo_locations.actions.new'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
