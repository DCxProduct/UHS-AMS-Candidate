<?php

namespace App\Models;

use App\Support\UserTypeOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserType extends Model
{
    protected $table = 'user_types';

    protected $fillable = [
        'key',
        'label_en',
        'label_kh',
        'color',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (UserType $candidateType): void {
            $candidateType->color = UserTypeOptions::canonicalColor($candidateType->color);
        });

        static::deleting(function (UserType $candidateType): void {
            static::removeUserTypeFromSystemUsers($candidateType->key);

            Role::query()
                ->where('guard_name', 'web')
                ->where('name', $candidateType->key)
                ->delete();
        });

        static::saved(function (UserType $candidateType): void {
            $originalKey = $candidateType->getOriginal('key');

            $role = null;

            if (filled($originalKey)) {
                $role = Role::query()
                    ->where('guard_name', 'web')
                    ->where('name', $originalKey)
                    ->first();
            }

            if (! $role) {
                $role = Role::query()->firstOrCreate([
                    'guard_name' => 'web',
                    'name' => $candidateType->key,
                ]);
            }

            if ($role->name !== $candidateType->key) {
                $role->forceFill([
                    'name' => $candidateType->key,
                ])->save();
            }
        });
    }

    public function getLocalizedLabel(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if ($locale === 'km' && filled($this->label_kh)) {
            return (string) $this->label_kh;
        }

        if (filled($this->label_en)) {
            return (string) $this->label_en;
        }

        if (filled($this->label_kh)) {
            return (string) $this->label_kh;
        }

        return (string) Str::of($this->key)
            ->replace(['_', '-'], ' ')
            ->title();
    }

    protected static function removeUserTypeFromSystemUsers(string $candidateTypeKey): void
    {
        SystemUser::query()
            ->whereNotNull('roles')
            ->get()
            ->each(function (SystemUser $systemUser) use ($candidateTypeKey): void {
                $roles = $systemUser->roles;

                if (is_string($roles)) {
                    $decoded = json_decode($roles, true);
                    $roles = is_array($decoded) ? $decoded : [$roles];
                }

                if (! is_array($roles)) {
                    return;
                }

                $updatedRoles = collect($roles)
                    ->filter(fn ($role): bool => filled($role))
                    ->reject(fn ($role): bool => strcasecmp(trim((string) $role), trim($candidateTypeKey)) === 0)
                    ->values()
                    ->all();

                if ($updatedRoles === $roles) {
                    return;
                }

                $systemUser->forceFill([
                    'roles' => $updatedRoles,
                ])->saveQuietly();
            });
    }
}
