<?php

namespace App\Filament\Admin\Resources\Majors\Pages;

use App\Filament\Admin\Resources\Majors\MajorResource;
use App\Models\Major;
use App\Support\ConfigurationKey;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateMajor extends CreateRecord
{
    protected static string $resource = MajorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $label = filled($data['name_en'] ?? null) ? $data['name_en'] : $data['abbreviation'];
        $data['key'] = ConfigurationKey::generate(Major::class, (string) $label, 'major', maxLength: 100);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return MajorResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
