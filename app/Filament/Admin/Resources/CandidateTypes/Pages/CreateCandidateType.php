<?php

namespace App\Filament\Admin\Resources\CandidateTypes\Pages;

use App\Filament\Admin\Resources\CandidateTypes\CandidateTypeResource;
use App\Models\Role;
use App\Models\UserType;
use App\Support\ConfigurationKey;
use App\Support\StaffRoles;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateCandidateType extends CreateRecord
{
    protected static string $resource = CandidateTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = ConfigurationKey::generate(
            UserType::class,
            (string) $data['label_en'],
            'candidate_type',
            reserved: [
                'candidate', 'student', 'super_admin', 'staff',
                ...StaffRoles::STAFF,
                ...Role::query()->where('guard_name', 'web')->pluck('name')->all(),
            ],
        );

        return $data;
    }

    public function getTitle(): string|Htmlable
    {
        return __('candidate_types.actions.new');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
