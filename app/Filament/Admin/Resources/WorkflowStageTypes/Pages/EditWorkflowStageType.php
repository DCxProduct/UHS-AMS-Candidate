<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Pages;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowStageType;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditWorkflowStageType extends EditRecord
{
    protected static string $resource = WorkflowStageTypeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['review_actions'], $data['stage_settings'], $data['enabled_actions'], $data['behavior']);

        $record = $this->getRecord();
        if ($record->workflow_button_ids !== null && blank($data['workflow_button_ids'] ?? null)) {
            throw ValidationException::withMessages([
                'data.workflow_button_ids' => __('workflow_stage_types.validation.workflow_buttons_required'),
            ]);
        }

        // Legacy stages keep their exact saved selection; catalog selections are derived by the model.
        $data['enabled_actions'] = $record->getOriginal('enabled_actions');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (WorkflowStageType $record): bool => $record->canDelete()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
