<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Register extends BaseRegister
{
    public function getTitle(): string | Htmlable
    {
        return __('app.sign_up');
    }

    public function getHeading(): string | Htmlable
    {
        return __('app.sign_up');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('registration_type')
                    ->hiddenLabel()
                    ->options([
                        'national_exam' => __('app.national_examination'),
                        'enrollment' => __('app.enrollment'),
                    ])
                    ->icons([
                        'national_exam' => 'heroicon-o-clipboard-document-check',
                        'enrollment' => 'heroicon-o-academic-cap',
                    ])
                    ->colors([
                        'national_exam' => 'warning',
                        'enrollment' => 'warning',
                    ])
                    ->default('national_exam')
                    ->inline()
                    ->live()
                    ->markAsRequired(false)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'uhs-register-type-wrapper flex flex-col items-center justify-center w-full',
                    ]),

                TextInput::make('username')
                    ->label(__('app.username'))
                    ->placeholder(__('app.enter_username'))
                    ->required()
                    ->minLength(6)
                    ->maxLength(15)
                    ->unique(User::class, 'username')
                    ->prefixIcon('heroicon-o-identification')
                    ->rules([
                        'required',
                        'string',
                        'min:6',
                        'max:15',
                        'regex:/[a-z]/',
                        'regex:/[0-9]/',
                        'regex:/^[a-z0-9_]+$/',
                        'not_regex:/(.)\1{4,}/',
                    ])
                    ->extraInputAttributes([
                        'oninput' => "this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '').slice(0, 15)",
                        'pattern' => '[a-z0-9_]+',
                        'maxlength' => 15,
                        'minlength' => 6,
                    ])
                    ->dehydrateStateUsing(fn ($state) => blank($state)
                        ? null
                        : Str::lower(trim((string) $state))
                    )
                    ->validationMessages([
                        'required' => __('app.username_required'),
                        'unique' => __('app.username_unique'),
                        'min' => __('app.username_min'),
                        'max' => __('app.username_max'),
                        'regex' => __('app.username_rule'),
                        'not_regex' => __('app.username_repeating'),
                    ])
                    ->autofocus(),

                TextInput::make('phone')
                    ->label(__('app.phone_number'))
                    ->placeholder(__('app.enter_phone_number'))
                    ->required(fn ($get): bool => $get('registration_type') === 'national_exam')
                    ->tel()
                    ->inputMode('numeric')
                    ->minLength(9)
                    ->maxLength(10)
                    ->rules([
                        'nullable',
                        'regex:/^[0-9]{9,10}$/',
                    ])
                    ->validationMessages([
                        'required' => __('app.phone_required'),
                        'regex' => __('app.phone_regex'),
                        'min' => __('app.phone_min'),
                        'max' => __('app.phone_max'),
                        'unique' => __('app.phone_unique'),
                    ])
                    ->unique(User::class, 'phone')
                    ->prefixIcon('heroicon-o-phone')
                    ->extraInputAttributes([
                        'oninput' => "this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)",
                        'maxlength' => 10,
                    ])
                    ->dehydrateStateUsing(fn ($state) => blank($state)
                        ? null
                        : preg_replace('/[^0-9]/', '', (string) $state)
                    ),

                TextInput::make('email')
                    ->label(__('app.email_address'))
                    ->placeholder(__('app.enter_email_address'))
                    ->prefixIcon('heroicon-o-envelope')
                    ->email()
                    ->nullable()
                    ->maxLength(255)
                    ->unique(User::class, 'email')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? Str::lower(trim($state))
                        : null
                    )
                    ->validationMessages([
                        'email' => __('app.email_invalid'),
                        'unique' => __('app.email_unique'),
                    ]),


                Select::make('academic_year')
                    ->label(__('app.academic_year'))
                    ->placeholder(__('app.select_academic_year'))
                    ->options(self::getAcademicYearOptions())
                    ->required(fn ($get): bool => $get('registration_type') === 'enrollment')
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'enrollment')
                    ->dehydrated(fn ($get): bool => $get('registration_type') === 'enrollment')
                    ->native(false)
                    ->searchable()
                    ->prefixIcon('heroicon-o-calendar')
                    ->validationMessages([
                        'required' => __('app.academic_year_required'),
                    ]),

                TextInput::make('seat_number')
                    ->label(__('app.seat_number'))
                    ->placeholder(__('app.enter_seat_number'))
                    ->required(fn ($get): bool => $get('registration_type') === 'enrollment')
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'enrollment')
                    ->dehydrated(fn ($get): bool => $get('registration_type') === 'enrollment')
                    ->maxLength(50)
                    ->unique(User::class, 'seat_number')
                    ->prefixIcon('heroicon-o-hashtag')
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                    ->validationMessages([
                        'required' => __('app.seat_number_required'),
                        'unique' => __('app.seat_number_unique'),
                    ]),

                DatePicker::make('date_of_birth')
                    ->label(__('app.date_of_birth'))
                    ->placeholder(__('app.select_date_of_birth'))
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->prefixIcon('heroicon-o-calendar-days')
                    ->maxDate(now())
                    ->validationMessages([
                        'required' => __('app.date_of_birth_required'),
                    ]),

                TextInput::make('password')
                    ->label(__('app.password'))
                    ->placeholder(__('app.enter_password'))
                    ->prefixIcon('heroicon-o-lock-closed')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->autocomplete('new-password')
                    ->rules([
                        'required',
                        'string',
                        'min:8',
                        'regex:/^[!-~]+$/',
                    ])
                    ->extraInputAttributes([
                        'inputmode' => 'latin',
                        'autocomplete' => 'new-password',
                        'onkeydown' => 'if (event.key === " ") event.preventDefault();',
                        'onbeforeinput' => 'if (event.data && /[^\x21-\x7E]/.test(event.data)) event.preventDefault();',
                        'oninput' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                        'oncompositionend' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                        'onpaste' => 'setTimeout(() => { this.value = this.value.replace(/[^\x21-\x7E]/g, ""); }, 0);',
                        'onblur' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                    ])
                    ->validationMessages([
                        'required' => __('app.password_required'),
                        'min' => __('app.password_min'),
                        'confirmed' => __('app.password_confirmed'),
                        'regex' => __('app.password_english_only'),
                    ]),

                TextInput::make('password_confirmation')
                    ->label(__('app.confirm_password'))
                    ->placeholder(__('app.enter_confirm_password'))
                    ->prefixIcon('heroicon-o-lock-closed')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password')
                    ->autocomplete('new-password')
                    ->rules([
                        'required',
                        'string',
                        'regex:/^[!-~]+$/',
                    ])
                    ->extraInputAttributes([
                        'inputmode' => 'latin',
                        'autocomplete' => 'new-password',
                        'onkeydown' => 'if (event.key === " ") event.preventDefault();',
                        'onbeforeinput' => 'if (event.data && /[^\x21-\x7E]/.test(event.data)) event.preventDefault();',
                        'oninput' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                        'oncompositionend' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                        'onpaste' => 'setTimeout(() => { this.value = this.value.replace(/[^\x21-\x7E]/g, ""); }, 0);',
                        'onblur' => 'this.value = this.value.replace(/[^\x21-\x7E]/g, "");',
                    ])
                    ->validationMessages([
                        'required' => __('app.confirm_password_required'),
                        'same' => __('app.confirm_password_same'),
                        'regex' => __('app.password_english_only'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Model
    {
        $registrationType = $data['registration_type'] ?? 'national_exam';

        $username = trim((string) ($data['username'] ?? ''));

        $phone = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);
        return User::create([
            'registration_type' => $registrationType,
            'academic_year' => $registrationType === 'enrollment'
                ? ($data['academic_year'] ?? null)
                : null,
            'name' => $username,
            'name_latin' => null,
            'username' => $username,
            'email' => filled($data['email'] ?? null)
                ? Str::lower(trim((string) $data['email']))
                : null,
            'phone' => $phone,
            'date_of_birth' => $data['date_of_birth'],
            'seat_number' => $registrationType === 'enrollment'
                ? trim((string) ($data['seat_number'] ?? ''))
                : null,

            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
    }

    protected static function getAcademicYearOptions(): array
    {
        $currentYear = now()->year;

        return [
            ($currentYear - 1) . '-' . $currentYear => ($currentYear - 1) . '-' . $currentYear,
            $currentYear . '-' . ($currentYear + 1) => $currentYear . '-' . ($currentYear + 1),
            ($currentYear + 1) . '-' . ($currentYear + 2) => ($currentYear + 1) . '-' . ($currentYear + 2),
        ];
    }
}
