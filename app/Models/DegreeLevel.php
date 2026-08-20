<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DegreeLevel extends Model
{
    protected $fillable = [
        'key',
        'label_en',
        'label_kh',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DegreeLevel $degreeLevel): void {
            $degreeLevel->key = Str::of((string) $degreeLevel->key)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9_-]+/', '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->toString();
        });

        static::deleting(function (DegreeLevel $degreeLevel): void {
            UserType::query()
                ->where('group_name', $degreeLevel->key)
                ->update([
                    'group_name' => null,
                ]);
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
        if (! Schema::hasTable('degree_levels')) {
            return [];
        }

        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (self $degreeLevel): array => [
                $degreeLevel->key => $degreeLevel->localized_label,
            ])
            ->all();
    }

    public static function findByKey(?string $key): ?self
    {
        if (blank($key) || ! Schema::hasTable('degree_levels')) {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(key) = ?', [Str::lower(trim((string) $key))])
            ->first();
    }
}
