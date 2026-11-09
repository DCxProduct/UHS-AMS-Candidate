<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Pages;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateWorkflowStageType extends CreateRecord
{
    protected static string $resource = WorkflowStageTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['review_actions'], $data['stage_settings'], $data['enabled_actions'], $data['behavior']);

        if (blank($data['workflow_button_ids'] ?? null)) {
            throw ValidationException::withMessages([
                'data.workflow_button_ids' => __('workflow_stage_types.validation.workflow_buttons_required'),
            ]);
        }

        $data['enabled_actions'] = array_column((new WorkflowStageType($data))->buttonDefinitions(), 'action_key');
        $data['behavior'] = WorkflowStageTypeSettings::behaviorForSelection($data['enabled_actions']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
