<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Pages;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowButtons extends ListRecords
{
    protected static string $resource = WorkflowButtonResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
