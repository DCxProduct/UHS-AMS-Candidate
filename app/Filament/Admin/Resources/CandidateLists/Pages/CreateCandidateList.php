<?php

namespace App\Filament\Admin\Resources\CandidateLists\Pages;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Support\CandidateLatinName;
use App\Support\UserTypeOptions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateCandidateList extends CreateRecord
{
    protected static string $resource = CandidateListResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected ?string $selectedCandidateType = null;

    protected function getRedirectUrl(): string
    {
        return CandidateListResource::getUrl('index');
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $candidateType = UserTypeOptions::resolve($data['candidate_type'] ?? null);
        $this->selectedCandidateType = $candidateType;

        $data['candidate_roles'] = UserTypeOptions::assignableUserRoles($candidateType);

        unset($data['role_ids'], $data['roles'], $data['permissions']);
        unset($data['candidate_type']);

        $data['name'] = CandidateLatinName::join($data['first_name_en'] ?? null, $data['last_name_en'] ?? null) ?: 'Candidate';
        $data['name_latin'] = $data['name'];
        unset($data['first_name_en'], $data['last_name_en']);

        $data['email'] = blank($data['email'] ?? null)
            ? null
            : trim((string) $data['email']);

        $data['phone'] = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        $data['username'] = CandidateLatinName::generateUsername($data['phone']);

        $data['registration_type'] = 'student';
        $data['date_of_birth'] = '2000-01-01';
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['email_verified_at'] = $data['email_verified_at'] ?? now();

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->selectedCandidateType !== null) {
            $this->record->syncRoles(UserTypeOptions::assignableWebRoles($this->selectedCandidateType));
        }
    }
}
