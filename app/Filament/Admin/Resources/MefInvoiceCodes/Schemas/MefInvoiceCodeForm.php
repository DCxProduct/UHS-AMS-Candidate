<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Schemas;

use App\Models\MefInvoiceCode;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MefInvoiceCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('mef_invoice_codes.form.section_title'))
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('mef_code')
                        ->label(__('mef_invoice_codes.fields.mef_code'))
                        ->placeholder(__('mef_invoice_codes.form.mef_code_placeholder'))
                        ->autocomplete(false)
                        ->required()
                        ->maxLength(100)
                        ->extraInputAttributes([
                            'oninput' => "if (/^[0-9]+$/.test(this.value)) { this.value = 'MEF' + this.value; }",
                        ])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (mixed $state, callable $set, ?MefInvoiceCode $record): void {
                            $code = self::normalizeMefCode($state, $record);

                            if ($code !== $state) {
                                $set('mef_code', $code);
                            }
                        })
                        ->mutateStateForValidationUsing(fn (mixed $state, ?MefInvoiceCode $record): string => self::normalizeMefCode($state, $record))
                        ->dehydrateStateUsing(fn (mixed $state, ?MefInvoiceCode $record): string => self::normalizeMefCode($state, $record)),
                    TextInput::make('invoice_code')
                        ->label(__('mef_invoice_codes.fields.invoice_code'))
                        ->required()
                        ->maxLength(100)
                        ->extraInputAttributes([
                            'oninput' => "this.value = this.value.replace(/[^\\p{N}\\p{P}\\p{S}]/gu, '')",
                        ])
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (mixed $state, callable $set): void {
                            $sanitized = MefInvoiceCode::sanitizeInvoiceCode($state);

                            if ($sanitized !== $state) {
                                $set('invoice_code', $sanitized);
                            }
                        })
                        ->dehydrateStateUsing(fn (mixed $state): string => MefInvoiceCode::sanitizeInvoiceCode($state))
                        ->rule('regex:/^[\p{N}\p{P}\p{S}]+$/u')
                        ->validationMessages([
                            'regex' => __('mef_invoice_codes.validation.invoice_code_symbols_only'),
                        ]),
                ]),
        ]);
    }

    protected static function normalizeMefCode(mixed $value, ?MefInvoiceCode $record = null): string
    {
        // Preserve an existing identifier when only its invoice is edited.
        if ($record && (string) $value === (string) $record->mef_code) {
            return (string) $record->mef_code;
        }

        $code = trim((string) $value);

        return preg_match('/^[0-9]+$/D', $code) ? 'MEF'.$code : $code;
    }
}
