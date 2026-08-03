<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = [
        'name',
        'name_kh',
        'guard_name',
    ];

    protected $appends = [
        'localized_name',
    ];

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();

        if ($locale === 'km' && filled($this->name_kh)) {
            return (string) $this->name_kh;
        }

        if (filled($this->name)) {
            return (string) $this->name;
        }

        if (filled($this->name_kh)) {
            return (string) $this->name_kh;
        }

        return (string) Str::of((string) $this->attributes['name'] ?? '')
            ->replace(['_', '-'], ' ')
            ->title();
    }
}
