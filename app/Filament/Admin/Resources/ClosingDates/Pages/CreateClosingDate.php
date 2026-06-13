<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateClosingDate extends CreateRecord
{
    protected static string $resource = ClosingDateResource::class;

    protected function getRedirectUrl(): string
    {
        return ClosingDateResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }
}