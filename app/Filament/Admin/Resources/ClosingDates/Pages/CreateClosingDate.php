<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Models\ClosingDate;
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

    /*
    |--------------------------------------------------------------------------
    | Save a new expired record as Closed
    |--------------------------------------------------------------------------
    */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (
            ! empty($data['end_date'])
            && now()->startOfDay()->gt(
                \Carbon\Carbon::parse($data['end_date'])->startOfDay()
            )
        ) {
            $data['status'] = ClosingDate::STATUS_CLOSED;
        }

        return $data;
    }
}
