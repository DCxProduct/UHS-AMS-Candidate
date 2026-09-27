<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SystemUser;
use App\Models\User;
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

        if (! self::shouldLogForActor($actor)) {
            return;
        }

        $data = [
            'actor_type' => $actor ? $actor::class : null,
            'actor_id' => $actor?->getKey(),
            'actor_name' => self::actorName($actor),
            'action' => $action,
            'module' => self::moduleName($auditable, $metadata),
            'description' => $description ?: self::defaultDescription($action, $auditable, $actor),
        ];

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $data['ip_address'] = request()?->ip();
        }

        if (Schema::hasColumn('audit_logs', 'actor_role')) {
            $data['actor_role'] = self::actorRole($actor);
        }

        if (Schema::hasColumn('audit_logs', 'old_values')) {
            $data['old_values'] = self::sanitize($oldValues);
        }

        if (Schema::hasColumn('audit_logs', 'new_values')) {
            $data['new_values'] = self::sanitize($newValues);
        }

        if (Schema::hasColumn('audit_logs', 'metadata')) {
            $data['metadata'] = self::sanitize($metadata);
        }

        AuditLog::query()->create($data);
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

    protected static function actorRole(mixed $actor): ?string
    {
        if ($actor instanceof SystemUser) {
            return collect(['admin', 'registrar', 'cashier'])
                ->first(fn (string $role): bool => $actor->hasJsonRole($role));
        }

        if ($actor instanceof User) {
            if (method_exists($actor, 'effectiveRoleNames')) {
                return collect(['admin', 'registrar', 'cashier'])
                    ->first(fn (string $role): bool => $actor->effectiveRoleNames()->contains($role));
            }

            return collect(['admin', 'registrar', 'cashier'])
                ->first(fn (string $role): bool => $actor->hasRole($role));
        }

        return null;
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

    protected static function shouldLogForActor(mixed $actor): bool
    {
        return match (true) {
            $actor instanceof SystemUser => $actor->hasJsonRole(['admin', 'registrar', 'cashier']),
            $actor instanceof User => method_exists($actor, 'hasEffectiveRole')
                ? $actor->hasEffectiveRole(['admin', 'registrar', 'cashier'])
                : $actor->hasRole(['admin', 'registrar', 'cashier']),
            default => false,
        };
    }

    protected static function sanitize(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        $sensitive = ['password', 'password_confirmation', 'remember_token', 'token', 'secret', 'api_key', 'access_key'];

        return collect($values)
            ->reject(function (mixed $value, mixed $key) use ($sensitive): bool {
                $key = Str::lower((string) $key);

                return $key === 'data'
                    || collect($sensitive)->contains(fn (string $needle): bool => Str::contains($key, $needle));
            })
            ->map(function (mixed $value): mixed {
                if (is_array($value)) {
                    return self::sanitize($value);
                }

                if ($value instanceof \DateTimeInterface) {
                    return $value->format(DATE_ATOM);
                }

                return is_scalar($value) || $value === null ? $value : (string) $value;
            })
            ->all() ?: null;
    }
}
