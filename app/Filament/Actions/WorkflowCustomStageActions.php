<?php

namespace App\Filament\Actions;

use App\Models\CandidateExitStatistic;
use App\Services\WorkflowCustomButtonService;
use App\Support\FilamentActionPermissions;
use App\Support\WorkflowActionFeedback;
use App\Support\WorkflowButtonColors;
use App\Support\WorkflowEntrySnapshot;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

final class WorkflowCustomStageActions
{
    public static function make(string $resourceClass): Action
    {
        return Action::make('custom_workflow_stage')
            ->view('filament.actions.workflow-custom-stage-buttons')
            ->viewData(fn (?Model $record): array => [
                'customStageButtons' => self::buttons($record, $resourceClass),
            ])
            ->label(fn (?Model $record, array $arguments): string => self::label(self::button($record, $arguments)))
            ->icon(fn (?Model $record, array $arguments): ?string => self::button($record, $arguments)['icon'] ?? null)
            ->color(fn (?Model $record, array $arguments): string => WorkflowButtonColors::forDefinition(self::button($record, $arguments), 'primary'))
            ->visible(fn (?Model $record, array $arguments): bool => self::canExecute($record, $arguments, $resourceClass))
            ->authorize(fn (?Model $record, array $arguments): bool => self::canExecute($record, $arguments, $resourceClass))
            ->action(function (Model $record, array $arguments, $livewire) use ($resourceClass): void {
                abort_unless(FilamentActionPermissions::canAccessWorkflowActions($resourceClass), 403);
                $entry = self::entry($record);
                $selection = self::selection($arguments);
                abort_unless($entry !== null && $selection !== null, 403);

                [$stageIndex, $buttonId] = $selection;
                $snapshot = WorkflowEntrySnapshot::snapshotFor($entry);
                $item = $snapshot['stages'][$stageIndex] ?? [];
                $stage = is_array($item['data'] ?? null) ? $item['data'] : $item;
                $alert = is_scalar($stage['alert_message'] ?? null) ? trim((string) $stage['alert_message']) : null;

                if (! app(WorkflowCustomButtonService::class)->execute($entry, $stageIndex, $buttonId)) {
                    return;
                }

                $record->refresh()->unsetRelations();
                WorkflowActionFeedback::send(Notification::make()->success(), $alert);

                if (method_exists($livewire, 'flushCachedTableRecords')) {
                    $livewire->flushCachedTableRecords();
                }
            });
    }

    private static function entry(?Model $record): ?CustomFormEntry
    {
        return match (true) {
            $record instanceof CustomFormEntry => $record,
            $record instanceof CandidateExitStatistic => $record->customFormEntry,
            default => null,
        };
    }

    private static function buttons(?Model $record, string $resourceClass): array
    {
        if (! FilamentActionPermissions::canAccessWorkflowActions($resourceClass) || ($entry = self::entry($record)) === null) {
            return [];
        }

        $service = app(WorkflowCustomButtonService::class);

        return array_values(array_filter($service->buttonsFor($entry), fn (array $button): bool => $service->canExecute(
            $entry,
            (int) $button['_stage_index'],
            (int) $button['id'],
        )));
    }

    private static function canExecute(?Model $record, array $arguments, string $resourceClass): bool
    {
        $buttons = self::buttons($record, $resourceClass);
        if ($arguments === []) {
            return $buttons !== [];
        }

        $selection = self::selection($arguments);
        if ($selection === null) {
            return false;
        }

        [$stageIndex, $buttonId] = $selection;
        foreach ($buttons as $button) {
            if ((int) $button['_stage_index'] === $stageIndex && (int) $button['id'] === $buttonId) {
                return true;
            }
        }

        return false;
    }

    private static function selection(array $arguments): ?array
    {
        $stageIndex = filter_var($arguments['stage_index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $buttonId = filter_var($arguments['button_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $stageIndex !== false && $buttonId !== false ? [$stageIndex, $buttonId] : null;
    }

    private static function button(?Model $record, array $arguments): array
    {
        $entry = self::entry($record);
        $selection = self::selection($arguments);
        if ($entry === null || $selection === null) {
            return [];
        }

        [$stageIndex, $buttonId] = $selection;
        foreach (app(WorkflowCustomButtonService::class)->buttonsFor($entry) as $button) {
            if ((int) $button['_stage_index'] === $stageIndex && (int) $button['id'] === $buttonId) {
                return $button;
            }
        }

        return [];
    }

    private static function label(array $button): string
    {
        $label = in_array(app()->getLocale(), ['km', 'kh'], true)
            ? (($button['label_km'] ?? null) ?: ($button['label_en'] ?? null))
            : (($button['label_en'] ?? null) ?: ($button['label_km'] ?? null));

        return filled($label) ? (string) $label : __('workflow_buttons.resource_label');
    }
}
