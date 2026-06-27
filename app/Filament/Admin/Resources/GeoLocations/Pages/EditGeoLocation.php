<?php

namespace App\Filament\Admin\Resources\GeoLocations\Pages;

use App\Filament\Admin\Resources\GeoLocations\GeoLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeoLocation extends EditRecord
{
    protected static string $resource = GeoLocationResource::class;

    protected function getRedirectUrl(): string
    {
        return GeoLocationResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
