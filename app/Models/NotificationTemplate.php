<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'subject_en',
        'subject_km',
        'body_en',
        'body_km',
        'channels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class, 'template_id');
    }

    public function getSubject(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return ($locale === 'km' && filled($this->subject_km))
            ? (string) $this->subject_km
            : (string) $this->subject_en;
    }

    public function getBody(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return ($locale === 'km' && filled($this->body_km))
            ? (string) $this->body_km
            : (string) $this->body_en;
    }
}
