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

    /** @var array<int, int|string> */
    protected array $selectedMajorIds = [];

    /** @var array<int, int|string> */
    protected array $createdRecordIds = [];

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
        $this->selectedMajorIds = collect(Arr::wrap($data['major_ids'] ?? []))
            ->filter(fn ($id): bool => filled($id))
            ->unique()
            ->values()
            ->all();
        unset($data['major_ids']);

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
            $record = parent::handleRecordCreation($data);
            $this->createdRecordIds = [$record->getKey()];

            return $record;
        }

        $firstRecord = null;

        foreach ($types as $type) {
            $record = parent::handleRecordCreation(
                array_merge($data, ['type' => $type])
            );
            $this->createdRecordIds[] = $record->getKey();

            $firstRecord ??= $record;
        }

        return $firstRecord;
    }

    protected function afterCreate(): void
    {
        if ($this->selectedMajorIds === []) {
            return;
        }

        foreach ($this->createdRecordIds as $recordId) {
            ClosingDate::query()->find($recordId)?->majors()->sync($this->selectedMajorIds);
        }
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
