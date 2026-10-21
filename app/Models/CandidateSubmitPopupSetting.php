<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CandidateSubmitPopupSetting extends Model
{
    protected $fillable = [
        'custom_form_id',
        'is_active',
        'title_en',
        'title_km',
        'description_en',
        'description_km',
        'confirm_label_en',
        'confirm_label_km',
        'cancel_label_en',
        'cancel_label_km',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function customForm(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'is_active' => true,
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

    public static function forCustomFormId(int|string|null $customFormId): ?self
    {
        if (blank($customFormId) || ! Schema::hasColumn('candidate_submit_popup_settings', 'custom_form_id')) {
            return static::singleton();
        }

        $form = CustomForm::query()
            ->whereKey($customFormId)
            ->first();

        if (! $form) {
            return static::singleton();
        }

        return static::query()->where('custom_form_id', $form->getKey())->first()
            ?? static::singleton();
    }

    public static function ensureForEachCustomForm(): void
    {
        if (! Schema::hasColumn('candidate_submit_popup_settings', 'custom_form_id')) {
            return;
        }

        $default = static::defaultContent(static::singleton());

        CustomForm::query()
            ->orderBy('display_order')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn (CustomForm $form) => static::query()->firstOrCreate(
                ['custom_form_id' => $form->getKey()],
                $default,
            ));
    }

    protected static function defaultContent(self $setting): array
    {
        return [
            'is_active' => true,
            'title_en' => $setting->title_en,
            'title_km' => $setting->title_km,
            'description_en' => $setting->description_en,
            'description_km' => $setting->description_km,
            'confirm_label_en' => $setting->confirm_label_en,
            'confirm_label_km' => $setting->confirm_label_km,
            'cancel_label_en' => $setting->cancel_label_en,
            'cancel_label_km' => $setting->cancel_label_km,
        ];
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
        $fallback = (string) ($this->getAttribute("{$prefix}_".($locale === 'km' ? 'en' : 'km')) ?? '');

        return filled($primary) ? $primary : $fallback;
    }
}
