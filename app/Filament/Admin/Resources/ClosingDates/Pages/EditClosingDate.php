<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Models\ClosingDate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditClosingDate extends EditRecord
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
    | Display expired records as Closed when opening the Edit page
    |--------------------------------------------------------------------------
    | This only changes the form state. Clicking Save changes will persist it.
    |--------------------------------------------------------------------------
    */
    protected function mutateFormDataBeforeFill(array $data): array
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

    /*
    |--------------------------------------------------------------------------
    | Save expired records as Closed
    |--------------------------------------------------------------------------
    */
    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
