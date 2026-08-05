<?php

namespace App\Support;

use App\Models\AuditLog;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        array $metadata = [],
    ): void {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $actor = auth()->user();

        AuditLog::query()->create([
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'actor_name' => self::actorName($actor),
            'action' => $action,
            'module' => self::moduleName($auditable, $metadata),
            'description' => $description ?: self::defaultDescription($action, $auditable, $actor),
        ]);
    }

    public static function logModelEvent(string $action, Model $model, array $oldValues = [], array $newValues = []): void
    {
        self::log(
            action: $action,
            auditable: $model,
            oldValues: $oldValues,
            newValues: $newValues,
        );
    }

    protected static function actorName(mixed $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        foreach (['name', 'username', 'email', 'phone'] as $field) {
            $value = data_get($actor, $field);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($actor) . ' #' . $actor->getKey();
    }

    protected static function moduleName(?Model $auditable, array $metadata): string
    {
        if (filled($metadata['module'] ?? null)) {
            return (string) $metadata['module'];
        }

        if (! $auditable) {
            return 'System';
        }

        if ($auditable instanceof CustomFormEntry) {
            $auditable->loadMissing('customForm');

            $formName = $auditable->customForm?->display_name;

            if (filled($formName)) {
                return (string) $formName;
            }
        }

        return Str::headline(class_basename($auditable));
    }

    protected static function defaultDescription(string $action, ?Model $auditable, mixed $actor): string
    {
        $actorName = self::actorName($actor) ?: 'System';
        $module = self::moduleName($auditable, []);
        $target = $auditable ? (' #' . $auditable->getKey()) : '';

        return "{$actorName} {$action} {$module}{$target}";
    }
}
