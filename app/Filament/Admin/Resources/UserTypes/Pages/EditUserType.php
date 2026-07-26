<?php

namespace App\Filament\Admin\Resources\UserTypes\Pages;

use App\Filament\Admin\Resources\UserTypes\UserTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditUserType extends EditRecord
{
    protected static string $resource = UserTypeResource::class;
}
