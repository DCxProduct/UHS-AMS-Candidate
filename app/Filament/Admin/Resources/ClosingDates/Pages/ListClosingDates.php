<?php

namespace App\Filament\Admin\Resources\ClosingDates\Pages;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClosingDates extends ListRecords
{
    protected static string $resource = ClosingDateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New')
                ->icon('heroicon-o-plus')
                ->color('warning'),
        ];
    }
}