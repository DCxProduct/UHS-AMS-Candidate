<?php

namespace App\Filament\Admin\Resources\CandidateLists\Pages;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Models\User;
use App\Support\CandidateLatinName;
use App\Support\UserTypeOptions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCandidateList extends CreateRecord
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $candidateType = UserTypeOptions::resolve($data['candidate_type'] ?? null);
        $this->selectedCandidateType = $candidateType;

        $data['roles'] = UserTypeOptions::assignableUserRoles($candidateType);

        unset($data['role_ids']);
        unset($data['candidate_type']);

        $data['name'] = CandidateLatinName::join($data['first_name_en'] ?? null, $data['last_name_en'] ?? null) ?: 'Candidate';
        unset($data['first_name_en'], $data['last_name_en']);

        $data['email'] = blank($data['email'] ?? null)
            ? null
            : trim((string) $data['email']);

        $data['phone'] = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        // Made automatically from the phone number, like registration.
        $data['username'] = CandidateLatinName::generateUsername($data['phone']);

        $data['permissions'] = null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['email_verified_at'] = $data['email_verified_at'] ?? now();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->selectedCandidateType) {
            $this->record->forceFill([
                'roles' => UserTypeOptions::assignableUserRoles($this->selectedCandidateType),
            ])->save();

            $this->record->refresh();
        }

        $this->record->syncLoginUser();
        $this->saveLatinNameOnLoginUser();
    }

    /**
     * The login account keeps the Latin name too, like registration.
     */
    protected function saveLatinNameOnLoginUser(): void
    {
        if (filled($this->record->username)) {
            User::query()->where('username', $this->record->username)->update(['name_latin' => $this->record->name]);
        }
    }
}
