<?php

namespace App\Filament\Admin\Resources\Payments\Schemas;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Models\ExchangeRate;
use App\Models\PaymentType;
use App\Models\SystemUser;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DatabaseSchema;

class PaymentForm
{
    public static function configure(Schema $schema, bool $restrictToUnpaidApplications = false): Schema
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
                                    ->options(fn (): array => self::candidateUserOptions($restrictToUnpaidApplications))
                                    ->default(fn (): ?int => self::defaultUserId($restrictToUnpaidApplications))
                                    ->searchable()
                                    ->native(false)
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.user_required'),
                                    ]),

                                Hidden::make('form_id')
                                    ->default(fn (): ?int => self::defaultFormId($restrictToUnpaidApplications)),

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
                                    ->options(fn (): array => PaymentType::activeOptions())
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.type_payment_required'),
                                    ]),

                                DatePicker::make('datetime_pay')
                                    ->label(__('payments.fields.datetime_pay'))
                                    ->markAsRequired()
                                    ->placeholder(__('payments.placeholders.datetime_pay'))
                                    ->native(false)
                                    ->maxDate(now()->toDateString())
                                    ->suffixIcon('heroicon-o-calendar-days')
                                    ->required()
                                    ->validationMessages([
                                        'required' => __('payments.validation.datetime_pay_required'),
                                    ]),

                                TextInput::make('amount_kh')
                                    ->label(__('payments.fields.amount_kh'))
                                    ->markAsRequired()
                                    ->placeholder(__('payments.placeholders.amount_kh'))
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
                                                $fail(__('validation.numeric', ['attribute' => $attribute]));
                                            }
                                        };
                                    })
                                    ->live(onBlur: true)
                                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                        $component->state(self::normalizeKhrAmount($state));
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $normalized = self::normalizeKhrAmount($state);
                                        $set('amount_kh', $normalized);
                                        self::syncUsdFromKhr($get, $set);
                                    })
                                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::dehydrateKhrAmount($state))
                                    ->validationMessages([
                                        'required' => __('payments.validation.amount_kh_required'),
                                    ]),

                                TextInput::make('amount_usd')
                                    ->label(__('payments.fields.amount_usd'))
                                    ->placeholder(__('payments.placeholders.amount_usd'))
                                    ->suffix('$')
                                    ->inputMode('decimal')
                                    ->extraInputAttributes([
                                        'oninput' => "this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\\..*)\\./g, '$1')",
                                    ])
                                    ->rule('numeric')
                                    ->live(onBlur: true)
                                    ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                        $component->state(self::normalizeUsdAmount($state));
                                    })
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $normalized = self::normalizeUsdAmount($state);
                                        $set('amount_usd', $normalized);
                                        self::syncKhrFromUsd($get, $set);
                                    })
                                    ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeUsdAmount($state))
                                    ->nullable(),

                            ]),

                        Hidden::make('exchange_rate')
                            ->default(fn (): string => self::defaultExchangeRate())
                            ->afterStateHydrated(function (Hidden $component, mixed $state): void {
                                $component->state(self::normalizeExchangeRate($state) ?? self::defaultExchangeRate());
                            })
                            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeExchangeRate($state)),

                        Placeholder::make('exchange_rate_display')
                            ->label(__('payments.fields.exchange_rate'))
                            ->helperText(__('payments.help.exchange_rate'))
                            ->content(function ($get): string {
                                $rate = self::normalizeExchangeRate($get('exchange_rate')) ?? self::defaultExchangeRate();

                                return sprintf('1 USD = %s KHR', $rate);
                            }),

                        Textarea::make('description')
                            ->label(__('payments.fields.description'))
                            ->placeholder(__('payments.placeholders.description'))
                            ->rows(4)
                            ->columnSpanFull(),

                        FileUpload::make('payment_slip_path')
                            ->label(__('payments.fields.payment_slip'))
                            ->placeholder(__('payments.placeholders.payment_slip'))
                            ->disk('public')
                            ->directory('payment-slips')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->image()
                            ->imageEditor()
                            ->maxSize(5120)
                            ->openable()
                            ->downloadable()
                            ->previewable()
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

    protected static function defaultExchangeRate(): string
    {
        return ExchangeRate::activeUsdToKhrRate() ?? '4100.00';
    }

    protected static function normalizeExchangeRate(mixed $value): ?string
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

    protected static function syncKhrFromUsd(callable $get, callable $set): void
    {
        $usdAmount = self::normalizeUsdAmount($get('amount_usd'));
        $rate = self::normalizeExchangeRate($get('exchange_rate'));

        if (blank($usdAmount) || blank($rate) || ! is_numeric($usdAmount) || ! is_numeric($rate) || (float) $rate <= 0) {
            return;
        }

        $set('amount_kh', self::normalizeKhrAmount((float) $usdAmount * (float) $rate));
    }

    protected static function syncUsdFromKhr(callable $get, callable $set): void
    {
        $khrAmount = self::dehydrateKhrAmount($get('amount_kh'));
        $rate = self::normalizeExchangeRate($get('exchange_rate'));

        if (blank($khrAmount) || blank($rate) || ! is_numeric($khrAmount) || ! is_numeric($rate) || (float) $rate <= 0) {
            return;
        }

        $set('amount_usd', self::normalizeUsdAmount((float) $khrAmount / (float) $rate));
    }

    protected static function syncAmountsFromRate(callable $get, callable $set): void
    {
        $usdAmount = self::normalizeUsdAmount($get('amount_usd'));
        $khrAmount = self::dehydrateKhrAmount($get('amount_kh'));

        if (filled($usdAmount) && is_numeric($usdAmount)) {
            self::syncKhrFromUsd($get, $set);

            return;
        }

        if (filled($khrAmount) && is_numeric($khrAmount)) {
            self::syncUsdFromKhr($get, $set);
        }
    }

    protected static function candidateUserOptions(bool $restrictToUnpaidApplications = false): array
    {
        if ($restrictToUnpaidApplications) {
            return static::unpaidApplicationUserOptions();
        }

        return CandidateListResource::getEloquentQuery()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (SystemUser $systemUser): array {
                $loginUser = $systemUser->findLinkedLoginUser();

                if (! $loginUser instanceof User || blank($loginUser->id)) {
                    return [];
                }

                return [
                    $loginUser->id => self::candidateDisplayName($loginUser, $systemUser),
                ];
            })
            ->toArray();
    }

    protected static function formOptions(bool $restrictToUnpaidApplications = false): array
    {
        if ($restrictToUnpaidApplications) {
            return static::unpaidApplicationFormOptions();
        }

        return CustomForm::query()
            ->whereNotNull('name')
            ->where('slug', '!=', 'profile')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (CustomForm $form): array => [
                $form->id => (string) ($form->display_name ?: $form->name),
            ])
            ->toArray();
    }

    protected static function defaultUserId(bool $restrictToUnpaidApplications = false): ?int
    {
        $requested = request()->integer('users_id') ?: null;

        if (! $restrictToUnpaidApplications) {
            return $requested;
        }

        $options = static::candidateUserOptions(true);

        if ($requested && array_key_exists($requested, $options)) {
            return $requested;
        }

        return count($options) === 1 ? (int) array_key_first($options) : $requested;
    }

    protected static function defaultFormId(bool $restrictToUnpaidApplications = false): ?int
    {
        $requested = request()->integer('form_id') ?: null;

        if (! $restrictToUnpaidApplications) {
            return $requested;
        }

        $options = static::formOptions(true);

        if ($requested && array_key_exists($requested, $options)) {
            return $requested;
        }

        return count($options) === 1 ? (int) array_key_first($options) : $requested;
    }

    protected static function candidateDisplayName(User $loginUser, ?SystemUser $systemUser = null): string
    {
        $profileName = self::latestProfileNameForUser($loginUser->id);

        if (filled($profileName)) {
            return $profileName;
        }

        return trim((string) (
            $loginUser->name
            ?: $systemUser?->name
            ?: $loginUser->username
            ?: $systemUser?->username
            ?: $loginUser->email
            ?: $systemUser?->email
            ?: $loginUser->phone
            ?: $systemUser?->phone
            ?: '-'
        ));
    }

    protected static function latestProfileNameForUser(int $userId): ?string
    {
        if (
            $userId <= 0
            || ! DatabaseSchema::hasTable('custom_forms')
            || ! DatabaseSchema::hasTable('custom_form_entries')
        ) {
            return null;
        }

        $profileFormId = CustomForm::query()
            ->where('slug', 'profile')
            ->value('id');

        if (! $profileFormId) {
            return null;
        }

        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => DatabaseSchema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();

        if ($ownerColumns === []) {
            return null;
        }

        $entry = CustomFormEntry::query()
            ->where('custom_form_id', $profileFormId)
            ->where(function ($query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, $userId);
                }
            })
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->latest('id')
            ->first();

        if (! $entry) {
            return null;
        }

        $data = is_array($entry->data)
            ? $entry->data
            : json_decode((string) $entry->data, true);

        if (! is_array($data)) {
            return null;
        }

        if (app()->getLocale() === 'km') {
            $khmerFullName = trim((string) ($data['full_name_kh'] ?? ''));

            if ($khmerFullName !== '') {
                return $khmerFullName;
            }

            $khmerName = trim(implode(' ', array_filter([
                $data['first_name_kh'] ?? null,
                $data['last_name_kh'] ?? null,
            ])));

            if ($khmerName !== '') {
                return $khmerName;
            }
        }

        $latinFullName = trim((string) ($data['full_name_en'] ?? ''));

        if ($latinFullName !== '') {
            return $latinFullName;
        }

        $latinName = trim(implode(' ', array_filter([
            $data['first_name_en'] ?? null,
            $data['last_name_en'] ?? null,
        ])));

        if ($latinName !== '') {
            return $latinName;
        }

        return null;
    }

    protected static function unpaidApplicationUserOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->with(['creator'])
            ->get()
            ->map(function ($record): array {
                $userId = (int) ($record->creator?->id ?? 0);

                if ($userId <= 0) {
                    return [];
                }

                $systemUser = $record->creator?->linkedSystemUser();

                return [
                    'id' => $userId,
                    'label' => static::candidateDisplayName($record->creator, $systemUser instanceof SystemUser ? $systemUser : null),
                ];
            })
            ->filter(fn (array $item): bool => filled($item['id'] ?? null))
            ->keyBy('id')
            ->map(fn (array $item): string => (string) $item['label'])
            ->toArray();
    }

    protected static function unpaidApplicationFormOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->with(['customForm'])
            ->get()
            ->filter(fn ($record): bool => filled($record->custom_form_id) && $record->customForm !== null)
            ->mapWithKeys(fn ($record): array => [
                (int) $record->custom_form_id => (string) ($record->customForm->display_name ?: $record->customForm->name ?: '-'),
            ])
            ->toArray();
    }
}
