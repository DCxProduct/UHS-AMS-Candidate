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

    /** @var array<int, int|string> */
    protected array $selectedMajorIds = [];

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
        $data['major_ids'] = $this->record->majors()->pluck('majors.id')->all();

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
        $this->selectedMajorIds = collect($data['major_ids'] ?? [])
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

    protected function afterSave(): void
    {
        $this->record->majors()->sync($this->selectedMajorIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->action(fn () => $this->record->forceDelete()),
        ];
    }
}
