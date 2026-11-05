<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Pages;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowStageTypes extends ListRecords
{
    protected static string $resource = WorkflowStageTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
