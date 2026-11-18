<?php

namespace App\Support;

use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Search the label selected by the existing status-driven application workflow. */
final class LegacyWorkflowStatusSearch
{
    private static ?array $searchMetadata = null;

    public static function beginSearchMetadata(): ?array
    {
        $previous = self::$searchMetadata;
        self::$searchMetadata = [];

        return $previous;
    }

    public static function endSearchMetadata(?array $previous): void
    {
        self::$searchMetadata = $previous;
    }

    private static function hasTable(string $table): bool
    {
        if (self::$searchMetadata === null) {
            return Schema::hasTable($table);
        }

        return self::$searchMetadata['table:'.$table] ??= Schema::hasTable($table);
    }

    private static function hasColumn(string $table, string $column): bool
    {
        if (self::$searchMetadata === null) {
            return Schema::hasColumn($table, $column);
        }

        return self::$searchMetadata['column:'.$table.'.'.$column] ??= Schema::hasColumn($table, $column);
    }

    public static function apply(Builder $query, string $search): Builder
    {
        $choices = [
            [fn (Builder $q): Builder => self::submissionVisible($q), fn (Builder $q): Builder => WorkflowStatusSearch::applyStageText($q, 'form_submission', 'submission_messages.candidate_status_message', $search)],
        ];
        foreach (['failed' => 'reject', 'other' => 'send_back'] as $status => $action) {
            $choices[] = [
                function (Builder $q) use ($status, $action): void {
                    self::stage($q, 'rejected');
                    self::statusIn($q, 'review_status', ['failed'], not: $status === 'other');
                    WorkflowStatusSearch::applyStageText($q, 'review', "review_actions.{$action}.status_message", '');
                },
                fn (Builder $q): Builder => WorkflowStatusSearch::applyStageText($q, 'review', "review_actions.{$action}.status_message", $search),
            ];
        }
        $choices[] = [
            function (Builder $q): void {
                self::stage($q, 'payment');
                $q->where(function (Builder $labels): void {
                    WorkflowStatusSearch::applyStageText($labels, 'review', 'review_actions.accept.status_message', '');
                    $labels->orWhere(fn (Builder $unpaid): Builder => WorkflowStatusSearch::applyStageText($unpaid, 'payment', 'payment_statuses.unpaid_status_message', ''));
                });
            },
            fn (Builder $q): Builder => WorkflowStatusSearch::firstText($q, $search, [['review', 'review_actions.accept.status_message'], ['payment', 'payment_statuses.unpaid_status_message']]),
        ];
        foreach (['review', 'rejected'] as $type) {
            $choices[] = [
                function (Builder $q) use ($type): void {
                    self::stage($q, $type);
                    self::workflowTextVisible($q);
                },
                fn (Builder $q): Builder => WorkflowStatusSearch::firstText($q, $search, [[$type, 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search)),
            ];
        }
        $choices[] = [
            fn (Builder $q): Builder => self::stage($q, 'payment'),
            fn (Builder $q): Builder => WorkflowStatusSearch::firstText($q, $search, [['payment', 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search)),
        ];
        $choices[] = [fn (Builder $q): Builder => self::stage($q, 'awaiting_results'), fn (Builder $q): Builder => self::resultText($q, $search)];
        $choices[] = [fn (Builder $q): Builder => self::stage($q, 'completed'), fn (Builder $q): Builder => self::completedText($q, $search)];

        return $query->where(fn (Builder $matches): Builder => self::choose($matches, $choices, 0, $search));
    }

    /** Match the first visible branch, rather than any future configured label. */
    private static function choose(Builder $query, array $choices, int $index, string $search): Builder
    {
        if (! isset($choices[$index])) {
            return self::defaultReview($query, $search);
        }
        [$condition, $match] = $choices[$index];
        $matchQuery = $query->getModel()->newQueryWithoutScopes();
        $match($matchQuery);
        $nextQuery = $query->getModel()->newQueryWithoutScopes();
        self::choose($nextQuery, $choices, $index + 1, $search);
        $matchImpossible = WorkflowStatusSearch::isImpossible($matchQuery);
        $nextImpossible = WorkflowStatusSearch::isImpossible($nextQuery);
        if ($matchImpossible && $nextImpossible) {
            return $query->whereRaw('1 = 0');
        }
        if ($matchImpossible) {
            $query->whereNot($condition)->addNestedWhereQuery($nextQuery->getQuery());

            return $query;
        }
        if ($nextImpossible) {
            $query->where($condition)->addNestedWhereQuery($matchQuery->getQuery());

            return $query;
        }

        return $query->where(function (Builder $decision) use ($condition, $matchQuery, $nextQuery): void {
            $decision->where(function (Builder $branch) use ($condition, $matchQuery): void {
                $branch->where($condition)->addNestedWhereQuery($matchQuery->getQuery());
            })->orWhere(function (Builder $next) use ($condition, $nextQuery): void {
                $next->whereNot($condition)->addNestedWhereQuery($nextQuery->getQuery());
            });
        });
    }

    private static function resultText(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $results) use ($search): void {
            $results->where(function (Builder $passed) use ($search): void {
                self::passedResult($passed);
                $passed->where(function (Builder $labels) use ($search): void {
                    $labels->where(function (Builder $completed) use ($search): void {
                        WorkflowStatusSearch::applyStageText($completed, 'completed', 'status_message', '');
                        self::completedText($completed, $search);
                    })->orWhere(function (Builder $ordinary) use ($search): void {
                        $ordinary->whereNot(fn (Builder $completed): Builder => WorkflowStatusSearch::applyStageText($completed, 'completed', 'status_message', ''));
                        WorkflowStatusSearch::firstText($ordinary, $search, [['awaiting_results', 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search));
                    });
                });
            })->orWhere(function (Builder $waiting) use ($search): void {
                $waiting->whereNot(fn (Builder $passed): Builder => self::passedResult($passed));
                $choices = [[
                    function (Builder $paid): void {
                        self::statusIn($paid, 'review_status', ['approved', 'accepted']);
                        self::requiresPayment($paid);
                        self::paid($paid);
                        WorkflowStatusSearch::applyStageText($paid, 'payment', 'status_message', '');
                    },
                    fn (Builder $paid): Builder => WorkflowStatusSearch::applyStageText($paid, 'payment', 'status_message', $search),
                ], [
                    fn (Builder $configured): Builder => self::resultStatusesArray($configured),
                    fn (Builder $pending): Builder => WorkflowStatusSearch::firstText($pending, $search, [['awaiting_results', 'result_statuses.pending_status_message']], __('candidate_entrance_statistics.statuses.pending')),
                ], [
                    fn (Builder $always): Builder => $always->whereRaw('1 = 1'),
                    fn (Builder $ordinary): Builder => WorkflowStatusSearch::firstText($ordinary, $search, [['awaiting_results', 'status_message']], fn (Builder $fallback): Builder => self::defaultReview($fallback, $search)),
                ]];
                self::choose($waiting, $choices, 0, $search);
            });
        });
    }

    private static function completedText(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $labels) use ($search): void {
            $labels->where(function (Builder $configured) use ($search): void {
                WorkflowStatusSearch::applyStageText($configured, 'completed', 'status_message', '');
                $configured->where(function (Builder $release) use ($search): void {
                    $release->where(function (Builder $sent) use ($search): void {
                        self::notified($sent);
                        WorkflowStatusSearch::applyStageText($sent, 'completed', 'status_message', $search);
                    })->orWhere(function (Builder $held) use ($search): void {
                        $held->whereNot(fn (Builder $sent): Builder => self::notified($sent));
                        $held->where(function (Builder $state) use ($search): void {
                            $state->where(function (Builder $passed) use ($search): void {
                                self::passedResult($passed);
                                WorkflowStatusSearch::firstText($passed, $search, [['awaiting_results', 'status_message']], __('candidate_entrance_statistics.statuses.passed'));
                            })->orWhere(function (Builder $pending) use ($search): void {
                                $pending->whereNot(fn (Builder $passed): Builder => self::passedResult($passed));
                                WorkflowStatusSearch::firstText($pending, $search, [['awaiting_results', 'result_statuses.pending_status_message']], __('candidate_entrance_statistics.statuses.pending'));
                            });
                        });
                    });
                });
            })->orWhere(function (Builder $unconfigured) use ($search): void {
                $unconfigured->whereNot(fn (Builder $configured): Builder => WorkflowStatusSearch::applyStageText($configured, 'completed', 'status_message', ''));
                self::defaultReview($unconfigured, $search);
            });
        });
    }

    public static function statusIn(Builder $query, string $column, array $values, bool $not = false): Builder
    {
        $expression = self::text($query, $column);
        $values = array_map(static fn ($value): string => mb_strtolower(trim((string) $value)), $values);

        return $query->whereRaw("LOWER(TRIM(COALESCE({$expression}, ''))) ".($not ? 'NOT IN' : 'IN').' ('.implode(', ', array_fill(0, count($values), '?')).')', $values);
    }

    public static function paid(Builder $query): Builder
    {
        return $query->where(function (Builder $paid): void {
            $paid->whereExists(AmsPaymentRequest::paidForApplicationQuery()->toBase());
            if (self::hasTable('payments') && self::hasColumn('payments', 'custom_form_entry_id')) {
                $paid->orWhereExists(Payment::query()->selectRaw('1')
                    ->whereColumn('payments.custom_form_entry_id', 'custom_form_entries.id')->where('status_payt', 'paid')->toBase());
            }
        });
    }

    public static function notified(Builder $query, string $stage = 'completed'): Builder
    {
        if (! self::hasTable('notifications')) {
            return $query->whereRaw('1 = 0');
        }
        $grammar = $query->getQuery()->getGrammar();
        $entry = self::text($query, 'custom_form_entries.id');
        $entryInNotification = $grammar->wrap('notifications.data->viewData->workflow_entry_id');

        return $query->whereHas('creator', fn (Builder $owner): Builder => $owner->where('registration_type', 'student'))
            ->where(function (Builder $delivered) use ($stage, $entry, $entryInNotification): void {
                $delivered->whereExists(DB::table('notifications')->selectRaw('1')
                    ->where('notifiable_type', User::class)->whereColumn('notifiable_id', 'custom_form_entries.created_by')
                    ->whereRaw("{$entryInNotification} = {$entry}")
                    ->where('data->viewData->workflow_stage_type', $stage));
                if (self::hasTable('workflow_notification_deliveries')) {
                    $delivered->orWhereExists(DB::table('workflow_notification_deliveries')->selectRaw('1')
                        ->whereColumn('custom_form_entry_id', 'custom_form_entries.id')
                        ->whereColumn('user_id', 'custom_form_entries.created_by')->where('stage_type', $stage));
                }
            });
    }

    public static function configured(Builder $query): Builder
    {
        if (! self::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereIn('custom_form_id', DB::table('workflow_notification_forms')->select('custom_form_id'));
        }
        [$authoritative, $enabled] = self::snapshotConditions($query);

        return $query->where(function (Builder $workflows) use ($authoritative, $enabled): void {
            $workflows->whereRaw("COALESCE(({$authoritative}), FALSE) AND COALESCE(({$enabled}), FALSE)")
                ->orWhere(function (Builder $legacy) use ($authoritative): void {
                    $legacy->whereRaw("COALESCE(({$authoritative}), FALSE) = FALSE")
                        ->whereIn('custom_form_id', DB::table('workflow_notification_forms')->select('custom_form_id'));
                });
        });
    }

    public static function requiresPayment(Builder $query): Builder
    {
        return $query->whereDoesntHave('customForm', fn (Builder $profile): Builder => $profile->where('slug', 'profile'))
            ->where(function (Builder $payment): void {
                WorkflowStatusSearch::applyStageText($payment, 'payment', 'stage_type', '');
                $payment->orWhere(function (Builder $static): void {
                    $static->whereNot(fn (Builder $configured): Builder => self::configured($configured))
                        ->whereHas('customForm', fn (Builder $form): Builder => $form->where('requires_payment', true));
                });
            });
    }

    public static function submissionVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $submission): void {
            self::statusIn($submission, 'review_status', ['pending']);
            self::statusIn($submission, 'data->registration_status', ['draft'], not: true);
            $submission->whereDoesntHave('customForm', fn (Builder $profile): Builder => $profile->where('slug', 'profile'));
            WorkflowStatusSearch::applyStageText($submission, 'form_submission', 'submission_messages.candidate_status_message', '');
        });
    }

    public static function defaultReview(Builder $query, string $search): Builder
    {
        $labels = [
            'accepted' => __('candidate_entrance_statistics.statuses.accepted'),
            'paid' => __('candidate_entrance_statistics.statuses.paid'),
            'failed' => __('candidate_entrance_statistics.statuses.final_rejected'),
            'rejected' => __('candidate_entrance_statistics.statuses.rejected'),
            'draft' => __('student_profile.save_as_draft'),
            'pending' => __('candidate_entrance_statistics.statuses.pending'),
        ];

        return $query->where(function (Builder $matches) use ($labels, $search): void {
            $matches->whereRaw('1 = 0');
            foreach ($labels as $state => $label) {
                if (! str_contains(CaseInsensitiveSearch::normalize($label), CaseInsensitiveSearch::normalize($search))) {
                    continue;
                }
                $matches->orWhere(function (Builder $status) use ($state): void {
                    if ($state === 'paid') {
                        $status->where(fn (Builder $already): Builder => self::entryStatus($already, ['paid']))
                            ->orWhere(fn (Builder $paid): Builder => self::displayPaid($paid));
                    } elseif ($state === 'accepted') {
                        self::entryStatus($status, ['approved', 'accepted', 'passed']);
                        $status->whereNot(fn (Builder $paid): Builder => self::displayPaid($paid));
                    } elseif ($state === 'pending') {
                        self::entryStatus($status, ['approved', 'accepted', 'passed', 'paid', 'failed', 'rejected', 'draft'], not: true);
                    } else {
                        self::entryStatus($status, [$state]);
                    }
                });
            }
        });
    }

    private static function displayPaid(Builder $query): Builder
    {
        self::entryStatus($query, ['approved', 'accepted', 'passed']);

        return $query->where(function (Builder $display): void {
            self::paid($display);
            if (auth()->user()?->registration_type === 'student') {
                $display->orWhere(fn (Builder $free): Builder => $free->whereNot(fn (Builder $required): Builder => self::requiresPayment($required)));
            }
        });
    }

    private static function entryStatus(Builder $query, array $values, bool $not = false): Builder
    {
        $review = 'LOWER(COALESCE('.self::text($query, 'review_status').", 'pending'))";
        $registration = 'LOWER(COALESCE('.self::text($query, 'data->registration_status').", ''))";
        $status = "CASE WHEN {$review} = 'draft' OR {$registration} = 'draft' THEN 'draft' WHEN {$review} = 'pending' OR {$registration} = 'pending' THEN 'pending' ELSE COALESCE(NULLIF({$review}, ''), NULLIF({$registration}, ''), 'pending') END";

        return $query->whereRaw("({$status}) ".($not ? 'NOT IN' : 'IN').' ('.implode(', ', array_fill(0, count($values), '?')).')', $values);
    }

    private static function stage(Builder $query, string $type): Builder
    {
        return $query->where(function (Builder $stage) use ($type): void {
            self::notDraft($stage);
            if ($type === 'completed') {
                $stage->where(function (Builder $completed): void {
                    self::anyStatus($completed, ['completed'], includeCandidate: true);
                    $completed->orWhere(function (Builder $advanced): void {
                        self::afterReview($advanced);
                        self::resultReady($advanced);
                        $advanced->whereNot(fn (Builder $results): Builder => self::hasStage($results, 'awaiting_results'));
                        self::hasStage($advanced, 'completed');
                    });
                });

                return;
            }
            $stage->whereNot(fn (Builder $completed): Builder => self::anyStatus($completed, ['completed'], includeCandidate: true));
            if ($type === 'rejected') {
                self::anyStatus($stage, ['failed', 'rejected']);

                return;
            }
            $stage->whereNot(fn (Builder $rejected): Builder => self::anyStatus($rejected, ['failed', 'rejected']));
            if ($type === 'review') {
                self::reviewPending($stage);

                return;
            }
            $stage->whereNot(fn (Builder $pending): Builder => self::reviewPending($pending));
            if ($type === 'payment') {
                self::statusIn($stage, 'review_status', ['approved', 'accepted']);
                self::statusIn($stage, 'data->candidate_status', ['passed'], not: true);
                self::requiresPayment($stage);
                $stage->whereNot(fn (Builder $paid): Builder => self::paid($paid));
            } else {
                self::resultReady($stage);
                $stage->where(function (Builder $results): void {
                    self::hasStage($results, 'awaiting_results');
                    $results->orWhere(function (Builder $unconfigured): void {
                        $unconfigured->whereNot(fn (Builder $completed): Builder => self::hasStage($completed, 'completed'));
                    });
                });
            }
        });
    }

    private static function afterReview(Builder $query): Builder
    {
        self::notDraft($query);
        $query->whereNot(fn (Builder $completed): Builder => self::anyStatus($completed, ['completed'], includeCandidate: true))
            ->whereNot(fn (Builder $rejected): Builder => self::anyStatus($rejected, ['failed', 'rejected']))
            ->whereNot(fn (Builder $pending): Builder => self::reviewPending($pending));

        return $query;
    }

    private static function resultReady(Builder $query): Builder
    {
        return $query->where(function (Builder $ready): void {
            self::statusIn($ready, 'review_status', ['passed']);
            $ready->orWhere(fn (Builder $candidate): Builder => self::statusIn($candidate, 'data->candidate_status', ['passed']))
                ->orWhere(function (Builder $reviewed): void {
                    self::statusIn($reviewed, 'review_status', ['approved', 'accepted']);
                    $reviewed->where(function (Builder $payment): void {
                        $payment->whereNot(fn (Builder $required): Builder => self::requiresPayment($required))
                            ->orWhere(fn (Builder $paid): Builder => self::paid($paid));
                    });
                });
        });
    }

    private static function reviewPending(Builder $query): Builder
    {
        return $query->where(function (Builder $pending): void {
            self::statusIn($pending, 'review_status', ['', 'pending']);
            $pending->orWhere(fn (Builder $registration): Builder => self::statusIn($registration, 'data->registration_status', ['pending']));
        });
    }

    private static function notDraft(Builder $query): Builder
    {
        return $query->whereNot(fn (Builder $draft): Builder => self::anyStatus($draft, ['draft']));
    }

    private static function anyStatus(Builder $query, array $values, bool $includeCandidate = false): Builder
    {
        return $query->where(function (Builder $statuses) use ($values, $includeCandidate): void {
            self::statusIn($statuses, 'review_status', $values);
            $statuses->orWhere(fn (Builder $registration): Builder => self::statusIn($registration, 'data->registration_status', $values));
            if ($includeCandidate) {
                $statuses->orWhere(fn (Builder $candidate): Builder => self::statusIn($candidate, 'data->candidate_status', $values));
            }
        });
    }

    private static function passedResult(Builder $query): Builder
    {
        self::statusIn($query, 'data->candidate_status', ['pending'], not: true);

        return $query->where(function (Builder $passed): void {
            self::statusIn($passed, 'review_status', ['passed']);
            $passed->orWhere(fn (Builder $candidate): Builder => self::statusIn($candidate, 'data->candidate_status', ['passed']));
        });
    }

    private static function workflowTextVisible(Builder $query): Builder
    {
        if (auth()->user()?->registration_type === 'student') {
            return $query;
        }
        self::configured($query);

        return $query->whereNot(fn (Builder $payment): Builder => self::requiresPayment($payment));
    }

    private static function hasStage(Builder $query, string $type): Builder
    {
        return WorkflowStatusSearch::applyStageText($query, $type, 'stage_type', '');
    }

    private static function text(Builder $query, string $column): string
    {
        $cast = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR' : 'TEXT';

        return 'CAST('.$query->getQuery()->getGrammar()->wrap($column)." AS {$cast})";
    }

    /** @return array{string, string} */
    private static function snapshotConditions(Builder $query): array
    {
        $snapshot = $query->getQuery()->getGrammar()->wrap('workflow_snapshot');
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            return ["jsonb_typeof(CAST({$snapshot} AS JSONB)) IN ('object', 'array')", "CAST({$snapshot} AS JSONB)->>'has_workflow' = 'true'"];
        }
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return ["JSON_TYPE({$snapshot}) IN ('OBJECT', 'ARRAY')", "JSON_UNQUOTE(JSON_EXTRACT({$snapshot}, '$.has_workflow')) = 'true'"];
        }
        $safe = "CASE WHEN json_valid({$snapshot}) THEN {$snapshot} ELSE 'null' END";

        return ["json_type({$safe}) IN ('object', 'array')", "json_extract({$safe}, '$.has_workflow') = 1"];
    }

    private static function resultStatusesArray(Builder $query): Builder
    {
        $ids = WorkflowNotification::query()->get(['id', 'stages'])->filter(function (WorkflowNotification $workflow): bool {
            foreach ($workflow->stages ?? [] as $stage) {
                $stage = is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
                if (is_array($stage) && ($stage['stage_type'] ?? null) === 'awaiting_results') {
                    return is_array($stage['result_statuses'] ?? null);
                }
            }

            return false;
        })->modelKeys();
        $forms = DB::table('workflow_notification_forms')->select('custom_form_id')->groupBy('custom_form_id');
        $ids === [] ? $forms->whereRaw('1 = 0') : $forms->havingRaw('MIN(workflow_notification_id) IN ('.implode(', ', array_fill(0, count($ids), '?')).')', $ids);
        if (! self::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereIn('custom_form_id', $forms);
        }
        [$authoritative, $enabled] = self::snapshotConditions($query);
        $snapshot = $query->getGrammar()->wrap('workflow_snapshot');
        $driver = $query->getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            $rows = "jsonb_array_elements(CASE WHEN jsonb_typeof(CAST({$snapshot} AS JSONB)->'stages') = 'array' THEN CAST({$snapshot} AS JSONB)->'stages' ELSE '[]'::jsonb END) WITH ORDINALITY AS result_search_stage(value, position)";
            $type = "CASE WHEN jsonb_typeof(value->'data') IN ('object', 'array') THEN value->'data'->>'stage_type' ELSE value->>'stage_type' END";
            $kind = "jsonb_typeof(CASE WHEN jsonb_typeof(value->'data') IN ('object', 'array') THEN value->'data'->'result_statuses' ELSE value->'result_statuses' END)";
            $order = 'position';
            $arrayTypes = "'object', 'array'";
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = "JSON_TABLE(CASE WHEN JSON_TYPE(JSON_EXTRACT({$snapshot}, '$.stages')) = 'ARRAY' THEN JSON_EXTRACT({$snapshot}, '$.stages') ELSE JSON_ARRAY() END, '$[*]' COLUMNS (position FOR ORDINALITY, value JSON PATH '$')) AS result_search_stage";
            $wrapped = "JSON_TYPE(JSON_EXTRACT(value, '$.data')) IN ('OBJECT', 'ARRAY')";
            $type = "CASE WHEN {$wrapped} THEN JSON_UNQUOTE(JSON_EXTRACT(value, '$.data.stage_type')) ELSE JSON_UNQUOTE(JSON_EXTRACT(value, '$.stage_type')) END";
            $kind = "JSON_TYPE(CASE WHEN {$wrapped} THEN JSON_EXTRACT(value, '$.data.result_statuses') ELSE JSON_EXTRACT(value, '$.result_statuses') END)";
            $order = 'position';
            $arrayTypes = "'OBJECT', 'ARRAY'";
        } else {
            $safe = "CASE WHEN json_valid({$snapshot}) THEN {$snapshot} ELSE 'null' END";
            $rows = "json_each(CASE WHEN json_type({$safe}, '$.stages') = 'array' THEN json_extract({$safe}, '$.stages') ELSE '[]' END) AS result_search_stage";
            $value = "CASE WHEN result_search_stage.type IN ('object', 'array') THEN result_search_stage.value ELSE '{}' END";
            $wrapped = "json_type({$value}, '$.data') IN ('object', 'array')";
            $type = "CASE WHEN {$wrapped} THEN json_extract({$value}, '$.data.stage_type') ELSE json_extract({$value}, '$.stage_type') END";
            $kind = "CASE WHEN {$wrapped} THEN json_type({$value}, '$.data.result_statuses') ELSE json_type({$value}, '$.result_statuses') END";
            $order = 'CAST(result_search_stage.key AS INTEGER)';
            $arrayTypes = "'object', 'array'";
        }

        return $query->where(function (Builder $matches) use ($authoritative, $enabled, $rows, $type, $kind, $order, $arrayTypes, $forms): void {
            $matches->whereRaw("COALESCE(({$authoritative}), FALSE) AND COALESCE(({$enabled}), FALSE)")
                ->whereRaw("COALESCE((SELECT {$kind} FROM {$rows} WHERE {$type} = ? ORDER BY {$order} LIMIT 1) IN ({$arrayTypes}), FALSE)", ['awaiting_results'])
                ->orWhere(function (Builder $legacy) use ($authoritative, $forms): void {
                    $legacy->whereRaw("COALESCE(({$authoritative}), FALSE) = FALSE")->whereIn('custom_form_id', $forms);
                });
        });
    }
}
