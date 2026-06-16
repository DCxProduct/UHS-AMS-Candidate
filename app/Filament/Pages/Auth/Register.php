<?php

namespace App\Filament\Pages\Auth;

use App\Models\SystemUser;
use App\Models\User;
use Carbon\Carbon;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
                Hidden::make('registration_type')
                    ->default('student')
                    ->dehydrated(true),

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
                        'regex:/^[a-z0-9_]+$/',
                        Rule::unique('system_users', 'username'),
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
                        'regex' => __('app.username_english_only'),
                    ])
                    ->autofocus(),

                TextInput::make('phone')
                    ->label(__('app.phone_number'))
                    ->placeholder(__('app.enter_phone_number'))
                    ->required()
                    ->tel()
                    ->inputMode('numeric')
                    ->minLength(9)
                    ->maxLength(10)
                    ->rules([
                        'required',
                        'regex:/^[0-9]{9,10}$/',
                        Rule::unique('system_users', 'phone'),
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
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email')
                    ->rules([
                        'required',
                        'email',
                        Rule::unique('system_users', 'email'),
                    ])
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state)
                        ? Str::lower(trim($state))
                        : null
                    )
                    ->validationMessages([
                        'required' => __('app.email_required'),
                        'email' => __('app.email_invalid'),
                        'unique' => __('app.email_unique'),
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
        $username = Str::lower(trim((string) ($data['username'] ?? '')));

        $phone = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        $email = Str::lower(trim((string) ($data['email'] ?? '')));

        $dateOfBirth = Carbon::parse($data['date_of_birth'])->format('Y-m-d');

        return DB::transaction(function () use (
            $username,
            $phone,
            $email,
            $dateOfBirth,
            $data,
        ): Model {
            $hashedPassword = Hash::make($data['password']);

            /*
            |--------------------------------------------------------------------------
            | Store login account in users table
            |--------------------------------------------------------------------------
            */
            $user = User::query()->create([
                'registration_type' => 'student',
                'academic_year' => null,
                'name' => $username,
                'name_latin' => null,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'date_of_birth' => $dateOfBirth,
                'seat_number' => null,
                'avatar' => null,
                'password' => $hashedPassword,
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Store student in system_users table
            |--------------------------------------------------------------------------
            */
            SystemUser::query()->create([
                'name' => $username,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'password' => $hashedPassword,
                'avatar' => null,
                'roles' => [
                    'Student',
                ],
                'permissions' => null,
                'is_active' => true,
                'email_verified_at' => now(),
                'last_login_at' => null,
            ]);

            return $user;
        });
    }
}
