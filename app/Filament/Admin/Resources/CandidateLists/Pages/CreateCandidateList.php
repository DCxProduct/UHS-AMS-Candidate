<?php

namespace App\Filament\Admin\Resources\CandidateLists\Pages;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Support\UserTypeOptions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

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
    }
}
