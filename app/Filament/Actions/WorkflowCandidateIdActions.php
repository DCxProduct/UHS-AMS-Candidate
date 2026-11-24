<?php

namespace App\Filament\Actions;

use App\Models\CandidateExitStatistic;
use App\Services\WorkflowCandidateIdGenerator;
use App\Support\FilamentActionPermissions;
use App\Support\WorkflowActionFeedback;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

final class WorkflowCandidateIdActions
{
    public static function make(string $resourceClass): Action
    {
        return Action::make('generate_candidate_id')
            ->label(fn (?Model $record): string => WorkflowButtons::label(self::entry($record), 'generate_candidate_id', __('workflow_notifications.actions.generate_candidate_id')))
            ->icon(fn (?Model $record): ?string => WorkflowButtons::icon(self::entry($record), 'generate_candidate_id', 'heroicon-o-identification'))
            ->color(fn (?Model $record): string => WorkflowButtons::color(self::entry($record), 'generate_candidate_id', 'info'))
            ->link()
            ->requiresConfirmation()
            ->modalHeading(__('workflow_notifications.confirmations.generate_candidate_id_title'))
            ->modalDescription(__('workflow_notifications.confirmations.generate_candidate_id_body'))
            ->modalSubmitActionLabel(__('workflow_notifications.actions.generate_candidate_id'))
            ->modalCancelActionLabel(__('app.cancel'))
            ->visible(fn (?Model $record): bool => self::canGenerate($record, $resourceClass))
            ->authorize(fn (?Model $record): bool => self::canGenerate($record, $resourceClass))
            ->action(function (Model $record, $livewire) use ($resourceClass): void {
                abort_unless(FilamentActionPermissions::canAccessWorkflowActions($resourceClass), 403);
                $entry = self::entry($record);
                abort_unless($entry !== null, 403);
                $stage = WorkflowStageProgression::currentStage($entry);
                $alert = is_scalar($stage['alert_message'] ?? null) ? trim(strip_tags((string) $stage['alert_message'])) : null;
                $candidateId = app(WorkflowCandidateIdGenerator::class)->generate($entry);

                if ($candidateId === null) {
                    Notification::make()
                        ->title(__('workflow_notifications.notifications.candidate_id_generation_missing_major_title'))
                        ->body(__('workflow_notifications.notifications.candidate_id_generation_missing_major_body'))
                        ->warning()->send();

                    return;
                }

                $record->refresh()->unsetRelations();
                WorkflowActionFeedback::send(
                    Notification::make()->success()->body(__('workflow_notifications.notifications.verification_candidate_id', ['id' => $candidateId])),
                    $alert,
                );
                if (method_exists($livewire, 'flushCachedTableRecords')) {
                    $livewire->flushCachedTableRecords();
                }
            });
    }

    private static function canGenerate(?Model $record, string $resourceClass): bool
    {
        return FilamentActionPermissions::canAccessWorkflowActions($resourceClass)
            && ($entry = self::entry($record)) !== null
            && app(WorkflowCandidateIdGenerator::class)->canGenerate($entry);
    }

    private static function entry(?Model $record): ?CustomFormEntry
    {
        return match (true) {
            $record instanceof CustomFormEntry => $record,
            $record instanceof CandidateExitStatistic => $record->customFormEntry,
            default => null,
        };
    }
}
