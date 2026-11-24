<?php

namespace App\Services;

use App\Enums\WorkflowStageType;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\AuditLogger;
use App\Support\CustomFormRoleAccess;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomFormAuthorization;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Execute a frozen generic button without invoking a built-in business action. */
final class WorkflowCustomButtonService
{
    public function buttonsFor(CustomFormEntry $entry): array
    {
        if (! WorkflowStageProgression::enabled($entry) || WorkflowStageProgression::currentType($entry) === null) {
            return [];
        }

        $stage = WorkflowStageProgression::currentStage($entry);
        $index = $stage['_index'] ?? null;
        if (! is_int($index)) {
            return [];
        }

        return array_values(array_map(
            fn (array $button): array => [...$button, '_stage_index' => $index],
            array_filter((array) ($stage['buttons'] ?? []), fn (mixed $button): bool => is_array($button)
                && array_key_exists('action_key', $button) && $button['action_key'] === null
                && ! array_key_exists($index.':'.($button['id'] ?? ''), (array) data_get($entry->data, 'workflow_custom_button_events', []))),
        ));
    }

    public function canExecute(CustomFormEntry $entry, int $stageIndex, int $buttonId, ?User $user = null): bool
    {
        $user ??= auth()->user();
        if (! $user instanceof User || $user->registration_type === 'student' || ! $user->is_active
            || ! $entry->exists || $stageIndex < 0 || $buttonId < 1) {
            return false;
        }

        if (CustomFormRoleAccess::canAccessForm($user, $entry->custom_form_id) === false) {
            return false;
        }
        $formView = CustomFormAuthorization::checkPermission($user, 'view', (int) $entry->custom_form_id, $entry);
        if ($formView === false || ! ($formView === true || $user->can('ViewAny:CustomFormEntry') || Gate::forUser($user)->allows('view', $entry))) {
            return false;
        }

        $button = collect($this->buttonsFor($entry))->first(fn (array $item): bool => (int) $item['_stage_index'] === $stageIndex && (int) ($item['id'] ?? 0) === $buttonId);
        if ($button === null) {
            return false;
        }

        $stage = WorkflowStageProgression::currentStage($entry);
        $roles = WorkflowNotification::normalizeResponsibleRoles($stage['responsible_role'] ?? null);

        return $roles === []
            ? $user->hasEffectiveRole('admin')
            : $user->hasEffectiveRole($roles);
    }

    /** Only a recorded click on a frozen generic button can supply this display label. */
    public function statusText(CustomFormEntry $entry): ?string
    {
        if (! WorkflowStageProgression::enabled($entry)) {
            return null;
        }
        $status = data_get($entry->data, 'workflow_custom_status');
        if (! is_array($status) || ! is_int($status['stage_index'] ?? null) || ! is_int($status['button_id'] ?? null)) {
            return null;
        }
        $index = $status['stage_index'];
        $buttonId = $status['button_id'];
        $event = data_get($entry->data, 'workflow_custom_button_events', [])[$index.':'.$buttonId] ?? null;
        $text = $status['text'] ?? null;
        if (! is_array($event) || ! is_string($text) || trim($text) === '' || ! is_string($status['at'] ?? null) || $status['at'] === ''
            || ($event['status'] ?? null) !== $text || ($event['at'] ?? null) !== ($status['at'] ?? null)) {
            return null;
        }
        $stages = collect(WorkflowNotification::forEntry($entry)?->stages ?? [])
            ->map(fn (mixed $stage): array => is_array($stage) ? ($stage['data'] ?? $stage) : [])
            ->filter(fn (array $stage): bool => WorkflowStageType::tryFrom((string) ($stage['stage_type'] ?? '')) !== null)
            ->values();
        $stage = $stages->get($index);
        $button = collect($stage['buttons'] ?? [])->first(fn (mixed $item): bool => is_array($item)
            && (int) ($item['id'] ?? 0) === $buttonId && array_key_exists('action_key', $item) && $item['action_key'] === null);
        $configured = ($stage['stage_type'] ?? null) === WorkflowStageType::Custom->value
            ? data_get($stage, 'submission_messages.candidate_status_message') : ($stage['status_message'] ?? null);

        return $button !== null && is_scalar($configured) && trim((string) $configured) === $text ? $text : null;
    }

    public function execute(CustomFormEntry $entry, int $stageIndex, int $buttonId): bool
    {
        if (! $entry->exists) {
            return false;
        }

        $changed = DB::transaction(function () use ($entry, $stageIndex, $buttonId): bool {
            $locked = CustomFormEntry::query()->lockForUpdate()->find($entry->getKey());
            if ($locked === null || ! $this->canExecute($locked, $stageIndex, $buttonId)) {
                return false;
            }

            $stage = WorkflowStageProgression::currentStage($locked);
            $custom = ($stage['stage_type'] ?? null) === WorkflowStageType::Custom->value;
            $text = $custom
                ? data_get($stage, 'submission_messages.candidate_status_message')
                : ($stage['status_message'] ?? null);
            $text = is_scalar($text) ? trim((string) $text) : '';
            $data = is_array($locked->data) ? $locked->data : [];
            $at = now()->toIso8601String();
            $data['workflow_custom_button_events'] = is_array($data['workflow_custom_button_events'] ?? null) ? $data['workflow_custom_button_events'] : [];
            $data['workflow_custom_button_events'][$stageIndex.':'.$buttonId] = ['at' => $at, 'by' => auth()->id(), 'status' => $text];
            if ($text !== '') {
                $data['workflow_custom_status'] = ['text' => $text, 'stage_index' => $stageIndex, 'button_id' => $buttonId, 'at' => $at];
            }

            DB::table($locked->getTable())->where($locked->getKeyName(), $locked->getKey())->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
            $locked->refresh();

            if ($custom && ! WorkflowStageProgression::completeCustomStage($locked, $stageIndex)) {
                throw new \LogicException('The custom workflow stage changed while its button was executing.');
            }

            AuditLogger::log(
                action: 'workflow_custom_button', auditable: $locked,
                oldValues: [], newValues: ['status_message' => $text],
                description: 'A custom workflow button updated its configured status and messages.',
                metadata: ['workflow_stage_index' => $stageIndex, 'workflow_button_id' => $buttonId, 'automatic' => false],
            );
            DB::afterCommit(fn () => WorkflowStageMessages::notifyCustomButton($locked, $stage, $stageIndex, $buttonId));

            return true;
        });

        $entry->refresh();

        return $changed;
    }
}
