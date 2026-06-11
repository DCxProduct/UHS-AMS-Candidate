<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getTitle(): string | Htmlable
    {
        return __('app.sign_in');
    }

    public function getHeading(): string | Htmlable
    {
        return __('app.sign_in');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('app.sign_in'))
            ->submit('authenticate');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('login')
                    ->label(__('app.username_email_phone'))
                    ->placeholder(__('app.username_email_phone'))
                    ->required()
                    ->autofocus()
                    ->autocomplete('username')
                    ->prefixIcon('heroicon-o-user-circle')
                    ->validationMessages([
                        'required' => __('app.username_email_phone'),
                    ]),

                TextInput::make('password')
                    ->label(__('app.password'))
                    ->placeholder(__('app.enter_password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->autocomplete('current-password')
                    ->prefixIcon('heroicon-o-lock-closed')
                    ->validationMessages([
                        'required' => __('app.password_required'),
                    ]),

                Checkbox::make('remember')
                    ->label(__('app.remember_me')),
            ])
            ->statePath('data');
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            throw ValidationException::withMessages([
                'data.login' => __('app.too_many_login_attempts', [
                    'seconds' => $exception->secondsUntilAvailable,
                ]),
            ]);
        }

        $data = $this->form->getState();

        $login = trim((string) ($data['login'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $remember = (bool) ($data['remember'] ?? false);

        $normalizedLogin = Str::lower($login);
        $normalizedPhone = preg_replace('/[^0-9]/', '', $login);

        $user = User::query()
            ->where(function ($query) use ($normalizedLogin, $normalizedPhone) {
                $query
                    ->whereRaw('LOWER(username) = ?', [$normalizedLogin])
                    ->orWhereRaw('LOWER(email) = ?', [$normalizedLogin]);

                if (! blank($normalizedPhone)) {
                    $query->orWhere('phone', $normalizedPhone);
                }

                $query
                    ->orWhere(function ($nationalExamQuery) use ($normalizedLogin) {
                        $nationalExamQuery
                            ->where('registration_type', 'national_exam')
                            ->whereRaw('LOWER(name) = ?', [$normalizedLogin]);
                    })
                    ->orWhere(function ($nationalExamQuery) use ($normalizedLogin) {
                        $nationalExamQuery
                            ->where('registration_type', 'national_exam')
                            ->whereRaw('LOWER(name_latin) = ?', [$normalizedLogin]);
                    });
            })
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'data.login' => __('app.invalid_credentials'),
            ]);
        }

        if (! (bool) $user->is_active) {
            throw ValidationException::withMessages([
                'data.login' => __('app.account_inactive'),
            ]);
        }

        if (
            $user instanceof FilamentUser &&
            ! $user->canAccessPanel(Filament::getCurrentPanel())
        ) {
            throw ValidationException::withMessages([
                'data.login' => __('app.no_panel_permission'),
            ]);
        }

        Filament::auth()->login($user, $remember);

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
