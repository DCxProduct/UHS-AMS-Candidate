<?php

namespace App\Filament\Admin\Resources\GuideBooks\Pages;

use App\Filament\Admin\Resources\GuideBooks\GuideBookResource;
use App\Models\GuideBook;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGuideBook extends ViewRecord
{
    protected static string $resource = GuideBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label(__('guide_books.open_file'))->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => route('guide-books.preview', $this->getRecord()))->openUrlInNewTab()
                ->visible(fn (): bool => GuideBook::validFilePath($this->getRecord()->file_path)),
            EditAction::make(),
        ];
    }
}
