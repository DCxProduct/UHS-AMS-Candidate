<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Query configured labels without changing the application's workflow state. */
final class WorkflowStatusSearch
{
    private static ?array $reviewConfigurations = null;

    private static ?bool $reviewHasSnapshot = null;

    private static ?array $reviewExpressions = null;

    private static ?array $reviewStageSets = null;

    public static function applyReview(Builder $query, string $search): Builder
    {
        $previousConfigurations = self::$reviewConfigurations;
        $previousSnapshot = self::$reviewHasSnapshot;
        $previousExpressions = self::$reviewExpressions;
        $previousStageSets = self::$reviewStageSets;
        $previousMetadata = LegacyWorkflowStatusSearch::beginSearchMetadata();
        self::$reviewConfigurations ??= WorkflowNotification::query()->get(['id', 'stages'])->all();
        self::$reviewHasSnapshot ??= Schema::hasColumn($query->getModel()->getTable(), 'workflow_snapshot');
        self::$reviewExpressions = [];
        self::$reviewStageSets = array_map(fn (WorkflowNotification $workflow): array => $workflow->stages ?? [], self::$reviewConfigurations);
        if (self::$reviewHasSnapshot) {
            foreach ($query->getModel()->newQueryWithoutScopes()->where('workflow_snapshot->has_workflow', true)
                ->select('workflow_snapshot->stages as review_search_stages')->distinct()->pluck('review_search_stages') as $stages) {
                $stages = is_string($stages) ? json_decode($stages, true) : $stages;
                if (is_array($stages)) {
                    self::$reviewStageSets[] = $stages;
                }
            }
        }

        try {
            $defaults = collect([
                __('candidate_entrance_statistics.statuses.accepted'), __('candidate_entrance_statistics.statuses.paid'),
                __('candidate_entrance_statistics.statuses.final_rejected'), __('candidate_entrance_statistics.statuses.rejected'),
                __('candidate_entrance_statistics.statuses.pending'), __('candidate_entrance_statistics.statuses.passed'),
                __('student_profile.save_as_draft'), __('workflow_notifications.status_defaults.verified'),
                __('workflow_notifications.status_defaults.verification'), __('workflow_notifications.status_defaults.notification_pending'),
                __('payments.options.status_payt.paid'), __('payments.options.status_payt.unpaid'),
            ]);
            $possible = $defaults->contains(fn (string $label): bool => self::contains($label, $search))
                || collect(self::$reviewStageSets)->flatten()->contains(fn ($label): bool => is_scalar($label) && self::contains((string) $label, $search));
            if (! $possible) {
                return $query->where(function (Builder $raw) use ($search): void {
                    CaseInsensitiveSearch::applyCast($raw, 'review_status', $search);
                    CaseInsensitiveSearch::applyCast($raw, 'data->registration_status', $search, or: true);
                });
            }
            $hasOrdered = self::$reviewHasSnapshot && WorkflowProgressQuery::ordered($query->getModel()->newQuery())->exists();
            $hasLegacy = WorkflowProgressQuery::legacy($query->getModel()->newQuery())->exists();
            $predicate = $query->getModel()->newQueryWithoutScopes();
            $predicate->where(function (Builder $matches) use ($search, $hasOrdered, $hasLegacy): void {
                CaseInsensitiveSearch::applyCast($matches, 'review_status', $search);
                CaseInsensitiveSearch::applyCast($matches, 'data->registration_status', $search, or: true);
                if ($hasOrdered) {
                    $matches->orWhere(function (Builder $ordered) use ($search): void {
                        WorkflowProgressQuery::ordered($ordered);
                        self::orderedReview($ordered, $search);
                    });
                }
                if ($hasLegacy) {
                    $matches->orWhere(function (Builder $legacy) use ($search): void {
                        WorkflowProgressQuery::legacy($legacy);
                        self::legacyReview($legacy, $search);
                    });
                }
            });
            $where = $predicate->getQuery()->getGrammar()->compileWheres($predicate->getQuery());
            $selects = [];
            $bindings = [];
            foreach (self::$reviewExpressions as [$alias, $expression, $parameters]) {
                if (! str_contains($where, 'workflow_review_labels.'.$alias.')')) {
                    continue;
                }
                $selects[] = $expression.' AS '.$alias;
                array_push($bindings, ...$parameters);
            }
            $selects = $selects === [] ? ['1 AS unused_label'] : $selects;
            $materialized = in_array($query->getConnection()->getDriverName(), ['pgsql', 'sqlite'], true) ? ' MATERIALIZED' : '';

            return $query->whereRaw('EXISTS (WITH workflow_review_labels AS'.$materialized.' (SELECT '.implode(', ', $selects).') SELECT 1 FROM workflow_review_labels '.$where.')', [
                ...$bindings, ...$predicate->getQuery()->getBindings(),
            ]);
        } finally {
            self::$reviewConfigurations = $previousConfigurations;
            self::$reviewHasSnapshot = $previousSnapshot;
            self::$reviewExpressions = $previousExpressions;
            self::$reviewStageSets = $previousStageSets;
            LegacyWorkflowStatusSearch::endSearchMetadata($previousMetadata);
        }
    }

