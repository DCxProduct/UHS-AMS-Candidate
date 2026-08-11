<?php

namespace App\Filament\Admin\Resources\ExchangeRates\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExchangeRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('exchange_rates.sections.rate_information'))
                    ->schema([
                        Hidden::make('base_currency')
                            ->default('USD')
                            ->dehydrated(true),

                        Hidden::make('quote_currency')
                            ->default('KHR')
                            ->dehydrated(true),

                        Hidden::make('is_active')
                            ->default(true)
                            ->dehydrated(true),

                        TextInput::make('rate')
                            ->label(__('exchange_rates.fields.rate'))
                            ->placeholder(__('exchange_rates.placeholders.rate'))
                            ->prefix('USD')
                            ->suffix('KHR')
                            ->inputMode('decimal')
                            ->required()
                            ->extraInputAttributes([
                                'oninput' => "this.value = this.value.replace(/[^0-9,]/g, '')",
                            ])
                            ->rule(static function (): \Closure {
                                return static function (string $attribute, mixed $value, \Closure $fail): void {
                                    if ($value === null || trim((string) $value) === '') {
                                        return;
                                    }

                                    if (! is_numeric(str_replace(',', '', (string) $value))) {
                                        $fail(__('exchange_rates.validation.rate_numeric'));
                                    }
                                };
                            })
                            ->live(onBlur: true)
                            ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                $component->state(self::formatRate($state));
                            })
                            ->afterStateUpdated(function (mixed $state, callable $set): void {
                                $set('rate', self::formatRate($state));
                            })
                            ->dehydrateStateUsing(fn (mixed $state): ?string => self::dehydrateRate($state))
                            ->validationMessages([
                                'required' => __('exchange_rates.validation.rate_required'),
                            ]),
                    ]),
            ]);
    }

    protected static function formatRate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);

        if (! is_numeric($normalized)) {
            return $normalized;
        }

        $number = (float) $normalized;

        if (floor($number) === $number) {
            return number_format($number, 0, '.', ',');
        }

        return number_format($number, 2, '.', ',');
    }

    protected static function dehydrateRate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return str_replace(',', '', $normalized);
    }
}
