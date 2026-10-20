<?php

namespace App\Filament\Admin\Resources\Majors\Pages;

use App\Filament\Admin\Resources\Majors\MajorResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateMajor extends CreateRecord
{
    protected static string $resource = MajorResource::class;

    protected function getRedirectUrl(): string
    {
        return MajorResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}
