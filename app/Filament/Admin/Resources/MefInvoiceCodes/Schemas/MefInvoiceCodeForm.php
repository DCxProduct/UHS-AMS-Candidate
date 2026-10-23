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
                        ->required()
                        ->maxLength(100),
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
}
