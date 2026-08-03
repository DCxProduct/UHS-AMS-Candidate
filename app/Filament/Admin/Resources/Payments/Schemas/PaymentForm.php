<?php

namespace App\Filament\Admin\Resources\Payments\Schemas;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\SystemUser;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('payments.sections.payment_information'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])
                            ->schema([
                                Select::make('users_id')
                                    ->label(__('payments.fields.user'))
                                    ->placeholder(__('payments.placeholders.user'))
                                    ->options(fn (): array => self::candidateUserOptions())
                                    ->default(fn (): ?int => request()->integer('users_id') ?: null)
                                    ->searchable()
                                    ->native(false)
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.user_required'),
                                    ]),

                                Select::make('form_id')
                                    ->label(__('payments.fields.form'))
                                    ->placeholder(__('payments.placeholders.form'))
                                    ->options(fn (): array => CustomForm::query()
                                        ->whereNotNull('name')
                                        ->where('slug', '!=', 'profile')
                                        ->orderBy('id')
                                        ->get()
                                        ->mapWithKeys(fn (CustomForm $form): array => [
                                            $form->id => (string) ($form->display_name ?: $form->name),
                                        ])
                                        ->toArray())
                                    ->default(fn (): ?int => request()->integer('form_id') ?: null)
                                    ->searchable()
                                    ->native(false)
                                    ->preload()
                                    ->nullable(),

                                TextInput::make('receipt_number')
                                    ->label(__('payments.fields.receipt_number'))
                                    ->placeholder(__('payments.placeholders.receipt_number'))
                                    ->required()
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'required' => __('payments.validation.receipt_number_required'),
                                    ]),

                                Select::make('type_payment')
                                    ->label(__('payments.fields.type_payment'))
                                    ->placeholder(__('payments.placeholders.type_payment'))
                                    ->options([
                                        'aba' => __('payments.options.type_payment.aba'),
                                        'wing' => __('payments.options.type_payment.wing'),
                                        'acleda' => __('payments.options.type_payment.acleda'),
                                        'cash' => __('payments.options.type_payment.cash'),
                                        'other' => __('payments.options.type_payment.other'),
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.type_payment_required'),
                                    ]),

                                DatePicker::make('datetime_pay')
                                    ->label(__('payments.fields.datetime_pay'))
                                    ->placeholder(__('payments.placeholders.datetime_pay'))
                                    ->native(false)
                                    ->suffixIcon('heroicon-o-calendar-days')
                                    ->nullable(),

                                TextInput::make('amount_usd')
                                    ->label(__('payments.fields.amount_usd'))
                                    ->placeholder(__('payments.placeholders.amount_usd'))
                                    ->suffix('$')
                                    ->inputMode('decimal')
                                    ->rule('numeric')
                                    ->live(onBlur: true)
                                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                        $component->state(self::normalizeUsdAmount($state));
                                    })
                                    ->afterStateUpdated(function (mixed $state, callable $set): void {
                                        $set('amount_usd', self::normalizeUsdAmount($state));
                                    })
                                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeUsdAmount($state))
                                    ->nullable(),

                                TextInput::make('amount_kh')
                                    ->label(__('payments.fields.amount_kh'))
                                    ->placeholder(__('payments.placeholders.amount_kh'))
                                    ->suffix('KHR')
                                    ->inputMode('decimal')
                                    ->rule('numeric')
                                    ->live(onBlur: true)
                                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                        $component->state(self::normalizeKhrAmount($state));
                                    })
                                    ->afterStateUpdated(function (mixed $state, callable $set): void {
                                        $set('amount_kh', self::normalizeKhrAmount($state));
                                    })
                                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::dehydrateKhrAmount($state))
                                    ->nullable(),

                            ]),

                        Textarea::make('description')
                            ->label(__('payments.fields.description'))
                            ->placeholder(__('payments.placeholders.description'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function normalizeUsdAmount(mixed $value): ?string
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

        return number_format((float) $normalized, 2, '.', '');
    }

    protected static function normalizeKhrAmount(mixed $value): ?string
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

    protected static function dehydrateKhrAmount(mixed $value): ?string
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

    protected static function candidateUserOptions(): array
    {
        return UserResource::getEloquentQuery()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (SystemUser $systemUser): array {
                $loginUser = $systemUser->findLinkedLoginUser();

                if (! $loginUser instanceof User || blank($loginUser->id)) {
                    return [];
                }

                return [
                    $loginUser->id => trim((string) ($systemUser->name ?: $systemUser->username ?: $systemUser->email ?: $systemUser->phone ?: '-')),
                ];
            })
            ->toArray();
    }
}
