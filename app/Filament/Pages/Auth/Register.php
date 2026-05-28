<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
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
                TextInput::make('username')
                    ->label(__('app.username'))
                    ->placeholder(__('app.enter_username'))
                    ->required()
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
                    ->required()
                    ->tel()
                    ->inputMode('numeric')
                    ->minLength(9)
                    ->maxLength(10)
                    ->rules([
                        'required',
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
        $username = trim((string) $data['username']);
        $phone = preg_replace('/[^0-9]/', '', (string) $data['phone']);

        return User::create([
            'name' => $username,
            'username' => $username,
            'email' => filled($data['email'] ?? null) ? Str::lower(trim($data['email'])) : null,
            'phone' => $phone,
            'date_of_birth' => $data['date_of_birth'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);
    }
}