    private static function orderedReview(Builder $query, string $search): void
    {
        $query->where(function (Builder $matches) use ($search): void {
            foreach (['failed' => 'reject', 'rejected' => 'send_back'] as $status => $action) {
                $matches->orWhere(function (Builder $review) use ($search, $status, $action): void {
                    self::statusIn($review, 'review_status', [$status]);
                    self::firstText($review, $search, [['review', "review_actions.{$action}.status_message"], ['review', 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search));
                });
            }
            $matches->orWhere(function (Builder $override) use ($search): void {
                self::sharedOrderedReview($override);
                $override->where(function (Builder $labels) use ($search): void {
                    $labels->where(function (Builder $verified) use ($search): void {
                        $verified->where('data->workflow_progress->verification_completed', true);
                        self::firstText($verified, $search, [['verification', 'status_message']], __('workflow_notifications.status_defaults.verified'));
                    })->orWhere(function (Builder $reviewed) use ($search): void {
                        self::notTrue($reviewed, 'data->workflow_progress->verification_completed');
                        self::applyStageText($reviewed, 'review', 'review_actions.accept.status_message', $search);
                    });
                });
            })->orWhere(function (Builder $stages) use ($search): void {
                self::statusIn($stages, 'review_status', ['failed', 'rejected'], not: true);
                $stages->whereNot(function (Builder $override): void {
                    self::sharedOrderedReview($override);
                    $override->where(function (Builder $label): void {
                        $label->where('data->workflow_progress->verification_completed', true)
                            ->orWhere(fn (Builder $review): Builder => self::applyStageText($review, 'review', 'review_actions.accept.status_message', ''));
                    });
                });
                $stages->where(function (Builder $visibility) use ($search): void {
                    $visibility->where(function (Builder $active) use ($search): void {
                        self::orderedActive($active);
                        $active->where(function (Builder $current) use ($search): void {
                            foreach (['form_submission', 'review', 'verification', 'payment', 'awaiting_results', 'completed'] as $type) {
                                $current->orWhere(function (Builder $stage) use ($type, $search): void {
                                    self::currentStage($stage, $type);
                                    self::orderedStageText($stage, $type, $search);
                                });
                            }
                        });
                    })->orWhere(function (Builder $inactive) use ($search): void {
                        $inactive->whereNot(fn (Builder $active): Builder => self::orderedActive($active));
                        self::submissionOrDefault($inactive, $search);
                    });
                });
            });
        });
    }

    private static function orderedStageText(Builder $query, string $type, string $search): void
    {
        if ($type === 'form_submission') {
            self::firstText($query, $search, [['form_submission', 'submission_messages.candidate_status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search));
        } elseif ($type === 'review') {
            $query->where(function (Builder $states) use ($search): void {
                $states->where(function (Builder $finished) use ($search): void {
                    $finished->where('data->workflow_progress->finished', true);
                    self::firstText($finished, $search, [['review', 'review_actions.accept.status_message'], ['review', 'status_message']], __('candidate_entrance_statistics.statuses.accepted'));
                })->orWhere(function (Builder $pending) use ($search): void {
                    self::notTrue($pending, 'data->workflow_progress->finished');
                    $pending->where(function (Builder $labels) use ($search): void {
                        $labels->where(function (Builder $submission) use ($search): void {
                            self::submissionVisible($submission);
                            self::applyStageText($submission, 'form_submission', 'submission_messages.candidate_status_message', $search);
                        })->orWhere(function (Builder $review) use ($search): void {
                            $review->whereNot(fn (Builder $submission): Builder => self::submissionVisible($submission));
                            self::firstText($review, $search, [['review', 'status_message']], __('candidate_entrance_statistics.statuses.pending'));
                        });
                    });
                });
            });
        } elseif ($type === 'verification') {
            $query->where(function (Builder $states) use ($search): void {
                $states->where(function (Builder $finished) use ($search): void {
                    $finished->where('data->workflow_progress->finished', true);
                    self::firstText($finished, $search, [['verification', 'status_message']], __('workflow_notifications.status_defaults.verified'));
                })->orWhere(function (Builder $pending) use ($search): void {
                    self::notTrue($pending, 'data->workflow_progress->finished');
                    $pending->where(function (Builder $labels) use ($search): void {
                        $labels->where(function (Builder $paid) use ($search): void {
                            self::stageCompleted($paid, 'payment');
                            self::paid($paid);
                            self::firstText($paid, $search, [['payment', 'status_message']], __('workflow_notifications.status_defaults.verification'));
                        });
                        if (self::contains(__('workflow_notifications.status_defaults.verification'), $search)) {
                            $labels->orWhere(function (Builder $unpaid): void {
                                // Missing JSON keys must retain the pending label;
                                // negating their SQL NULL predicates would hide it.
                                $paid = $unpaid->getModel()->newQueryWithoutScopes();
                                self::stageCompleted($paid, 'payment');
                                self::paid($paid);
                                $key = $unpaid->getModel()->getQualifiedKeyName();
                                $unpaid->whereNotIn($key, $paid->select($key));
                            });
                        }
                    });
                });
            });
        } elseif ($type === 'payment') {
            $query->where(function (Builder $states) use ($search): void {
                foreach ([true, false] as $paid) {
                    $states->orWhere(function (Builder $state) use ($search, $paid): void {
                        self::stageCompleted($state, 'payment', $paid);
                        self::firstText($state, $search, [['payment', $paid ? 'status_message' : 'payment_statuses.unpaid_status_message']], __('payments.options.status_payt.'.($paid ? 'paid' : 'unpaid')));
                    });
                }
            });
        } elseif ($type === 'awaiting_results') {
            $query->where(function (Builder $states) use ($search): void {
                $states->where(function (Builder $passed) use ($search): void {
                    $passed->where('data->workflow_progress->result_passed', true);
                    self::firstText($passed, $search, [['awaiting_results', 'status_message']], __('candidate_entrance_statistics.statuses.passed'));
                })->orWhere(function (Builder $waiting) use ($search): void {
                    self::notTrue($waiting, 'data->workflow_progress->result_passed');
                    $waiting->where(function (Builder $labels) use ($search): void {
                        $labels->where(function (Builder $received) use ($search): void {
                            self::receiptStatusApplies($received);
                            self::firstText($received, $search, [['verification', 'status_message']], __('workflow_notifications.status_defaults.verified'));
                        })->orWhere(function (Builder $other) use ($search): void {
                            $received = $other->getModel()->newQueryWithoutScopes();
                            self::receiptStatusApplies($received);
                            $key = $other->getModel()->getQualifiedKeyName();
                            $other->whereNotIn($key, $received->select($key));
                            $other->where(function (Builder $paymentLabels) use ($search): void {
                                $paymentLabels->where(function (Builder $paid) use ($search): void {
                                    self::stageCompleted($paid, 'payment');
                                    self::paid($paid);
                                    self::firstText($paid, $search, [['payment', 'status_message'], ['awaiting_results', 'result_statuses.pending_status_message']], __('candidate_entrance_statistics.statuses.pending'));
                                })->orWhere(function (Builder $unpaid) use ($search): void {
                                    $paid = $unpaid->getModel()->newQueryWithoutScopes();
                                    self::stageCompleted($paid, 'payment');
                                    self::paid($paid);
                                    $key = $unpaid->getModel()->getQualifiedKeyName();
                                    $unpaid->whereNotIn($key, $paid->select($key));
                                    self::firstText($unpaid, $search, [['awaiting_results', 'result_statuses.pending_status_message']], __('candidate_entrance_statistics.statuses.pending'));
                                });
                            });
                        });
                    });
                });
            });
        } elseif ($type === 'completed') {
            $query->where(function (Builder $states) use ($search): void {
                $states->where(function (Builder $notified) use ($search): void {
                    self::notified($notified);
                    self::firstText($notified, $search, [['completed', 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search));
                })->orWhere(function (Builder $unnotified) use ($search): void {
                    $unnotified->whereNot(fn (Builder $notified): Builder => self::notified($notified));
                    $unnotified->where(function (Builder $labels) use ($search): void {
                        $labels->where(function (Builder $passed) use ($search): void {
                            $passed->where(fn (Builder $ready): Builder => $ready->where('data->workflow_progress->ready_for_final', true)->orWhere('data->workflow_progress->result_passed', true));
                            self::firstText($passed, $search, [['awaiting_results', 'status_message']], __('candidate_entrance_statistics.statuses.passed'));
                        });
                        if (self::contains(__('workflow_notifications.status_defaults.notification_pending'), $search)) {
                            $labels->orWhere(function (Builder $pending): void {
                                self::notTrue($pending, 'data->workflow_progress->ready_for_final');
                                self::notTrue($pending, 'data->workflow_progress->result_passed');
                            });
                        }
                    });
                });
            });
        }
    }

    /** Keep the receipt label when receipt is the later completed action. */
    private static function receiptStatusApplies(Builder $query): Builder
    {
        self::stageCompleted($query, 'verification');
        $paid = $query->getModel()->newQueryWithoutScopes();
        self::stageCompleted($paid, 'payment');
        self::paid($paid);
        $key = $query->getModel()->getQualifiedKeyName();
        $orderPairs = self::receiptAfterPaymentPositions();

        return $query->where(function (Builder $states) use ($paid, $key, $orderPairs): void {
            $states->whereNotIn($key, $paid->select($key))
                ->orWhere(function (Builder $order) use ($orderPairs): void {
                    $order->whereRaw('1 = 0');
                    foreach ($orderPairs as [$receiptIndex, $paymentIndex]) {
                        $order->orWhere(function (Builder $receipt) use ($receiptIndex, $paymentIndex): void {
                            self::stageTypeAt($receipt, $receiptIndex, 'verification');
                            self::stageTypeAt($receipt, $paymentIndex, 'payment');
                        });
                    }
                });
        });
    }

    /** Use actual frozen/template orders to avoid planning every possible pair. */
    private static function receiptAfterPaymentPositions(): array
    {
        $pairs = [];
        $stageLimit = count(WorkflowStageType::cases());
        if (self::$reviewStageSets === null) {
            for ($receiptIndex = 1; $receiptIndex < $stageLimit; $receiptIndex++) {
                for ($paymentIndex = 0; $paymentIndex < $receiptIndex; $paymentIndex++) {
                    $pairs[] = [$receiptIndex, $paymentIndex];
                }
            }

            return $pairs;
        }

        foreach (self::$reviewStageSets as $stages) {
            $paymentPositions = [];
            for ($index = 0; $index < $stageLimit; $index++) {
                $stage = $stages[$index] ?? null;
                $stage = is_array($stage) && is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
                if (! is_array($stage)) {
                    continue;
                }
                if (($stage['stage_type'] ?? null) === 'payment') {
                    $paymentPositions[] = $index;
                } elseif (($stage['stage_type'] ?? null) === 'verification') {
                    foreach ($paymentPositions as $paymentIndex) {
                        $pairs[$index.':'.$paymentIndex] = [$index, $paymentIndex];
                    }
                }
            }
        }

        return array_values($pairs);
    }

    private static function sharedOrderedReview(Builder $query): Builder
    {
        self::statusIn($query, 'review_status', ['approved', 'accepted']);
        self::notTrue($query, 'data->workflow_progress->finished');
        $query->where('data->workflow_progress->current_type', 'payment');
        self::stageCompleted($query, 'review');

        return $query->whereNot(fn (Builder $paid): Builder => self::paid($paid));
    }

    /** Search the first configured label, preserving the formatter's fallback order. */
    public static function firstText(Builder $query, string $search, array $paths, Closure|string|null $fallback = null): Builder
    {
        $matchingPaths = [];
        foreach ($paths as $index => [$stage, $field]) {
            if (self::canMatchPath($stage, $field, $search)) {
                $matchingPaths[$index] = [$stage, $field];
            }
        }
        $fallbackQuery = null;
        if ($fallback instanceof Closure) {
            $fallbackQuery = $query->getModel()->newQueryWithoutScopes();
            $fallback($fallbackQuery);
            if (self::isImpossible($fallbackQuery)) {
                $fallbackQuery = null;
            }
        }
        if ($matchingPaths === [] && $fallbackQuery === null && (! is_string($fallback) || ! self::contains($fallback, $search))) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $matches) use ($search, $paths, $matchingPaths, $fallback, $fallbackQuery): void {
            $matches->whereRaw('1 = 0');
            foreach ($matchingPaths as $index => [$stage, $field]) {
                $matches->orWhere(function (Builder $label) use ($search, $paths, $index, $stage, $field): void {
                    foreach (array_slice($paths, 0, $index) as [$priorStage, $priorField]) {
                        $label->whereNot(fn (Builder $prior): Builder => self::applyStageText($prior, $priorStage, $priorField, ''));
                    }
                    self::applyStageText($label, $stage, $field, $search);
                });
            }
            if ($fallbackQuery !== null || (is_string($fallback) && self::contains($fallback, $search))) {
                $matches->orWhere(function (Builder $defaults) use ($paths, $fallbackQuery): void {
                    foreach ($paths as [$stage, $field]) {
                        $defaults->whereNot(fn (Builder $label): Builder => self::applyStageText($label, $stage, $field, ''));
                    }
                    if ($fallbackQuery !== null) {
                        $defaults->addNestedWhereQuery($fallbackQuery->getQuery());
                    }
                });
            }
        });
    }

    private static function contains(string $label, string $search): bool
    {
        return str_contains(CaseInsensitiveSearch::normalize($label), CaseInsensitiveSearch::normalize($search));
    }

    private static function canMatchPath(string $type, string $path, string $search): bool
    {
        if (self::$reviewStageSets === null || $search === '') {
            return true;
        }
        $types = $type === 'review' ? ['review', 'approval'] : [$type];
        foreach (self::$reviewStageSets as $stages) {
            foreach ($stages as $stage) {
                $stage = is_array($stage) && is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
                if (! is_array($stage) || ! in_array($stage['stage_type'] ?? null, $types, true)) {
                    continue;
                }
                if (self::contains(self::localizedText(data_get($stage, $path)), $search)) {
                    return true;
                }
                break;
            }
        }

        return false;
    }

    /** Constant folding for query branches constructed from impossible label matches. */
    public static function isImpossible(Builder $query): bool
    {
        return self::constantWhere($query->getQuery()->wheres) === false;
    }

    private static function constantWhere(array $wheres): ?bool
    {
        $or = false;
        $and = true;
        $first = true;
        foreach ($wheres as $where) {
            $value = null;
            if ($where['type'] === 'Raw' && is_string($where['sql'])) {
                $sql = str_replace(' ', '', trim($where['sql']));
                $value = $sql === '1=0' ? false : ($sql === '1=1' ? true : null);
            } elseif ($where['type'] === 'Nested') {
                $value = self::constantWhere($where['query']->wheres);
            }
            if (str_contains($where['boolean'], 'not') && $value !== null) {
                $value = ! $value;
            }
            if (! $first && str_starts_with($where['boolean'], 'or')) {
                $or = $or === true || $and === true ? true : ($or === false && $and === false ? false : null);
                $and = $value;
            } else {
                $and = $and === false || $value === false ? false : ($and === true && $value === true ? true : null);
            }
            $first = false;
        }

        return $or === true || $and === true ? true : ($or === false && $and === false ? false : null);
    }

    private static function legacyReview(Builder $query, string $search): void
    {
        LegacyWorkflowStatusSearch::apply($query, $search);
    }

    private static function statusIn(Builder $query, string $column, array $values, bool $not = false): Builder
    {
        return LegacyWorkflowStatusSearch::statusIn($query, $column, $values, $not);
    }

    private static function paid(Builder $query): Builder
    {
        return LegacyWorkflowStatusSearch::paid($query);
    }

    private static function notified(Builder $query): Builder
    {
        return LegacyWorkflowStatusSearch::notified($query);
    }

    private static function defaultReview(Builder $query, string $search): Builder
    {
        return LegacyWorkflowStatusSearch::defaultReview($query, $search);
    }

    private static function submissionVisible(Builder $query): Builder
    {
        return LegacyWorkflowStatusSearch::submissionVisible($query);
    }

    private static function submissionOrDefault(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $labels) use ($search): void {
            $labels->where(function (Builder $submission) use ($search): void {
                self::submissionVisible($submission);
                self::applyStageText($submission, 'form_submission', 'submission_messages.candidate_status_message', $search);
            })->orWhere(function (Builder $fallback) use ($search): void {
                $fallback->whereNot(fn (Builder $submission): Builder => self::submissionVisible($submission));
                self::defaultReview($fallback, $search);
            });
        });
    }

    private static function notTrue(Builder $query, string $column): Builder
    {
        return $query->where(fn (Builder $value): Builder => $value->whereNull($column)->orWhere($column, '!=', true));
    }

    private static function orderedActive(Builder $query): Builder
    {
        return $query->where(function (Builder $active): void {
            $active->where('data->workflow_progress->finished', true)
                ->orWhere(function (Builder $current): void {
                    $current->where('data->workflow_progress->submitted', true)
                        ->whereIn('data->workflow_progress->current_type', array_column(WorkflowStageType::cases(), 'value'));
                    self::statusIn($current, 'review_status', ['draft', 'rejected', 'failed'], not: true);
                    self::statusIn($current, 'data->registration_status', ['draft', 'rejected', 'failed'], not: true);
                });
        });
    }

    private static function stageTypeAt(Builder $query, int $index, string $type): Builder
    {
        $types = $type === 'review' ? ['review', 'approval'] : [$type];

        return $query->where(fn (Builder $stage): Builder => $stage
            ->whereIn("workflow_snapshot->stages[{$index}]->data->stage_type", $types)
            ->orWhereIn("workflow_snapshot->stages[{$index}]->stage_type", $types));
    }

    private static function currentStage(Builder $query, string $type): Builder
    {
        return $query->where(function (Builder $positions) use ($type): void {
            $positions->whereRaw('1 = 0');
            foreach (array_keys(WorkflowStageType::cases()) as $index) {
                $positions->orWhere(function (Builder $position) use ($index, $type): void {
                    self::stageTypeAt($position, $index, $type);
                    $position->where(function (Builder $cursor) use ($index): void {
                        $cursor->where(fn (Builder $value): Builder => $value->where('data->workflow_progress->current_index', $index)->orWhere('data->workflow_progress->current_index', (string) $index))
                            ->orWhere(function (Builder $last) use ($index): void {
                                $next = $index + 1;
                                $last->whereNull('data->workflow_progress->current_index')
                                    ->whereNull("workflow_snapshot->stages[{$next}]");
                            });
                    });
                });
            }
        });
    }

    private static function stageCompleted(Builder $query, string $type, bool $completed = true): Builder
    {
        $condition = static function (Builder $positions) use ($type): void {
            $positions->whereRaw('1 = 0');
            foreach (array_keys(WorkflowStageType::cases()) as $index) {
                $positions->orWhere(function (Builder $position) use ($index, $type): void {
                    self::stageTypeAt($position, $index, $type);
                    $position->where(fn (Builder $time): Builder => $time
                        ->whereNotNull("data->workflow_progress->completed_at[{$index}]")
                        ->orWhereNotNull("data->workflow_progress->completed_at->{$index}"));
                });
            }
        };

        return $completed ? $query->where($condition) : $query->whereNot($condition);
    }

    public static function applyStageText(Builder $query, string $stageType, string $fieldPath, string $search): Builder
    {
        if (! self::canMatchPath($stageType, $fieldPath, $search)) {
            return $query->whereRaw('1 = 0');
        }
        if (! preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/D', $fieldPath)) {
            throw new InvalidArgumentException('Invalid workflow status field path.');
        }

        $types = $stageType === WorkflowStageType::Review->value
            ? [WorkflowStageType::Review->value, WorkflowStageType::Approval->value]
            : [$stageType];
        $workflowIds = collect(self::$reviewConfigurations ?? WorkflowNotification::query()->get(['id', 'stages'])->all())
            ->filter(function (WorkflowNotification $workflow) use ($types, $fieldPath, $search): bool {
                foreach ($workflow->stages ?? [] as $stage) {
                    if (! is_array($stage)) {
                        continue;
                    }
                    $stage = is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
                    if (! is_array($stage) || ! in_array($stage['stage_type'] ?? null, $types, true)) {
                        continue;
                    }

                    $text = self::localizedText(data_get($stage, $fieldPath));

                    return $text !== '' && str_contains(CaseInsensitiveSearch::normalize($text), CaseInsensitiveSearch::normalize($search));
                }

                return false;
            })->map(fn (WorkflowNotification $workflow): int => (int) $workflow->getKey())->values()->all();

        $legacyForms = DB::table('workflow_notification_forms')->select('custom_form_id')->groupBy('custom_form_id');
        if ($workflowIds === []) {
            $legacyForms->whereRaw('1 = 0');
        } else {
            $legacyForms->havingRaw('MIN(workflow_notification_id) IN ('.implode(', ', array_fill(0, count($workflowIds), '?')).')', $workflowIds);
        }
        $formColumn = $query->getModel()->qualifyColumn('custom_form_id');
        if (! (self::$reviewHasSnapshot ?? Schema::hasColumn($query->getModel()->getTable(), 'workflow_snapshot'))) {
            return $query->whereIn($formColumn, $legacyForms);
        }

        $snapshot = $query->getQuery()->getGrammar()->wrap($query->getModel()->qualifyColumn('workflow_snapshot'));
        [$isSnapshot, $hasWorkflow, $stageText] = self::snapshotExpressions($query, $snapshot, $fieldPath, count($types));
        $stageBindings = $types;
        if (self::$reviewExpressions !== null) {
            $key = $stageType.'.'.$fieldPath;
            if (! isset(self::$reviewExpressions[$key])) {
                $alias = 'label_'.count(self::$reviewExpressions);
                self::$reviewExpressions[$key] = [$alias, $stageText, $types];
            }
            $stageText = 'workflow_review_labels.'.self::$reviewExpressions[$key][0];
            $stageBindings = [];
        }
        $terms = array_unique([Str::lower($search), CaseInsensitiveSearch::normalize($search)]);

        return $query->where(function (Builder $matches) use ($isSnapshot, $hasWorkflow, $stageText, $stageBindings, $terms, $legacyForms, $formColumn): void {
            $matches->where(function (Builder $frozen) use ($isSnapshot, $hasWorkflow, $stageText, $stageBindings, $terms): void {
                $frozen->whereRaw($isSnapshot)->whereRaw($hasWorkflow)
                    ->whereRaw("COALESCE(TRIM({$stageText}), '') <> ''", $stageBindings)
                    ->where(function (Builder $text) use ($stageText, $stageBindings, $terms): void {
                        foreach ($terms as $term) {
                            $text->orWhereRaw("LOWER({$stageText}) LIKE ?", [...$stageBindings, '%'.$term.'%']);
                        }
                    });
            })->orWhere(function (Builder $legacy) use ($isSnapshot, $legacyForms, $formColumn): void {
                $legacy->whereRaw("COALESCE(({$isSnapshot}), FALSE) = FALSE")
                    ->whereIn($formColumn, $legacyForms);
            });
        });
    }

    /** @return array{string, string, string} */
    private static function snapshotExpressions(Builder $query, string $snapshot, string $fieldPath, int $typeCount): array
    {
        $driver = $query->getConnection()->getDriverName();
        $types = implode(', ', array_fill(0, $typeCount, '?'));
        $path = explode('.', $fieldPath);
        $locale = app()->getLocale();
        $locale = preg_match('/^[A-Za-z0-9_-]+$/D', $locale) ? $locale : 'en';
        $locales = array_unique([$locale, 'km', 'kh', 'en']);

        if ($driver === 'pgsql') {
            $json = "CAST({$snapshot} AS JSONB)";
            $isSnapshot = "jsonb_typeof({$json}) IN ('object', 'array')";
            $hasWorkflow = "{$json}->>'has_workflow' = 'true'";
            $stages = "jsonb_array_elements(CASE WHEN jsonb_typeof({$json}->'stages') = 'array' THEN {$json}->'stages' ELSE '[]'::jsonb END) WITH ORDINALITY AS workflow_search_stage(value, position)";
            $extract = static fn (array $keys): string => "workflow_search_stage.value #>> '{".implode(',', $keys)."}'";
            $wrapped = "jsonb_typeof(workflow_search_stage.value->'data') IN ('object', 'array')";
            $order = 'workflow_search_stage.position';
        } else {
            $mysql = in_array($driver, ['mysql', 'mariadb'], true);
            if (! $mysql) {
                $snapshot = "CASE WHEN json_valid({$snapshot}) THEN {$snapshot} ELSE 'null' END";
            }
            $jsonType = $mysql ? 'JSON_TYPE' : 'json_type';
            $value = $mysql ? 'workflow_search_stage.value' : "CASE WHEN workflow_search_stage.type IN ('object', 'array') THEN workflow_search_stage.value ELSE '{}' END";
            $extractJson = static fn (array $keys): string => "json_extract({$value}, '$.".implode('.', $keys)."')";
            $extract = $mysql
                ? static fn (array $keys): string => 'JSON_UNQUOTE('.$extractJson($keys).')'
                : $extractJson;
            $isSnapshot = "{$jsonType}({$snapshot}) IN (".($mysql ? "'OBJECT', 'ARRAY'" : "'object', 'array'").')';
            $hasWorkflow = $mysql
                ? "JSON_UNQUOTE(JSON_EXTRACT({$snapshot}, '$.has_workflow')) = 'true'"
                : "json_extract({$snapshot}, '$.has_workflow') = 1";
            $wrapped = $mysql
                ? "JSON_TYPE(JSON_EXTRACT(workflow_search_stage.value, '$.data')) IN ('OBJECT', 'ARRAY')"
                : "json_type({$value}, '$.data') IN ('object', 'array')";
            if ($mysql) {
                $stages = "JSON_TABLE(CASE WHEN JSON_TYPE(JSON_EXTRACT({$snapshot}, '$.stages')) = 'ARRAY' THEN JSON_EXTRACT({$snapshot}, '$.stages') ELSE JSON_ARRAY() END, '$[*]' COLUMNS (position FOR ORDINALITY, value JSON PATH '$')) AS workflow_search_stage";
                $order = 'workflow_search_stage.position';
            } else {
                $stages = "json_each(CASE WHEN json_type({$snapshot}, '$.stages') = 'array' THEN json_extract({$snapshot}, '$.stages') ELSE '[]' END) AS workflow_search_stage";
                $order = 'CAST(workflow_search_stage.key AS INTEGER)';
            }
        }

        $stageType = 'CASE WHEN '.$wrapped.' THEN '.$extract(['data', 'stage_type']).' ELSE '.$extract(['stage_type']).' END';
        $textFor = static function (array $prefix) use ($extract, $path, $locales): string {
            $values = array_map(static fn (string $locale): string => $extract([...$prefix, ...$path, $locale]), $locales);
            $values[] = $extract([...$prefix, ...$path]);

            return 'COALESCE('.implode(', ', $values).')';
        };
        $field = 'CASE WHEN '.$wrapped.' THEN '.$textFor(['data']).' ELSE '.$textFor([]).' END';
        $cast = in_array($driver, ['mysql', 'mariadb'], true) ? 'CHAR' : 'TEXT';
        $stageText = "CAST((SELECT {$field} FROM {$stages} WHERE {$stageType} IN ({$types}) ORDER BY {$order} LIMIT 1) AS {$cast})";

        return [$isSnapshot, $hasWorkflow, $stageText];
    }

    private static function localizedText(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[app()->getLocale()] ?? $value['km'] ?? $value['kh'] ?? $value['en'] ?? '';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
