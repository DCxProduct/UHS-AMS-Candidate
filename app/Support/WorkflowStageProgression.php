<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Models\WorkflowNotification;
use App\Services\AmsPaymentSync;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;

/** Follow the frozen order for new applications; retain older applications' rules. */
final class WorkflowStageProgression
{
    public static function enabled(CustomFormEntry $entry): bool
    {
        $snapshot = WorkflowEntrySnapshot::snapshotFor($entry);

        return is_array($snapshot)
            && (int) ($snapshot['schema_version'] ?? 1) >= 2
            && ($snapshot['has_workflow'] ?? false) === true;
    }

    public static function initialize(CustomFormEntry $entry): bool
    {
        return self::change($entry, 'initialize');
    }

    public static function submitted(CustomFormEntry $entry): bool
    {
        return self::change($entry, 'submitted');
    }

    public static function reviewed(CustomFormEntry $entry): bool
    {
        return self::change($entry, 'reviewed');
    }

    public static function paid(CustomFormEntry $entry): bool
    {
        return self::change($entry, 'paid');
    }

    public static function passed(CustomFormEntry $entry): bool
    {
        return self::change($entry, 'passed');
    }

    public static function notified(CustomFormEntry $entry, WorkflowStageType $type): bool
    {
        return self::change($entry, 'notified', $type);
    }

    public static function currentType(CustomFormEntry $entry): ?WorkflowStageType
    {
        if (! self::enabled($entry)) {
            return null;
        }

        $state = self::state($entry);
        if (($state['finished'] ?? false)
            || in_array(strtolower((string) $entry->review_status), ['draft', 'rejected', 'failed'], true)
            || in_array(strtolower((string) data_get($entry->data, 'registration_status')), ['draft', 'rejected', 'failed'], true)) {
            return null;
        }

        return ($state['submitted'] ?? false)
            ? WorkflowStageType::tryFrom((string) ($state['current_type'] ?? ''))
            : null;
    }

    public static function currentStage(CustomFormEntry $entry): ?array
    {
        if (! self::enabled($entry)) {
            return null;
        }

        $stages = self::stages($entry);
        $index = self::state($entry)['current_index'] ?? null;
        $index ??= $stages === [] ? null : count($stages) - 1;

        return $index !== null && isset($stages[$index]) ? [...$stages[$index], '_index' => $index] : null;
    }

    public static function isCurrent(CustomFormEntry $entry, WorkflowStageType $type): bool
    {
        $current = self::currentType($entry);

        return $current === $type
            || ($type === WorkflowStageType::Review && $current === WorkflowStageType::Approval);
    }

    public static function readyForFinal(CustomFormEntry $entry): bool
    {
        return self::enabled($entry) && (bool) (self::state($entry)['ready_for_final'] ?? false);
    }

    public static function finished(CustomFormEntry $entry): bool
    {
        return self::enabled($entry) && (bool) (self::state($entry)['finished'] ?? false);
    }

    public static function completedAt(CustomFormEntry $entry, int $index): ?string
    {
        return self::state($entry)['completed_at'][$index] ?? null;
    }

    public static function finalStageType(CustomFormEntry $entry): ?WorkflowStageType
    {
        $stages = self::stages($entry);

        return WorkflowStageType::tryFrom((string) (last($stages)['stage_type'] ?? ''));
    }

    private static function stages(CustomFormEntry $entry): array
    {
        return collect(WorkflowNotification::forEntry($entry)?->stages ?? [])
            ->map(fn (mixed $stage): array => is_array($stage) ? ($stage['data'] ?? $stage) : [])
            ->filter(fn (array $stage): bool => WorkflowStageType::tryFrom((string) ($stage['stage_type'] ?? '')) !== null)
            ->values()->all();
    }

