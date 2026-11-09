<?php

namespace App\Support;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use Illuminate\Support\Facades\Schema;

/** Current configuration references, loaded once per request; application snapshots are excluded. */
final class WorkflowConfigurationUsage
{
    private ?array $index = null;

    private array $linkPermissions = [];

    /** Related resource policies use the same CRUD permissions for every configuration record. */
    public static function canOpenReference(string $kind): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }
        $resolver = app(self::class);
        $key = $user::class.':'.$user->getAuthIdentifier().':'.$kind;
        if (! array_key_exists($key, $resolver->linkPermissions)) {
            $resolver->linkPermissions[$key] = match ($kind) {
                'stage' => WorkflowStageTypeResource::canAccess() && $user->can('Update:WorkflowStageType'),
                'workflow' => WorkflowNotificationResource::canAccess() && $user->can('ViewAny:WorkflowNotification'),
                default => false,
            };
        }

        return $resolver->linkPermissions[$key];
    }

    public static function load(): array
    {
        return app(self::class)->index();
    }

    public static function stageTypesForButton(WorkflowButton $button): array
    {
        $index = self::load();

        return array_map(function (int $id) use ($index): array {
            $stage = $index['stage_types'][$id];

            return ['id' => $id, 'name' => $stage->label()];
        }, array_values($index['button_stages'][$button->getKey()] ?? []));
    }

    public static function workflowsForStage(WorkflowStageType $stage): array
    {
        return array_values(self::load()['stage_workflows'][$stage->getKey()] ?? []);
    }

    public static function workflowsForButton(WorkflowButton $button): array
    {
        return array_values(self::load()['button_workflows'][$button->getKey()] ?? []);
    }

    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $stageTypes = Schema::hasTable('workflow_stage_types')
            ? WorkflowStageType::query()->orderForDisplay()->get(['id', 'label_en', 'label_km', 'behavior', 'is_system', 'workflow_button_ids'])->keyBy('id')->all()
            : [];
        $buttonStages = [];
        $stageWorkflows = [];
        $buttonWorkflows = [];
        $legacyTypes = [];

        foreach ($stageTypes as $type) {
            foreach ((array) $type->workflow_button_ids as $buttonId) {
                if (($id = self::id($buttonId)) !== null) {
                    $buttonStages[$id][$type->getKey()] = (int) $type->getKey();
                }
            }

            // A legacy stage has no catalog ID and refers to the built-in execution type.
            if ($type->is_system) {
                $legacyTypes[self::behavior((string) $type->behavior)][] = (int) $type->getKey();
            }
        }

        $workflows = Schema::hasTable('workflow_notifications')
            ? WorkflowNotification::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'stages'])
            : [];
        foreach ($workflows as $workflow) {
            $usage = ['id' => (int) $workflow->getKey(), 'name' => (string) $workflow->name];
            foreach ($workflow->stages ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $stage = is_array($item['data'] ?? null) ? $item['data'] : $item;
                $stageId = self::id($stage['stage_type_id'] ?? null);
                $typeIds = $stageId !== null
                    ? (isset($stageTypes[$stageId]) ? [$stageId] : [])
                    : (blank($stage['stage_type_id'] ?? null)
                        ? ($legacyTypes[self::behavior((string) ($stage['stage_type'] ?? ''))] ?? [])
                        : []);
                foreach ($typeIds as $typeId) {
                    $stageWorkflows[$typeId][$workflow->getKey()] = $usage;
                }

                // Saved template buttons can differ from the catalog after later edits.
                foreach (is_array($stage['buttons'] ?? null) ? $stage['buttons'] : [] as $button) {
                    if (is_array($button) && ($buttonId = self::id($button['id'] ?? null)) !== null) {
                        $buttonWorkflows[$buttonId][$workflow->getKey()] = $usage;
                    }
                }
            }
        }

        return $this->index = [
            'stage_types' => $stageTypes,
            'button_stages' => $buttonStages,
            'stage_workflows' => $stageWorkflows,
            'button_workflows' => $buttonWorkflows,
        ];
    }

    private static function id(mixed $value): ?int
    {
        return (is_int($value) || is_string($value)) && ctype_digit((string) $value) && (int) $value > 0
            ? (int) $value : null;
    }

    private static function behavior(string $behavior): string
    {
        return $behavior === 'approval' ? 'review' : $behavior;
    }
}
