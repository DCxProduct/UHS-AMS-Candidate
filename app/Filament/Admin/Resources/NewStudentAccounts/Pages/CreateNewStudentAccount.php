<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Pages;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewStudentAccount extends CreateRecord
{
    protected static string $resource = NewStudentAccountResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
