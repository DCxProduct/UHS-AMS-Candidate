<?php

namespace App\Filament\Admin\Resources\UserTypes\Pages;

use App\Filament\Admin\Resources\UserTypes\UserTypeResource;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Resources\Pages\CreateRecord;

class CreateUserType extends CreateRecord
{
    protected static string $resource = UserTypeResource::class;

    public function getTitle(): string | Htmlable
    {
        return __('user_types.actions.new');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
