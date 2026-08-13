<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getCurrencyPairAttribute(): string
    {
        return sprintf('%s/%s', strtoupper((string) $this->base_currency), strtoupper((string) $this->quote_currency));
    }

    public static function usdToKhrRecord(): self
    {
        return static::query()->firstOrCreate(
            [
                'base_currency' => 'USD',
                'quote_currency' => 'KHR',
            ],
            [
                'rate' => 4100,
                'is_active' => true,
            ]
        );
    }

    public static function activeUsdToKhrRate(): ?string
    {
        $record = static::query()
            ->whereRaw('UPPER(base_currency) = ?', ['USD'])
            ->whereRaw('UPPER(quote_currency) = ?', ['KHR'])
            ->where('is_active', true)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if (! $record) {
            $record = static::query()
                ->whereRaw('UPPER(base_currency) = ?', ['USD'])
                ->whereRaw('UPPER(quote_currency) = ?', ['KHR'])
                ->latest('updated_at')
                ->latest('id')
                ->first();
        }

        if (! $record) {
            $record = static::usdToKhrRecord();
        }

        if (! $record || blank($record->rate)) {
            return null;
        }

        return number_format((float) $record->rate, 2, '.', '');
    }
}
