<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/** Maps stage catalog buttons and settings onto workflow fields. */
final class WorkflowStageTypeSettings
{
    /** Only user-triggered buttons belong in the catalog picker; status keys remain supported for saved workflows. */
    public static function buttonActions(): array
    {
        $actions = self::allActions();
        unset($actions['unpaid'], $actions['pending']);
        $actions = array_replace(array_flip(['submitted', 'verify', 'accept', 'send_back', 'reject', 'paid', 'passed', 'notify']), $actions);

        foreach (array_keys($actions) as $key) {
            $actions[$key]['label'] = __('workflow_stage_types.button_actions.'.$key);
        }

        return $actions;
    }

    public static function allActions(): array
    {
        $actions = [];
        foreach (array_keys(WorkflowStageType::options()) as $behavior) {
            foreach (self::actions($behavior) as $key => $settings) {
                $actions[$key] = [
                    ...$settings,
                    'behavior' => $behavior,
                    'group' => __('workflow_notifications.stage_types.'.$behavior),
                ];
            }
        }

        return $actions;
    }

    /** A stage uses one existing execution type, inferred from its chosen buttons. */
    public static function behaviorForSelection(mixed $selection): ?string
    {
        if (! is_array($selection) || $selection === []) {
            return null;
        }

        $actions = self::allActions();
        $behavior = null;
        foreach ($selection as $key) {
            if (! is_string($key) || ! isset($actions[$key])) {
                return null;
            }
            if ($behavior !== null && $behavior !== $actions[$key]['behavior']) {
                return null;
            }
            $behavior = $actions[$key]['behavior'];
        }

        return $behavior;
    }

    /** A missing selection retains every existing action; subsets keep canonical order. */
    public static function selectedActions(string $behavior, mixed $selection = null): array
    {
        $actions = self::actions($behavior);

        return $selection === null
            ? $actions
            : array_intersect_key($actions, array_flip(is_array($selection) ? array_filter($selection, 'is_string') : []));
    }

    public static function actions(string $behavior): array
    {
        $keys = match ($behavior) {
            'form_submission' => ['submitted'],
            'review' => ['accept', 'send_back', 'reject'],
            'verification' => ['verify'],
            'payment' => ['unpaid', 'paid'],
            'awaiting_results' => ['pending', 'passed'],
            'completed' => ['notify'],
            default => [],
        };
        $icons = [
            'submitted' => 'heroicon-o-paper-airplane',
            'accept' => 'heroicon-o-check-circle',
            'send_back' => 'heroicon-o-arrow-uturn-left',
            'reject' => 'heroicon-o-x-circle',
            'verify' => 'heroicon-o-shield-check',
            'unpaid' => 'heroicon-o-clock',
            'paid' => 'heroicon-o-banknotes',
            'pending' => 'heroicon-o-clock',
            'passed' => 'heroicon-o-check-circle',
            'notify' => 'heroicon-o-bell-alert',
        ];
        $actions = [];

        foreach ($keys as $key) {
            $actions[$key] = [
                'label' => __($behavior === 'review'
                    ? 'workflow_notifications.review_actions.'.$key.'.title'
                    : 'workflow_stage_types.config_actions.'.$key),
                'icon' => $icons[$key],
            ];
        }

        return $actions;
    }

