<?php

namespace App\Models;

use App\Support\CandidateTypeOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CandidateType extends Model
{
    protected $table = 'candidate_types';

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
        static::saving(function (CandidateType $candidateType): void {
            $candidateType->color = CandidateTypeOptions::canonicalColor($candidateType->color);
        });

        static::deleting(function (CandidateType $candidateType): void {
            static::removeCandidateTypeFromSystemUsers($candidateType->key);

            Role::query()
                ->where('guard_name', 'web')
                ->where('name', $candidateType->key)
                ->delete();
        });

        static::saved(function (CandidateType $candidateType): void {
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

    protected static function removeCandidateTypeFromSystemUsers(string $candidateTypeKey): void
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
