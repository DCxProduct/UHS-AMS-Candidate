<?php

namespace App\Filament\Admin\Resources\Majors\Pages;

use App\Filament\Admin\Resources\Majors\MajorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditMajor extends EditRecord
{
    protected static string $resource = MajorResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
