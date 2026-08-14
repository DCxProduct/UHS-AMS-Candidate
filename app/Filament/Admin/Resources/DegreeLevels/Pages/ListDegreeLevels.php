<?php

namespace App\Filament\Admin\Resources\DegreeLevels\Pages;

use App\Filament\Admin\Resources\DegreeLevels\DegreeLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDegreeLevels extends ListRecords
{
    protected static string $resource = DegreeLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