    private static function state(CustomFormEntry $entry): array
    {
        if ($entry->exists && ! array_key_exists('data', $entry->getAttributes())) {
            $data = DB::table($entry->getTable())->where($entry->getKeyName(), $entry->getKey())->value('data');
            $entry->data = is_string($data) ? json_decode($data, true, flags: JSON_THROW_ON_ERROR) : $data;
        }

        $saved = data_get($entry->data, 'workflow_progress');
        if (is_array($saved) && ($saved['version'] ?? null) === 1) {
            return $saved;
        }

        $status = strtolower(trim((string) $entry->review_status));
        $stages = self::stages($entry);
        $types = array_column($stages, 'stage_type');
        $state = [
            'version' => 1,
            'submitted' => $status !== 'draft' && strtolower((string) data_get($entry->data, 'registration_status')) !== 'draft',
            'reviewed' => in_array($status, ['approved', 'accepted', 'passed', 'completed'], true),
            'result_passed' => strtolower((string) data_get($entry->data, 'candidate_status')) === 'passed'
                || in_array($status, ['passed', 'completed'], true),
            'payment_completed' => $entry->exists && self::latestPaymentIsPaid($entry),
            'has_review' => count(array_intersect($types, ['review', 'approval'])) > 0,
            'has_results' => in_array('awaiting_results', $types, true),
            'current_index' => 0,
            'current_type' => $types[0] ?? null,
            'completed_at' => [],
            'ready_for_final' => false,
            'finished' => false,
            'final_notified_at' => null,
        ];

        return self::settle($state, $stages);
    }

    /** Consume only the next configured stage; automatic checkpoints never mean exam passed. */
    private static function settle(array $state, array $stages): array
    {
        $state['ready_for_final'] = false;
        $state['finished'] = false;
        if (! $state['submitted']) {
            return $state;
        }

        foreach ($stages as $index => $stage) {
            if (isset($state['completed_at'][$index])) {
                continue;
            }

            $type = WorkflowStageType::from($stage['stage_type']);
            $state['current_index'] = $index;
            $state['current_type'] = $type->value;
            if ($type === WorkflowStageType::Completed && $index === count($stages) - 1) {
                $state['ready_for_final'] = true;
                if (blank($state['final_notified_at'])) {
                    return $state;
                }
            } elseif (! match ($type) {
                WorkflowStageType::FormSubmission => $state['submitted'],
                WorkflowStageType::Review, WorkflowStageType::Approval => $state['reviewed'],
                WorkflowStageType::Payment => $state['payment_completed'],
                WorkflowStageType::AwaitingResults => $state['result_passed'],
                WorkflowStageType::Completed => true,
                WorkflowStageType::Rejected => false,
            }) {
                return $state;
            }

            $state['completed_at'][$index] = now()->toIso8601String();
        }

        $state['current_index'] = null;
        $state['current_type'] = null;
        $state['finished'] = true;
        $state['ready_for_final'] = true;

        return $state;
    }

