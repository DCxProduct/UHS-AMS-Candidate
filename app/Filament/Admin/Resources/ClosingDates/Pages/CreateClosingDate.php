<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Models\ClosingDate;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateClosingDate extends CreateRecord
{
    protected static string $resource = ClosingDateResource::class;

    protected int $createdRecordsCount = 1;

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
    protected function handleRecordCreation(array $data): Model
    {
        $types = collect(Arr::wrap($data['type'] ?? null))
            ->map(fn ($type): string => trim((string) $type))
            ->filter(fn (string $type): bool => $type !== '')
            ->unique()
            ->values();

        $this->createdRecordsCount = max($types->count(), 1);

        if ($types->count() <= 1) {
            $data['type'] = $types->first();

            return parent::handleRecordCreation($data);
        }

        $firstRecord = null;

        foreach ($types as $type) {
            $record = parent::handleRecordCreation(
                array_merge($data, ['type' => $type])
            );

            $firstRecord ??= $record;
        }

        return $firstRecord;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        if ($this->createdRecordsCount > 1) {
            return __('closing_dates.created_multiple', [
                'count' => $this->createdRecordsCount,
            ]);
        }

        return parent::getCreatedNotificationTitle();
    }
}
