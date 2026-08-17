<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RoleType extends Model
{
    protected $fillable = [
        'key',
        'label_en',
        'label_kh',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (RoleType $roleType): void {
            $roleType->key = Str::of((string) $roleType->key)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9_-]+/', '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->toString();
        });
    }

    public function getLocalizedLabelAttribute(): string
    {
        if (app()->getLocale() === 'km' && filled($this->label_kh)) {
            return (string) $this->label_kh;
        }

        return (string) ($this->label_en ?: $this->label_kh ?: $this->key);
    }

    public static function options(): array
    {
        static::ensureDefaults();

        if (! Schema::hasTable('role_types')) {
            return static::defaultOptions();
        }

        return static::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $roleType): array => [
                $roleType->key => $roleType->localized_label,
            ])
            ->all();
    }

    public static function descriptionFor(?string $key): string
    {
        static::ensureDefaults();

        if (! is_string($key) || trim($key) === '') {
            return '';
        }

        return static::defaultDescriptions()[strtolower(trim($key))] ?? '';
    }

    protected static function ensureDefaults(): void
    {
        if (! Schema::hasTable('role_types')) {
            return;
        }

        $legacyStaff = static::query()
            ->where('key', 'system_admin')
            ->first();

        $staff = static::query()
            ->where('key', 'staff')
            ->first();

        if ($legacyStaff && $staff) {
            $legacyStaff->delete();
        } elseif ($legacyStaff && ! $staff) {
            $legacyStaff->forceFill([
                'key' => 'staff',
                'label_en' => $legacyStaff->label_en ?: 'Staff',
                'label_kh' => $legacyStaff->label_kh ?: 'បុគ្គលិក',
            ])->save();
        }

        if (static::query()->exists()) {
            return;
        }

        foreach (static::defaultRecords() as $record) {
            static::query()->create($record);
        }
    }

    protected static function defaultOptions(): array
    {
        return [
            'candidate' => __('system_users.role_menu.options.user'),
            'staff' => __('system_users.role_menu.options.staff'),
        ];
    }

    protected static function defaultDescriptions(): array
    {
        return [
            'candidate' => __('system_users.role_menu.help_user'),
            'staff' => __('system_users.role_menu.help_staff'),
        ];
    }

    protected static function defaultRecords(): array
    {
        return [
            [
                'key' => 'candidate',
                'label_en' => 'Candidate',
                'label_kh' => 'បេក្ខជន',
                'is_active' => true,
            ],
            [
                'key' => 'staff',
                'label_en' => 'Staff',
                'label_kh' => 'បុគ្គលិក',
                'is_active' => true,
            ],
        ];
    }
}
