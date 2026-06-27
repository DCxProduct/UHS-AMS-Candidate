<?php

namespace App\Filament\Admin\Resources\GeoLocations\Pages;

use App\Filament\Admin\Resources\GeoLocations\GeoLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGeoLocation extends CreateRecord
{
    protected static string $resource = GeoLocationResource::class;

    protected function getRedirectUrl(): string
    {
        return GeoLocationResource::getUrl('index');
    }
}
