<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Support\UserTypeOptions;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $selectedCandidateType = null;

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label(__('users.actions.delete')),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $storedRoles = $this->record->roles;

        if (is_string($storedRoles)) {
            $decoded = json_decode($storedRoles, true);
            $storedRoles = is_array($decoded) ? $decoded : [$storedRoles];
        }

        $candidateType = collect(is_array($storedRoles) ? $storedRoles : [])
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => trim((string) $role))
            ->first(fn (string $role): bool => UserTypeOptions::isCandidateManagedRole($role));

        $data['candidate_type'] = UserTypeOptions::resolve($candidateType ?? UserTypeOptions::BASE_ROLE);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $candidateType = UserTypeOptions::resolve($data['candidate_type'] ?? null);
        $this->selectedCandidateType = $candidateType;

        $data['roles'] = UserTypeOptions::assignableUserRoles($candidateType);

        unset($data['role_ids']);
        unset($data['candidate_type']);

        $data['name'] = blank($data['name'] ?? null)
            ? trim((string) ($data['username'] ?? 'Candidate'))
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
        if ($this->selectedCandidateType) {
            $this->record->forceFill([
                'roles' => UserTypeOptions::assignableUserRoles($this->selectedCandidateType),
            ])->save();

            $this->record->refresh();
        }

        $this->record->syncLoginUser();
    }
}
