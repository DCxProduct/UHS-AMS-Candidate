<?php

namespace App\Filament\Admin\Resources\RoleTypes\Pages;

use App\Filament\Admin\Resources\RoleTypes\RoleTypeResource;
use App\Models\RoleType;
use App\Support\ConfigurationKey;
use Filament\Resources\Pages\CreateRecord;

class CreateRoleType extends CreateRecord
{
    protected static string $resource = RoleTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['key'] = ConfigurationKey::generate(
            RoleType::class,
            (string) $data['label_en'],
            'role_type',
            reserved: ['system_admin'],
        );

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
