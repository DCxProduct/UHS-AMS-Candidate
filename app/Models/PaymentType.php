<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $fillable = [
        'key',
        'name_en',
        'name_kh',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getLocalizedNameAttribute(): string
    {
        if (app()->getLocale() === 'km' && filled($this->name_kh)) {
            return (string) $this->name_kh;
        }

        return (string) ($this->name_en ?: $this->name_kh ?: $this->key);
    }

    public static function activeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name_en')
            ->get()
            ->mapWithKeys(fn (self $paymentType): array => [
                $paymentType->key => $paymentType->localized_name,
            ])
            ->toArray();
    }

    public static function localizedLabelFor(?string $key): string
    {
        $normalized = strtolower(trim((string) $key));

        if ($normalized === '') {
            return '-';
        }

        $paymentType = static::query()
            ->whereRaw('LOWER(key) = ?', [$normalized])
            ->first();

        if ($paymentType) {
            return $paymentType->localized_name;
        }

        return __('payments.options.type_payment.' . $normalized);
    }
}
