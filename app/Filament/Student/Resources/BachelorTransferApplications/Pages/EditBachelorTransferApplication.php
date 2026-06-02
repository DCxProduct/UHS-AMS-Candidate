<?php

namespace App\Filament\Student\Resources\BachelorTransferApplications\Pages;

use App\Filament\Student\Resources\BachelorTransferApplications\BachelorTransferApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBachelorTransferApplication extends EditRecord
{
    protected static string $resource = BachelorTransferApplicationResource::class;

    public function getTitle(): string
    {
        return __('bachelor_transfer_applications.pages.edit_title');
    }

    public function getHeading(): string
    {
        return __('bachelor_transfer_applications.pages.edit_heading');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label(__('bachelor_transfer_applications.actions.delete'))
                ->modalHeading(__('bachelor_transfer_applications.modal.delete_heading'))
                ->modalDescription(__('bachelor_transfer_applications.modal.delete_description'))
                ->modalSubmitActionLabel(__('bachelor_transfer_applications.modal.delete_submit')),

            ForceDeleteAction::make()
                ->label(__('bachelor_transfer_applications.actions.force_delete'))
                ->modalHeading(__('bachelor_transfer_applications.modal.force_delete_heading'))
                ->modalDescription(__('bachelor_transfer_applications.modal.force_delete_description'))
                ->modalSubmitActionLabel(__('bachelor_transfer_applications.modal.force_delete_submit')),

            RestoreAction::make()
                ->label(__('bachelor_transfer_applications.actions.restore'))
                ->modalHeading(__('bachelor_transfer_applications.modal.restore_heading'))
                ->modalDescription(__('bachelor_transfer_applications.modal.restore_description'))
                ->modalSubmitActionLabel(__('bachelor_transfer_applications.modal.restore_submit')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return BachelorTransferApplicationResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('bachelor_transfer_applications.notifications.updated');
    }
}
