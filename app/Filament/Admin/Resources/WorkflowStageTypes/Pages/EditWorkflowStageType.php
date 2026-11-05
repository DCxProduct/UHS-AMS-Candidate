<?php

namespace App\Filament\Admin\Resources\WorkflowStageTypes\Pages;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowStageType extends EditRecord
{
    protected static string $resource = WorkflowStageTypeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['review_actions'], $data['stage_settings']);

        $record = $this->getRecord();
        $buttons = array_values(array_intersect(array_keys(WorkflowStageTypeSettings::buttonActions()), $data['enabled_actions'] ?? []));
        if ($record->enabled_actions === null && $buttons === $record->enabledButtonKeys()) {
            $data['enabled_actions'] = null;
        } else {
            // Hidden legacy statuses are retained when editing the visible action buttons.
            $statuses = array_diff($record->enabledActionKeys(), array_keys(WorkflowStageTypeSettings::buttonActions()));
            $data['enabled_actions'] = array_merge($statuses, $buttons);
        }

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
