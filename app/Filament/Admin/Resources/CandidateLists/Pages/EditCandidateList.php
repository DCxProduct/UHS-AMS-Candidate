<?php

namespace App\Filament\Admin\Resources\CandidateLists\Pages;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Models\User;
use App\Support\CandidateLatinName;
use App\Support\UserTypeOptions;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditCandidateList extends EditRecord
{
    protected static string $resource = CandidateListResource::class;

    protected ?string $selectedCandidateType = null;

    protected function getRedirectUrl(): string
    {
        return CandidateListResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label(__('candidate_lists.actions.delete'))
                ->action(fn () => $this->record->forceDelete()),
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

        // The username is not edited here: it links this candidate to their login account.
        $data['name'] = CandidateLatinName::join($data['first_name_en'] ?? null, $data['last_name_en'] ?? null) ?: 'Candidate';
        unset($data['first_name_en'], $data['last_name_en'], $data['username']);

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

        // The login account keeps the Latin name too, like registration.
        if (filled($this->record->username)) {
            User::query()->where('username', $this->record->username)->update(['name_latin' => $this->record->name]);
        }
    }
}
