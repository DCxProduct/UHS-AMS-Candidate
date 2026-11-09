<?php

namespace App\Filament\Admin\Resources\DegreeLevels\Pages;

use App\Filament\Admin\Resources\DegreeLevels\DegreeLevelResource;
use App\Models\DegreeLevel;
use App\Support\ConfigurationKey;
use Filament\Resources\Pages\CreateRecord;

class CreateDegreeLevel extends CreateRecord
{
    protected static string $resource = DegreeLevelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = ConfigurationKey::generate(DegreeLevel::class, (string) $data['label_en'], 'degree_level');

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
