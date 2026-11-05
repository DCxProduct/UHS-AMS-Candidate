<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Pages;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Support\WorkflowStageTypeSettings;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateWorkflowStageType extends CreateRecord
{
    protected static string $resource = WorkflowStageTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['review_actions'], $data['stage_settings']);

        $selection = $data['enabled_actions'] ?? null;

        if ($selection !== null) {
            $behavior = WorkflowStageTypeSettings::behaviorForSelection($selection);
            if ($behavior === null) {
                throw ValidationException::withMessages([
                    'data.enabled_actions' => __('workflow_stage_types.validation.enabled_actions_same_group'),
                ]);
            }

            // Chosen buttons, rather than a client-supplied hidden value, determine execution.
            $data['behavior'] = $behavior;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
