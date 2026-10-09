<?php

namespace App\Filament\Admin\Resources\SystemUsers\Pages;

use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use App\Support\SystemUsername;
use App\Support\UserTypeOptions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateSystemUser extends CreateRecord
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $candidateType = UserTypeOptions::resolveSystemRole($data['candidate_type'] ?? null);

        $data['roles'] = UserTypeOptions::assignableSystemRoles($candidateType);

        unset($data['role_ids']);
        unset($data['candidate_type']);

        $data['email'] = blank($data['email'] ?? null)
            ? null
            : trim((string) $data['email']);

        $data['phone'] = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        // Made automatically (from the email, or the phone); the name follows it as before.
        $data['username'] = SystemUsername::generate($data['email'], $data['phone']);
        $data['name'] = blank($data['name'] ?? null)
            ? $data['username']
            : trim((string) $data['name']);

        $data['permissions'] = null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['email_verified_at'] = $data['email_verified_at'] ?? now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncLoginUser();
    }
}
