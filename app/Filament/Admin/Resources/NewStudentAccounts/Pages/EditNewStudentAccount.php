<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Pages;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNewStudentAccount extends EditRecord
{
    protected static string $resource = NewStudentAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
