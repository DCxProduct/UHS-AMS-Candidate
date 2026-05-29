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
                    ->label(__('app.register_as'))
                    ->options([
                        'enrollment' => __('app.enrollment'),
                        'national_exam' => __('app.national_examination'),
                    ])
                    ->icons([
                        'enrollment' => 'heroicon-o-academic-cap',
                        'national_exam' => 'heroicon-o-clipboard-document-check',
                    ])
                    ->colors([
                        'enrollment' => 'warning',
                        'national_exam' => 'warning',
                    ])
                    ->default('enrollment')
                    ->inline()
                    ->live()
                    ->markAsRequired(false)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'uhs-register-type-box-center',
                    ]),

                TextInput::make('username')
                    ->label(__('app.username'))
                    ->placeholder(__('app.enter_username'))
                    ->required(fn ($get): bool => $get('registration_type') === 'enrollment')
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'enrollment')
                    ->maxLength(255)
                    ->unique(User::class, 'username')
                    ->prefixIcon('heroicon-o-identification')
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                    ->validationMessages([
                        'required' => __('app.username_required'),
                        'unique' => __('app.username_unique'),
                    ])
                    ->autofocus(),

                TextInput::make('email')
                    ->label(__('app.email_address'))
                    ->placeholder(__('app.enter_email_address'))
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'enrollment')
                    ->prefixIcon('heroicon-o-envelope')
                    ->email()
                    ->nullable()
                    ->maxLength(255)
                    ->unique(User::class, 'email')
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? Str::lower(trim($state))
                        : null)
                    ->validationMessages([
                        'email' => __('app.email_invalid'),
                        'unique' => __('app.email_unique'),
                    ]),

                TextInput::make('phone')
                    ->label(__('app.phone_number'))
                    ->placeholder(__('app.enter_phone_number'))
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'enrollment')
                    ->required(fn ($get): bool => $get('registration_type') === 'enrollment')
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

                TextInput::make('national_exam_name')
                    ->label(__('app.name'))
                    ->placeholder(__('app.enter_name'))
                    ->required(fn ($get): bool => $get('registration_type') === 'national_exam')
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'national_exam')
                    ->maxLength(255)
                    ->prefixIcon('heroicon-o-user')
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                    ->validationMessages([
                        'required' => __('app.name_required'),
                    ]),

                TextInput::make('seat_number')
                    ->label(__('app.seat_number'))
                    ->placeholder(__('app.enter_seat_number'))
                    ->required(fn ($get): bool => $get('registration_type') === 'national_exam')
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'national_exam')
                    ->maxLength(50)
                    ->unique(User::class, 'seat_number')
                    ->prefixIcon('heroicon-o-hashtag')
                    ->dehydrateStateUsing(fn ($state) => blank($state) ? null : trim((string) $state))
                    ->validationMessages([
                        'required' => __('app.seat_number_required'),
                        'unique' => __('app.seat_number_unique'),
                    ]),

                Select::make('academic_year')
                    ->label(__('app.academic_year'))
                    ->placeholder(__('app.select_academic_year'))
                    ->options(self::getAcademicYearOptions())
                    ->hidden(fn ($get): bool => $get('registration_type') !== 'national_exam')
                    ->required(fn ($get): bool => $get('registration_type') === 'national_exam')
                    ->dehydrated(fn ($get): bool => $get('registration_type') === 'national_exam')
                    ->native(false)
                    ->searchable()
                    ->prefixIcon('heroicon-o-calendar')
                    ->validationMessages([
                        'required' => __('app.academic_year_required'),
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
                    ->validationMessages([
                        'required' => __('app.password_required'),
                        'min' => __('app.password_min'),
                        'confirmed' => __('app.password_confirmed'),
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
                    ->validationMessages([
                        'required' => __('app.confirm_password_required'),
                        'same' => __('app.confirm_password_same'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Model
    {
        $registrationType = $data['registration_type'] ?? 'enrollment';

        if ($registrationType === 'national_exam') {
            $name = trim((string) $data['national_exam_name']);
            $seatNumber = trim((string) $data['seat_number']);

            return User::create([
                'registration_type' => 'national_exam',
                'academic_year' => $data['academic_year'] ?? null,
                'name' => $name,

                // User can login with this seat number because we save it as username too.
                'username' => $seatNumber,

                'email' => null,
                'phone' => null,
                'date_of_birth' => $data['date_of_birth'],
                'seat_number' => $seatNumber,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);
        }

        $username = trim((string) $data['username']);
        $phone = preg_replace('/[^0-9]/', '', (string) $data['phone']);

        return User::create([
            'registration_type' => 'enrollment',
            'academic_year' => null,
            'name' => $username,
            'username' => $username,
            'email' => filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null,
            'phone' => $phone,
            'date_of_birth' => $data['date_of_birth'],
            'seat_number' => null,
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
