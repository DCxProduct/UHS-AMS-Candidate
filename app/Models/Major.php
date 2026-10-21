<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Major extends Model
{
    protected $fillable = [
        'key',
        'abbreviation',
        'name_en',
        'name_kh',
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
        static::saving(function (Major $major): void {
            $major->key = Str::of((string) $major->key)
                ->trim()
                ->lower()
                ->replaceMatches('/[^a-z0-9_-]+/', '_')
                ->replaceMatches('/_+/', '_')
                ->trim('_')
                ->toString();

            if (filled($major->abbreviation)) {
                $major->abbreviation = strtoupper(trim((string) $major->abbreviation));
            }
        });
    }

    public function closingDates(): BelongsToMany
    {
        return $this->belongsToMany(ClosingDate::class, 'closing_date_major');
    }

    public function degreeLevels(): BelongsToMany
    {
        return $this->belongsToMany(DegreeLevel::class, 'degree_level_major');
    }

    public function getLocalizedNameAttribute(): string
    {
        if (app()->getLocale() === 'km' && filled($this->name_kh)) {
            return (string) $this->name_kh;
        }

        return (string) ($this->name_en ?: $this->name_kh ?: '-');
    }

    public static function options(): array
    {
        if (! Schema::hasTable('majors')) {
            return [];
        }

        return static::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get()
            ->mapWithKeys(fn (self $major): array => [
                $major->getKey() => $major->localized_name,
            ])
            ->all();
    }

    public static function optionsForDegreeLevelId(int|string|null $degreeLevelId): array
    {
        if (blank($degreeLevelId) || ! Schema::hasTable('degree_level_major')) {
            return [];
        }

        return static::query()
            ->where('is_active', true)
            ->whereHas('degreeLevels', fn ($query) => $query->whereKey($degreeLevelId))
            ->orderBy('name_en')
            ->get()
            ->mapWithKeys(fn (self $major): array => [
                $major->getKey() => $major->localized_name,
            ])
            ->all();
    }

    public static function optionsForCustomFormId(int|string|null $customFormId): array
    {
        if (blank($customFormId) || ! Schema::hasTable('closing_date_major')) {
            return [];
        }

        $closingDate = ClosingDate::getDeadlineByCustomFormId($customFormId);

        if (! $closingDate) {
            return [];
        }

        $query = $closingDate->majors()
            ->where('majors.is_active', true)
            ->when(
                filled($closingDate->degree_level_id) && Schema::hasTable('degree_level_major'),
                fn ($query) => $query->whereHas(
                    'degreeLevels',
                    fn ($degreeLevels) => $degreeLevels->whereKey($closingDate->degree_level_id)
                )
            )
            ->orderBy('majors.name_en');

        return $query->get()
            ->mapWithKeys(fn (self $major): array => [
                $major->getKey() => $major->localized_name,
            ])
            ->all();
    }
}
