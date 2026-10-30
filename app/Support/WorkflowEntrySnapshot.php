<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The workflow an application started with, retained for its entire lifetime. */
final class WorkflowEntrySnapshot
{
    public static function forForm(int|string|null $formId): array
    {
        $workflow = filled($formId) ? WorkflowNotification::forForm($formId) : null;

        return [
            'schema_version' => 1,
            'captured_at' => now()->toIso8601String(),
            'workflow_id' => $workflow?->getKey(),
            'name' => $workflow?->name,
            'stages' => $workflow?->stages ?? [],
            'has_workflow' => $workflow !== null,
            'requires_payment' => $workflow?->hasStageType(WorkflowStageType::Payment) ?? false,
        ];
    }

    public static function capture(CustomFormEntry $entry): void
    {
        if (! Schema::hasColumn($entry->getTable(), 'workflow_snapshot')) {
            return;
        }

        if ($entry->exists) {
            $original = $entry->getRawOriginal('workflow_snapshot');

            // Updates using a partial model must not replace a stored snapshot.
            if ($original === null) {
                $original = DB::table($entry->getTable())
                    ->where($entry->getKeyName(), $entry->getKey())
                    ->value('workflow_snapshot');
            }

            if ($original !== null) {
                $entry->workflow_snapshot = self::decode($original);

                return;
            }
        }

        $entry->workflow_snapshot = self::forForm($entry->custom_form_id);
    }

    public static function workflowFor(CustomFormEntry $entry): ?WorkflowNotification
    {
        $snapshot = $entry->workflow_snapshot;

        if ($snapshot === null
            && $entry->exists
            && Schema::hasColumn($entry->getTable(), 'workflow_snapshot')) {
            $snapshot = self::decode(DB::table($entry->getTable())
                ->where($entry->getKeyName(), $entry->getKey())
                ->value('workflow_snapshot'));
        }

        // Older installations can deploy this code before running migrations.
        if (! is_array($snapshot)) {
            return filled($entry->custom_form_id)
                ? WorkflowNotification::forForm($entry->custom_form_id)
                : null;
        }

        // An explicit static workflow stays static after a template is attached.
        if (! ($snapshot['has_workflow'] ?? false)) {
            return null;
        }

        return (new WorkflowNotification)->forceFill([
            'id' => $snapshot['workflow_id'] ?? null,
            'name' => $snapshot['name'] ?? null,
            'stages' => $snapshot['stages'] ?? [],
        ]);
    }

    private static function decode(mixed $snapshot): ?array
    {
        if (is_string($snapshot)) {
            $snapshot = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);
        }

        return is_array($snapshot) ? $snapshot : null;
    }
}