    private static function change(CustomFormEntry $entry, string $event, ?WorkflowStageType $notifiedType = null): bool
    {
        if (! $entry->exists || ! self::enabled($entry)) {
            return false;
        }

        $changed = DB::transaction(function () use ($entry, $event, $notifiedType): bool {
            $locked = CustomFormEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            if (! self::enabled($locked)) {
                return false;
            }

            $stages = self::stages($locked);
            $previous = data_get($locked->data, 'workflow_progress');
            $state = self::state($locked);
            if (! is_array($previous) && in_array($event, ['reviewed', 'paid', 'passed'], true)) {
                $state[match ($event) {
                    'reviewed' => 'reviewed',
                    'paid' => 'payment_completed',
                    'passed' => 'result_passed',
                }] = false;
                $state['completed_at'] = [];
                $state = self::settle($state, $stages);
            }
            $current = WorkflowStageType::tryFrom((string) ($state['current_type'] ?? ''));
            $blocked = in_array(strtolower((string) $locked->review_status), ['draft', 'rejected', 'failed'], true);
            $accepted = match ($event) {
                'initialize' => ! is_array($previous),
                'submitted' => strtolower((string) $locked->review_status) !== 'draft',
                'reviewed' => ! $blocked && in_array($current, [WorkflowStageType::Review, WorkflowStageType::Approval], true),
                'paid' => ! $blocked && $current === WorkflowStageType::Payment && self::latestPaymentIsPaid($locked),
                'passed' => ! $blocked && $current === WorkflowStageType::AwaitingResults,
                'notified' => ! $blocked && $state['ready_for_final'] && $notifiedType === self::finalStageType($locked),
                default => false,
            };
            if (! $accepted && is_array($previous)) {
                return false;
            }
            if (! $accepted && $event !== 'initialize') {
                // A rejected/out-of-order action must not initialize inferred facts.
                return false;
            }

            if ($event === 'submitted') {
                $state['submitted'] = true;
            } elseif ($event === 'reviewed') {
                $state['reviewed'] = true;
            } elseif ($event === 'paid') {
                $state['payment_completed'] = true;
            } elseif ($event === 'passed') {
                $state['result_passed'] = true;
            } elseif ($event === 'notified') {
                $state['final_notified_at'] = now()->toIso8601String();
            }

            $state = self::settle($state, $stages);
            // Resubmission can reset canonical fields while retaining completed gates.
            if ($state === $previous && $event !== 'submitted') {
                return false;
            }

            self::storeProgress($locked, $state, 'workflow_stage_'.$event);
            $newlyCompleted = array_diff(array_keys($state['completed_at']), array_keys($previous['completed_at'] ?? []));
            foreach ($newlyCompleted as $index) {
                $checkpoint = WorkflowStageType::from($stages[$index]['stage_type']);
                if ($checkpoint === WorkflowStageType::FormSubmission
                    || ($checkpoint === WorkflowStageType::Completed && $index < count($stages) - 1)) {
                    DB::afterCommit(function () use ($locked, $checkpoint): void {
                        if ($checkpoint === WorkflowStageType::FormSubmission) {
                            WorkflowSubmissionMessages::notifyCheckpoint($locked);
                        } else {
                            WorkflowStageMessages::notifyCheckpoint($locked, $checkpoint);
                        }
                    });
                }
            }
            if ($state['submitted']
                && $state['current_type'] === WorkflowStageType::Payment->value
                && ($previous['current_type'] ?? null) !== WorkflowStageType::Payment->value) {
                // AMS collection can follow any configured gate, including Results.
                DB::afterCommit(function () use ($locked): void {
                    try {
                        app(AmsPaymentSync::class)->sync($locked);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                });
            }

            return true;
        });
        $entry->refresh();

        return $changed;
    }

    private static function storeProgress(CustomFormEntry $entry, array $state, string $action): void
    {
        $data = is_array($entry->data) ? $entry->data : [];
        $data['workflow_progress'] = $state;
        $status = (string) $entry->review_status;
        if ($state['submitted'] && ! in_array(strtolower($status), ['rejected', 'failed'], true)) {
            $final = $state['ready_for_final'];
            $status = $final ? 'passed' : (($state['reviewed'] || ! $state['has_review']) ? 'approved' : 'pending');
            $data['registration_status'] = $status;
            $data['candidate_status'] = ($state['result_passed'] || $final) ? 'passed' : 'pending';
            if ($data['candidate_status'] === 'passed') {
                $data['candidate_reviewed_at'] ??= now()->toDateTimeString();
            } else {
                $data['candidate_reviewed_at'] = null;
            }
        }

        self::store($entry, $data, $status, $action);
    }

    public static function advance(CustomFormEntry $entry): bool
    {
        if (self::enabled($entry)) {
            $initialized = self::initialize($entry);

            return match (self::currentType($entry)) {
                WorkflowStageType::Review, WorkflowStageType::Approval => in_array(strtolower((string) $entry->review_status), ['approved', 'accepted'], true)
                    ? self::reviewed($entry) : $initialized,
                WorkflowStageType::Payment => self::paid($entry),
                default => $initialized,
            };
        }

        if (! $entry->exists) {
            return false;
        }

        $changed = DB::transaction(function () use ($entry): bool {
            $locked = CustomFormEntry::query()->lockForUpdate()->find($entry->getKey());
            if (! $locked || ! self::finishesAfterReviewOrPayment($locked)
                || ! in_array(strtolower(trim((string) $locked->review_status)), ['approved', 'accepted'], true)
                || (WorkflowStageMessages::requiresPayment($locked) && ! self::latestPaymentIsPaid($locked))) {
                return false;
            }

            $data = is_array($locked->data) ? $locked->data : [];
            $data['candidate_status'] = 'passed';
            $data['registration_status'] = 'passed';
            $data['exam_result'] = 'passed';
            $data['result_status'] = 'passed';
            $data['candidate_reviewed_at'] = now()->toDateTimeString();
            $data['workflow_auto_completed'] = true;

            self::store($locked, $data, 'passed', 'workflow_auto_completed');

            return true;
        });

        if ($changed) {
            $entry->refresh();
        }

        return $changed;
    }

    /** A reversed receipt may undo automatic completion, never an exam decision. */
    public static function rollbackUnpaidAutoCompletion(CustomFormEntry $entry): bool
    {
        if (self::enabled($entry)) {
            return self::rewindPayment($entry);
        }

        if (! $entry->exists) {
            return false;
        }

        $changed = DB::transaction(function () use ($entry): bool {
            $locked = CustomFormEntry::query()->lockForUpdate()->find($entry->getKey());
            $data = is_array($locked?->data) ? $locked->data : [];
            if (! $locked || ($data['workflow_auto_completed'] ?? false) !== true
                || strtolower(trim((string) $locked->review_status)) !== 'passed'
                || ! self::finishesAfterReviewOrPayment($locked)
                || ! WorkflowStageMessages::requiresPayment($locked)
                || self::latestPaymentIsPaid($locked)) {
                return false;
            }

            $data['candidate_status'] = 'pending';
            $data['registration_status'] = 'approved';
            $data['exam_result'] = 'pending';
            $data['result_status'] = 'pending';
            $data['candidate_reviewed_at'] = null;
            unset($data['workflow_auto_completed']);

            self::store($locked, $data, 'approved', 'workflow_auto_completion_reversed');
            WorkflowStageMessages::resetCompletedNotification($locked);

            return true;
        });

        if ($changed) {
            $entry->refresh();
        }

        return $changed;
    }

    private static function rewindPayment(CustomFormEntry $entry): bool
    {
        $changed = DB::transaction(function () use ($entry): bool {
            $locked = CustomFormEntry::query()->lockForUpdate()->findOrFail($entry->getKey());
            if (self::latestPaymentIsPaid($locked)) {
                return false;
            }

            $state = self::state($locked);
            $stages = self::stages($locked);
            $index = array_search('payment', array_column($stages, 'stage_type'), true);
            if ($index === false || ! ($state['payment_completed'] ?? false)) {
                return false;
            }

            foreach (array_keys($state['completed_at']) as $completedIndex) {
                if ($completedIndex >= $index) {
                    unset($state['completed_at'][$completedIndex]);
                }
            }
            $state['payment_completed'] = false;
            $state['final_notified_at'] = null;
            $reviewIndex = collect($stages)->search(fn (array $stage): bool => in_array($stage['stage_type'], ['review', 'approval'], true));
            $resultIndex = array_search('awaiting_results', array_column($stages, 'stage_type'), true);
            if ($reviewIndex !== false && $reviewIndex > $index) {
                $state['reviewed'] = false;
            }
            if ($resultIndex !== false && $resultIndex > $index) {
                $state['result_passed'] = false;
            }
            self::storeProgress($locked, self::settle($state, $stages), 'workflow_payment_reversed');
            $completedIndex = array_search('completed', array_column($stages, 'stage_type'), true);
            if ($completedIndex !== false && $completedIndex >= $index) {
                WorkflowStageMessages::resetCompletedNotification($locked);
            }

            return true;
        });
        $entry->refresh();

        return $changed;
    }

    private static function finishesAfterReviewOrPayment(CustomFormEntry $entry): bool
    {
        $workflow = WorkflowNotification::forEntry($entry);

        return $workflow !== null
            && $workflow->hasStageType(WorkflowStageType::Completed)
            && ! $workflow->hasStageType(WorkflowStageType::AwaitingResults);
    }

    private static function latestPaymentIsPaid(CustomFormEntry $entry): bool
    {
        return strtolower(trim((string) Payment::query()
            ->where('custom_form_entry_id', $entry->getKey())
            ->where('form_id', $entry->custom_form_id)
            ->latest('id')
            ->value('status_payt'))) === 'paid';
    }

    private static function store(CustomFormEntry $entry, array $data, string $reviewStatus, string $action): void
    {
        $statusKeys = ['candidate_status', 'registration_status', 'exam_result', 'result_status', 'candidate_reviewed_at', 'workflow_auto_completed'];
        $oldValues = ['review_status' => $entry->review_status];
        $newValues = ['review_status' => $reviewStatus];
        foreach ($statusKeys as $key) {
            $oldValues[$key] = data_get($entry->data, $key);
            $newValues[$key] = $data[$key] ?? null;
        }

        DB::table($entry->getTable())->where($entry->getKeyName(), $entry->getKey())->update([
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'review_status' => $reviewStatus,
            'updated_at' => now(),
        ]);

        $entry->refresh();
        CandidateStatisticsSynchronizer::syncEntry($entry);
        AuditLogger::log(
            action: $action,
            auditable: $entry,
            oldValues: $oldValues,
            newValues: $newValues,
            description: self::enabled($entry)
                ? 'Application advanced through its frozen workflow: '.$action
                : ($reviewStatus === 'passed'
                    ? 'Application automatically completed without an exam-result stage'
                    : 'Automatic workflow completion reversed after payment reversal'),
            metadata: ['workflow_stage' => $data['workflow_progress']['current_type'] ?? WorkflowStageType::Completed->value, 'automatic' => true],
        );
    }
}
