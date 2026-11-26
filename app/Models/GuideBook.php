<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GuideBook extends Model
{
    public const MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    protected $table = 'system_guide_books';

    protected $fillable = ['title_en', 'title_km', 'description_en', 'description_km', 'file_path', 'file_name', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updated(function (self $guide): void {
            if ($guide->wasChanged('file_path')) {
                static::deleteUnusedFile($guide->getRawOriginal('file_path'));
            }
        });
        static::deleted(fn (self $guide) => static::deleteUnusedFile($guide->file_path));
    }

    private static function deleteUnusedFile(?string $path): void
    {
        if (! static::validFilePath($path)) {
            return;
        }
        DB::afterCommit(function () use ($path): void {
            if (! static::query()->where('file_path', $path)->exists()) {
                Storage::disk('guide_books')->delete($path);
            }
        });
    }

    public static function defaultData(): array
    {
        return [
            'title_en' => 'System User Guide',
            'title_km' => 'សៀវភៅណែនាំប្រើប្រាស់ប្រព័ន្ធ',
            'description_en' => 'Guide to using the system.',
            'description_km' => 'សេចក្ដីណែនាំអំពីការប្រើប្រាស់ប្រព័ន្ធ។',
            'file_path' => '',
            'file_name' => null,
            'is_published' => false,
        ];
    }

    public static function ensureDefault(): self
    {
        $existing = static::query()->find(1) ?? static::query()->orderBy('id')->first();

        // The stable ID also prevents simultaneous first visits from adding two defaults.
        return $existing ?? static::unguarded(fn () => static::query()->firstOrCreate(['id' => 1], static::defaultData()));
    }

    public static function validFilePath(?string $path): bool
    {
        return is_string($path) && preg_match('~^files/[A-Za-z0-9_-]+\.(?:pdf|jpe?g|png|webp)$~i', $path) === 1;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function localized(string $field): string
    {
        $locale = in_array(app()->getLocale(), ['km', 'kh'], true) ? 'km' : 'en';

        return (string) ($this->getAttribute($field.'_'.$locale) ?: $this->getAttribute($field.'_en') ?: $this->getAttribute($field.'_km'));
    }
}
