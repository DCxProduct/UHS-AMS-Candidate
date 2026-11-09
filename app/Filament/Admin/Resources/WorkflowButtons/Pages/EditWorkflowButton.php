<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Pages;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Models\WorkflowButton;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowButton extends EditRecord
{
    protected static string $resource = WorkflowButtonResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn (WorkflowButton $record): bool => $record->canDelete())];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
