<?php

namespace App\Filament\Admin\Resources\DegreeLevels\Pages;

use App\Filament\Admin\Resources\DegreeLevels\DegreeLevelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDegreeLevel extends CreateRecord
{
    protected static string $resource = DegreeLevelResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
