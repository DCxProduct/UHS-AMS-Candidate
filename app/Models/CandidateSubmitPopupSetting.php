<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateSubmitPopupSetting extends Model
{
    protected $fillable = [
        'title_en',
        'title_km',
        'description_en',
        'description_km',
        'confirm_label_en',
        'confirm_label_km',
        'cancel_label_en',
        'cancel_label_km',
    ];

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'title_en' => __('app.confirm_submit_data', locale: 'en'),
                'title_km' => __('app.confirm_submit_data', locale: 'km'),
                'description_en' => __('app.confirm_submit_data_description', locale: 'en'),
                'description_km' => __('app.confirm_submit_data_description', locale: 'km'),
                'confirm_label_en' => __('app.save', locale: 'en'),
                'confirm_label_km' => __('app.save', locale: 'km'),
                'cancel_label_en' => __('app.cancel', locale: 'en'),
                'cancel_label_km' => __('app.cancel', locale: 'km'),
            ],
        );
    }

    public function localizedTitle(): string
    {
        return $this->localizedValue('title') ?: __('app.confirm_submit_data');
    }

    public function localizedDescription(): string
    {
        return $this->localizedValue('description') ?: __('app.confirm_submit_data_description');
    }

    public function localizedConfirmLabel(): string
    {
        return $this->localizedValue('confirm_label') ?: __('app.save');
    }

    public function localizedCancelLabel(): string
    {
        return $this->localizedValue('cancel_label') ?: __('app.cancel');
    }

    protected function localizedValue(string $prefix): string
    {
        $locale = app()->getLocale() === 'km' ? 'km' : 'en';
        $primary = (string) ($this->getAttribute("{$prefix}_{$locale}") ?? '');
        $fallback = (string) ($this->getAttribute("{$prefix}_" . ($locale === 'km' ? 'en' : 'km')) ?? '');

        return filled($primary) ? $primary : $fallback;
    }
}
