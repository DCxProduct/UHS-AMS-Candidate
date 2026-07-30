<?php

namespace App\Filament\Admin\Resources\UserTypes\Pages;

use App\Filament\Admin\Resources\UserTypes\UserTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserTypes extends ListRecords
{
    protected static string $resource = UserTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('user_types.actions.create_user_types')),
        ];
    }
}
