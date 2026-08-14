<?php

namespace App\Filament\Admin\Resources\RoleTypes\Pages;

use App\Filament\Admin\Resources\RoleTypes\RoleTypeResource;
use App\Models\RoleType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoleTypes extends ListRecords
{
    protected static string $resource = RoleTypeResource::class;

    protected function getHeaderActions(): array
    {
        RoleType::options();

        return [
            CreateAction::make(),
        ];
    }
}