    public static function fields(string $behavior, string $action): array
    {
        if (! array_key_exists($action, self::actions($behavior))) {
            return [];
        }

        if ($behavior === 'review') {
            $prefix = 'review_actions.'.$action.'.';
            $placeholder = 'placeholders.review_actions.'.$action.'.';

            return [
                self::field($prefix.'status_message', 'fields.shared_status_message', $placeholder.'status_message'),
                self::field($prefix.'alert_message', 'fields.alert_message', 'placeholders.alert_message'),
                self::field($prefix.'notification_message', 'review_actions.'.$action.'.notification_message', $placeholder.'notification_message', 'textarea', 'candidate', 1000),
                self::field($prefix.'staff_notification_message', 'fields.staff_notification_message', $placeholder.'staff_notification_message', 'textarea', 'staff', 1000),
                self::field($prefix.'staff_recipient_roles', 'fields.staff_recipient_roles', 'placeholders.staff_recipient_roles', 'roles', 'staff'),
            ];
        }

        if ($behavior === 'form_submission') {
            return [
                self::field('submission_messages.candidate_status_message', 'fields.shared_status_message', 'placeholders.submission_status'),
                self::field('alert_message', 'fields.alert_message', 'placeholders.alert_message'),
                self::field('submission_messages.candidate_notification_message', 'fields.notification_message', 'placeholders.submission_candidate_notification', 'textarea', 'candidate', 1000),
                self::field('submission_messages.staff_notification_message', 'fields.staff_notification_message', 'placeholders.submission_staff_notification', 'textarea', 'staff', 1000),
                self::field('submission_messages.staff_recipient_roles', 'fields.staff_recipient_roles', 'placeholders.staff_recipient_roles', 'roles', 'staff'),
                self::field('submission_messages.resubmission_staff.title', 'fields.resubmission_staff_title', 'placeholders.resubmission_staff_title', 'text', 'staff'),
                self::field('submission_messages.resubmission_staff.message', 'fields.resubmission_staff_message', 'placeholders.resubmission_staff_message', 'textarea', 'staff', 1000),
                self::field('submission_messages.resubmission_staff.staff_recipient_roles', 'fields.resubmission_staff_recipient_roles', 'placeholders.staff_recipient_roles', 'roles', 'staff'),
            ];
        }

        if ($behavior === 'payment' && $action === 'unpaid') {
            return [self::field('payment_statuses.unpaid_status_message', 'fields.unpaid_status_message', 'placeholders.unpaid_status_message')];
        }

        if ($behavior === 'awaiting_results' && $action === 'pending') {
            return [
                self::field('result_statuses.pending_status_message', 'fields.waiting_result_status_message', 'placeholders.waiting_result_status_message'),
                self::field('result_statuses.pending_alert_message', 'fields.pending_alert_message', 'placeholders.pending_alert_message'),
            ];
        }

        $statusLabel = match ($behavior) {
            'payment' => 'fields.paid_status_message',
            'awaiting_results' => 'fields.passed_result_status_message',
            default => 'fields.shared_status_message',
        };
        $placeholder = 'placeholders.stage_audiences.'.$behavior.'.';

        $fields = [
            self::field('status_message', $statusLabel, $placeholder.'status_message'),
            self::field('alert_message', 'fields.alert_message', 'placeholders.alert_message'),
            self::field('notification_message', 'fields.notification_message', $placeholder.'notification_message', 'textarea', 'candidate', 1000),
            self::field('audience_messages.staff_notification_message', 'fields.staff_notification_message', $placeholder.'staff_notification_message', 'textarea', 'staff', 1000),
            self::field('audience_messages.staff_recipient_roles', 'fields.staff_recipient_roles', 'placeholders.staff_recipient_roles', 'roles', 'staff'),
        ];

        if ($behavior === 'awaiting_results') {
            $fields[] = [
                ...self::field('statistics_menu', 'fields.statistics_menu', 'placeholders.statistics_menu', 'select'),
                'options' => StatisticsMenuOptions::options(),
            ];
        } elseif ($behavior === 'completed') {
            $fields[] = [
                ...self::field('passed_result_menu', 'fields.passed_result_menu', 'placeholders.passed_result_menu', 'select'),
                'options' => PassedResultMenuOptions::options(),
            ];
        }

        return $fields;
    }

    public static function rules(string $behavior, string $prefix = 'stage_settings'): array
    {
        $fields = self::allFields($behavior);
        $children = $prefix === '' ? [] : [$prefix => []];

        foreach ($fields as $field) {
            $parent = $prefix;
            foreach (explode('.', $field['path']) as $part) {
                if ($parent !== '') {
                    $children[$parent][$part] = true;
                }
                $parent = $parent === '' ? $part : $parent.'.'.$part;
            }
        }

        $rules = [];
        foreach ($children as $path => $keys) {
            $rules[$path] = $keys === []
                ? ['nullable', 'array', 'size:0']
                : ['nullable', 'array:'.implode(',', array_keys($keys))];
        }

        $allowedRoles = null;
        foreach ($fields as $field) {
            $path = $prefix === '' ? $field['path'] : $prefix.'.'.$field['path'];

            if ($field['type'] === 'roles') {
                $rules[$path] = ['nullable', 'array'];
                $allowedRoles ??= array_keys(WorkflowStaffRecipients::roleOptions());
                $rules[$path.'.*'] = ['string', Rule::in($allowedRoles)];
            } else {
                $rules[$path] = ['nullable', 'string', 'max:'.$field['max']];
                if ($field['type'] === 'select') {
                    $rules[$path][] = Rule::in(array_keys($field['options']));
                }
            }
        }

        return $rules;
    }

    public static function onlyAllowed(string $behavior, array $settings): array
    {
        $allowed = [];

        foreach (self::allFields($behavior) as $field) {
            if (Arr::has($settings, $field['path'])) {
                Arr::set($allowed, $field['path'], Arr::get($settings, $field['path']));
            }
        }

        return $allowed;
    }

    private static function allFields(string $behavior): array
    {
        $fields = [];
        foreach (array_keys(self::actions($behavior)) as $action) {
            foreach (self::fields($behavior, $action) as $field) {
                $fields[$field['path']] = $field;
            }
        }

        return array_values($fields);
    }

    private static function field(string $path, string $label, string $placeholder, string $type = 'text', string $audience = 'general', int $max = 255): array
    {
        return [
            'path' => $path,
            'label' => __('workflow_notifications.'.$label),
            'placeholder' => __('workflow_notifications.'.$placeholder),
            'type' => $type,
            'max' => $max,
            'audience' => $audience,
        ];
    }
}
