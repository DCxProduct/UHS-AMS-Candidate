<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidebarMenuName extends Model
{
    protected $fillable = ['target_key', 'label_en', 'label_km'];

    public function label(string $fallback): string
    {
        $label = app()->getLocale() === 'km' ? $this->label_km : $this->label_en;

        return filled($label) ? $label : $fallback;
    }
}
