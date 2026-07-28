<?php

namespace App\Filament\Pages\Auth;

use App\Models\SystemUser;
use App\Models\User;
use Carbon\Carbon;
use Filament\Auth\Events\Registered;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportRedirects\Redirector;
use App\Support\UserTypeOptions;

class Register extends BaseRegister
{
    public function mount(): void
    {
        $this->refreshCaptchaChallenge();

        parent::mount();
    }

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
        $this->ensureCaptchaChallenge();

        return $schema
            ->components([
                ToggleButtons::make('student_role')
                    ->label(__('app.candidate_type'))
                    ->options($this->getUserTypeOptions())
                    ->colors($this->getUserTypeColors())
                    ->default($this->getDefaultUserTypeRole())
                    ->required()
                    ->inline()
                    ->live()
                    ->dehydrateStateUsing(fn (?string $state): string => $this->resolveSelectedUserTypeRole($state))
                    ->validationMessages([
                        'required' => __('app.candidate_type_required'),
                    ])
                    ->extraFieldWrapperAttributes([
                        'class' => 'uhs-register-type-wrapper uhs-register-type-box-center',
                    ]),

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
                    ->nullable()
                    ->maxLength(255)
                    ->rules([
                        'nullable',
                        'email',
                        Rule::unique('users', 'email'),
                        Rule::unique('system_users', 'email'),
                    ])
                    ->dehydrateStateUsing(
                        fn (?string $state): ?string => filled($state)
                            ? Str::lower(trim($state))
                            : null
                    )
                    ->validationMessages([
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

                Placeholder::make('captcha_preview')
                    ->label(__('app.captcha'))
                    ->content(fn (): HtmlString => new HtmlString($this->captchaPreviewHtml()))
                    ->columnSpanFull(),

                TextInput::make('captcha_answer')
                    ->label(__('app.captcha_answer'))
                    ->helperText(__('app.captcha_question'))
                    ->placeholder(__('app.enter_captcha_answer'))
                    ->required()
                    ->minLength(7)
                    ->maxLength(7)
                    ->prefixIcon('heroicon-o-shield-check')
                    ->validationMessages([
                        'required' => __('app.captcha_required'),
                        'min' => __('app.captcha_length'),
                        'max' => __('app.captcha_length'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        if (trim((string) ($data['captcha_answer'] ?? '')) !== (string) session('register_captcha_answer')) {
            $this->refreshCaptchaChallengeForForm();

            throw ValidationException::withMessages([
                'data.captcha_answer' => __('app.captcha_invalid'),
            ]);
        }

        unset($data['captcha_answer']);

        $this->refreshCaptchaChallengeForForm();

        return $data;
    }

    public function register(): ?RegistrationResponse
    {
        if ($this->isRegisterRateLimited($this->data['email'] ?? '')) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeRegister($data);

            $this->callHook('beforeRegister');

            $user = $this->handleRegistration($data);

            $this->form->model($user)->saveRelationships();

            $this->callHook('afterRegister');

            return $user;
        });

        event(new Registered($user));

        $this->sendEmailVerificationNotification($user);

        Notification::make()
            ->title(__('app.register_success_title'))
            ->body(__('app.register_success_body'))
            ->success()
            ->send();

        return new class implements RegistrationResponse
        {
            public function toResponse($request): RedirectResponse | Redirector
            {
                return redirect()->route('filament.app.auth.login');
            }
        };
    }

    protected function handleRegistration(array $data): Model
    {
        $username = Str::lower(trim((string) ($data['username'] ?? '')));
        $studentRole = UserTypeOptions::resolve($data['student_role'] ?? null);

        $phone = blank($data['phone'] ?? null)
            ? null
            : preg_replace('/[^0-9]/', '', (string) $data['phone']);

        $email = filled($data['email'] ?? null)
            ? Str::lower(trim((string) $data['email']))
            : null;

        $dateOfBirth = Carbon::parse($data['date_of_birth'])->format('Y-m-d');

        return DB::transaction(function () use (
            $username,
            $studentRole,
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

            $webRoles = UserTypeOptions::assignableWebRoles($studentRole);

            if ($webRoles !== []) {
                $user->syncRoles($webRoles);
            }

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
                'roles' => UserTypeOptions::assignableSystemRoles($studentRole),
                'permissions' => null,
                'is_active' => true,
                'email_verified_at' => now(),
                'last_login_at' => null,
            ]);

            return $user;
        });
    }

    protected function ensureCaptchaChallenge(): void
    {
        if (session()->has('register_captcha_answer')) {
            return;
        }

        $this->refreshCaptchaChallenge();
    }

    protected function refreshCaptchaChallenge(): void
    {
        $characters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = collect(range(1, 7))
            ->map(fn (): string => $characters[random_int(0, strlen($characters) - 1)])
            ->join('');

        session([
            'register_captcha_answer' => $code,
        ]);
    }

    public function refreshCaptchaChallengeForForm(): void
    {
        $this->refreshCaptchaChallenge();

        data_set($this->data, 'captcha_answer', null);
    }

    protected function getUserTypeOptions(): array
    {
        $options = UserTypeOptions::customQuery()
            ->get()
            ->filter(fn ($userType): bool => in_array((string) $userType->key, ['candidate', 'associate'], true))
            ->mapWithKeys(fn ($userType): array => [
                $userType->key => $userType->getLocalizedLabel(),
            ])
            ->all();

        return $options !== []
            ? $options
            : [
                'candidate' => __('app.candidate'),
                'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            ];
    }

    protected function getDefaultUserTypeRole(): string
    {
        return array_key_first($this->getUserTypeOptions()) ?? 'candidate';
    }

    protected function getUserTypeColors(): array
    {
        $colors = UserTypeOptions::customQuery()
            ->get()
            ->filter(fn ($userType): bool => in_array((string) $userType->key, ['candidate', 'associate'], true))
            ->mapWithKeys(fn ($userType): array => [
                $userType->key => UserTypeOptions::normalizeColor($userType->color),
            ])
            ->all();

        return $colors !== []
            ? $colors
            : [
                'candidate' => 'primary',
                'associate' => 'warning',
            ];
    }

    protected function resolveSelectedUserTypeRole(?string $roleName): string
    {
        return UserTypeOptions::resolve($roleName);
    }

    protected function captchaPreviewHtml(): string
    {
        $code = e((string) session('register_captcha_answer'));
        $refreshLabel = e(__('app.refresh_captcha'));

        return <<<HTML
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="min-width:190px; padding:10px 16px; border:1px solid #d1d5db; background:
                    repeating-linear-gradient(35deg, rgba(17,24,39,.16) 0, rgba(17,24,39,.16) 1px, transparent 1px, transparent 8px),
                    repeating-linear-gradient(120deg, rgba(17,24,39,.12) 0, rgba(17,24,39,.12) 1px, transparent 1px, transparent 10px),
                    #f8fafc;
                    color:#111827; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                    font-size:32px; font-weight:800; letter-spacing:6px; line-height:1; user-select:none; transform:skew(-5deg);">
                    {$code}
                </div>
                <button type="button" wire:click="refreshCaptchaChallengeForForm" style="height:42px; min-width:42px; border:1px solid #d1d5db; border-radius:8px; background:#ffffff; color:#374151; font-size:18px; cursor:pointer;" title="{$refreshLabel}" aria-label="{$refreshLabel}">
                    &#8635;
                </button>
            </div>
        HTML;
    }
}
