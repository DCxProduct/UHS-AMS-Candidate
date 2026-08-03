<?php

namespace App\Filament\Admin\Resources\SystemUsers\Pages;

use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use App\Support\UserTypeOptions;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

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
                ->label(__('system_users.actions.delete'))
                ->action(fn () => $this->record->forceDelete()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $roles = $data['roles'] ?? [];

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        $candidateType = collect(is_array($roles) ? $roles : [])
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => trim((string) $role))
            ->first(fn (string $role): bool => ! in_array(strtolower($role), ['student', 'candidate'], true));

        $data['candidate_type'] = $candidateType
            ? UserTypeOptions::resolveSystemRole($candidateType)
            : UserTypeOptions::resolveSystemRole(null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $candidateType = UserTypeOptions::resolveSystemRole($data['candidate_type'] ?? null);

        $data['roles'] = UserTypeOptions::assignableSystemRoles($candidateType);

        unset($data['role_ids']);
        unset($data['candidate_type']);

        $data['name'] = blank($data['name'] ?? null)
            ? trim((string) ($data['username'] ?? 'System User'))
            : trim((string) $data['name']);

        $data['username'] = blank($data['username'] ?? null)
            ? null
            : Str::lower(trim((string) $data['username']));

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
