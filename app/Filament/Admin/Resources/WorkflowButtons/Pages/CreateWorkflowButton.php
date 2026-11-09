<?php

namespace App\Filament\Admin\Resources\WorkflowButtons\Pages;

use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowButton extends CreateRecord
{
    protected static string $resource = WorkflowButtonResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
