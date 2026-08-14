<?php

namespace App\Filament\Admin\Resources\RoleTypes\Pages;

use App\Filament\Admin\Resources\RoleTypes\RoleTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditRoleType extends EditRecord
{
    protected static string $resource = RoleTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
