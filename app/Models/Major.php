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

    public static function optionsForCustomFormId(int|string|null $customFormId): array
    {
        if (blank($customFormId) || ! Schema::hasTable('closing_date_major')) {
            return [];
        }

        $closingDate = ClosingDate::getDeadlineByCustomFormId($customFormId);

        if (! $closingDate) {
            return [];
        }

        return $closingDate->majors()
            ->where('majors.is_active', true)
            ->orderBy('majors.name_en')
            ->get()
            ->mapWithKeys(fn (self $major): array => [
                $major->getKey() => $major->localized_name,
            ])
            ->all();
    }
}
