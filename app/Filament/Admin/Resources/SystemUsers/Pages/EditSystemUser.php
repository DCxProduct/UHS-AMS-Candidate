<?php

namespace App\Filament\Admin\Resources\SystemUsers\Pages;

use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditSystemUser extends EditRecord
{
    protected static string $resource = SystemUserResource::class;

    protected function getRedirectUrl(): string
    {
        return SystemUserResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label(__('system_users.actions.delete')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Hide role in form, but always keep Student role
        $data['roles'] = ['Student'];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Force role to Student only
        $data['roles'] = ['Student'];

        unset($data['role_ids']);

        $data['name'] = blank($data['name'] ?? null)
            ? trim((string) ($data['username'] ?? 'Student'))
            : trim((string) $data['name']);

        $data['username'] = blank($data['username'] ?? null)
            ? null
            : trim((string) $data['username']);

        $data['email'] = blank($data['email'] ?? null)
            ? null
            : trim((string) $data['email']);

        $data['phone'] = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        $data['permissions'] = null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncLoginUser();
    }
}
