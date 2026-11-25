<?php

namespace App\Models;

use App\Support\SidebarMenuUrls;
use Illuminate\Database\Eloquent\Model;

class SidebarMenuName extends Model
{
    protected $fillable = ['target_key', 'label_en', 'label_km'];

    protected static function booted(): void
    {
        static::saved(fn () => app(SidebarMenuUrls::class)->flush());
        static::deleted(fn () => app(SidebarMenuUrls::class)->flush());
    }

    public function label(string $fallback): string
    {
        $label = app()->getLocale() === 'km' ? $this->label_km : $this->label_en;

        return filled($label) ? $label : $fallback;
    }
}
