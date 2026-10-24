<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentType extends Model
{
    protected $fillable = [
        'key',
        'abbreviation',
        'name_en',
        'name_kh',
        'method',
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

    public function getLocalizedMethodAttribute(): string
    {
        return __('payment_types.options.methods.' . ($this->method ?: 'cash'));
    }

    public static function activeOptions(): array
    {
        return static::activeQuery()
            ->get()
            ->mapWithKeys(fn (self $paymentType): array => [
                $paymentType->key => $paymentType->localized_name,
            ])
            ->toArray();
    }

    public static function activeOptionsForMethod(string $method): array
    {
        return static::activeQuery()
            ->where('method', $method)
            ->get()
            ->mapWithKeys(fn (self $paymentType): array => [
                $paymentType->key => $method === 'bank'
                    ? ($paymentType->abbreviation ?: $paymentType->localized_name)
                    : $paymentType->localized_name,
            ])
            ->toArray();
    }

    public static function activeMethodOptions(): array
    {
        return collect(['bank', 'cash'])
            ->mapWithKeys(fn (string $method): array => [
                $method => __('payment_types.options.methods.' . $method),
            ])
            ->all();
    }

    public static function activeKeyForMethod(string $method): ?string
    {
        return static::activeQuery()
            ->where('method', $method)
            ->value('key');
    }

    protected static function activeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name_en');
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

    public static function localizedMethodFor(?string $key): string
    {
        return __('payment_types.options.methods.' . static::methodForKey($key));
    }

    public static function methodForKey(?string $key): string
    {
        $normalized = strtolower(trim((string) $key));
        $method = static::query()
            ->whereRaw('LOWER(key) = ?', [$normalized])
            ->value('method');

        return in_array($method, ['cash', 'bank'], true)
            ? $method
            : ($normalized === 'cash' ? 'cash' : 'bank');
    }

    public static function localizedBankNameFor(?string $key): string
    {
        $paymentType = static::query()->whereRaw('LOWER(key) = ?', [strtolower(trim((string) $key))])->first();

        return $paymentType && $paymentType->method === 'bank'
            ? ($paymentType->abbreviation ?: $paymentType->localized_name)
            : '-';
    }
}
