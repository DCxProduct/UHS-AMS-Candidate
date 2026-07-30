<?php

namespace App\Filament\Admin\Resources\Payments\Schemas;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                            'lg' => 3,
                        ])
                            ->schema([
                                Select::make('users_id')
                                    ->label(__('payments.fields.user'))
                                    ->placeholder(__('payments.placeholders.user'))
                                    ->options(fn (): array => User::query()
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (User $user): array => [
                                            $user->id => trim((string) ($user->name ?: $user->username ?: $user->email ?: $user->phone ?: '-')),
                                        ])
                                        ->toArray())
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

                                Select::make('status_payt')
                                    ->label(__('payments.fields.status_payt'))
                                    ->placeholder(__('payments.placeholders.status_payt'))
                                    ->options([
                                        'paid' => __('payments.options.status_payt.paid'),
                                        'return' => __('payments.options.status_payt.return'),
                                        'pending' => __('payments.options.status_payt.pending'),
                                    ])
                                    ->native(false)
                                    ->default('paid')
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.status_payt_required'),
                                    ]),

                                DateTimePicker::make('datetime_pay')
                                    ->label(__('payments.fields.datetime_pay'))
                                    ->placeholder(__('payments.placeholders.datetime_pay'))
                                    ->seconds(false)
                                    ->native(false)
                                    ->nullable(),

                                TextInput::make('amount_usd')
                                    ->label(__('payments.fields.amount_usd'))
                                    ->placeholder(__('payments.placeholders.amount_usd'))
                                    ->numeric()
                                    ->prefix('$')
                                    ->inputMode('decimal')
                                    ->nullable(),

                                TextInput::make('amount_kh')
                                    ->label(__('payments.fields.amount_kh'))
                                    ->placeholder(__('payments.placeholders.amount_kh'))
                                    ->numeric()
                                    ->suffix('KHR')
                                    ->inputMode('decimal')
                                    ->nullable(),

                                Toggle::make('status')
                                    ->label(__('payments.fields.status'))
                                    ->default(true)
                                    ->required(),
                            ]),

                        Textarea::make('description')
                            ->label(__('payments.fields.description'))
                            ->placeholder(__('payments.placeholders.description'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
