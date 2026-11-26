<?php

namespace App\Filament\Admin\Resources\GuideBooks\Pages;

use App\Filament\Admin\Resources\GuideBooks\GuideBookResource;
use App\Models\GuideBook;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class EditGuideBook extends EditRecord
{
    protected static string $resource = GuideBookResource::class;

    #[Locked]
    public bool $guideResetPending = false;

    public function getTitle(): string
    {
        return __('guide_books.singular');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetGuideBook')
                ->label(__('guide_books.reset'))
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->authorize('reset', $this->getRecord())
                ->action(function (): void {
                    Gate::authorize('reset', $this->getRecord());
                    $this->guideResetPending = true;
                    $this->form->fill(GuideBook::defaultData());
                    Notification::make()->title(__('guide_books.reset_pending'))->info()->send();
                }),
        ];
    }

    protected function beforeValidate(): void
    {
        if ($this->guideResetPending) {
            Gate::authorize('reset', $this->getRecord());
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->guideResetPending) {
            return $data;
        }

        $data['file_path'] ??= '';
        if (blank($data['file_path'])) {
            $data['file_name'] = null;
        }

        return [...GuideBook::defaultData(), ...$data];
    }

    protected function afterSave(): void
    {
        $this->guideResetPending = false;
    }
}
