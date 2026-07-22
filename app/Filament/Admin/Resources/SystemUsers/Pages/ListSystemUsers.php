<?php

namespace App\Filament\Admin\Resources\SystemUsers\Pages;

use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListSystemUsers extends ListRecords
{
    protected static string $resource = SystemUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('system_users.actions.new')),
        ];
    }
}
