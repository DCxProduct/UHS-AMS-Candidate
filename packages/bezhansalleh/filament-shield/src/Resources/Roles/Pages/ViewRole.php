<?php

declare(strict_types=1);

namespace BezhanSalleh\FilamentShield\Resources\Roles\Pages;

use App\Support\AdminRolePermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function beforeFill(): void
    {
        if (AdminRolePermissions::isAdmin($this->record)) {
            AdminRolePermissions::sync($this->record, FilamentShield::getEntitiesPermissions() ?? []);
        }
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn (): bool => ! RoleResource::isAdminRole($this->record)),
        ];
    }
}
