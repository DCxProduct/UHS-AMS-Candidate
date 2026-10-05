<?php

namespace App\Filament\Student\Pages;

use App\Models\User;
use App\Support\CandidateDisplayName;
use App\Support\NotificationLanguage;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.student.pages.my-profile';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('student_profile.my_profile');
    }

    public function getHeading(): string
    {
        return __('student_profile.my_profile');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $nameParts = CandidateDisplayName::partsFor($user);

        $this->form->fill([
            'first_name_en' => $nameParts['first_name_en'],
            'last_name_en' => $nameParts['last_name_en'],
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $this->normalizeAvatar($user->avatar),
            'password' => null,
            'password_confirmation' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->model(Auth::user())
            ->columns(12)
            ->components([
                Section::make(__('student_profile.profile_information'))
                    ->description(__('student_profile.profile_information_description'))
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('first_name_en')
                                ->label(__('student_profile.first_name_latin'))
                                ->placeholder(__('student_profile.enter_first_name_latin'))
                                ->required()
                                ->maxLength(100)
                                ->rules([
                                    'required',
                                    'string',
                                    'max:100',
                                    "regex:/^[A-Za-z][A-Za-z .'-]*$/",
                                ])
                                ->validationMessages([
                                    'required' => __('student_profile.first_name_latin_required'),
                                    'regex' => __('student_profile.latin_name_regex'),
                                ])
                                ->dehydrateStateUsing(fn (?string $state): string => self::normalizeLatinNamePart($state))
                                ->columnSpan(6),

                            TextInput::make('last_name_en')
                                ->label(__('student_profile.last_name_latin'))
                                ->placeholder(__('student_profile.enter_last_name_latin'))
                                ->required()
                                ->maxLength(100)
                                ->rules([
                                    'required',
                                    'string',
                                    'max:100',
                                    "regex:/^[A-Za-z][A-Za-z .'-]*$/",
                                ])
                                ->validationMessages([
                                    'required' => __('student_profile.last_name_latin_required'),
                                    'regex' => __('student_profile.latin_name_regex'),
                                ])
                                ->dehydrateStateUsing(fn (?string $state): string => self::normalizeLatinNamePart($state))
                                ->columnSpan(6),

                            TextInput::make('email')
                                ->label(__('student_profile.email_address'))
                                ->email()
                                ->maxLength(255)
                                ->rules([
                                    fn () => Rule::unique('users', 'email')->ignore(Auth::id()),
                                ])
                                ->columnSpan(6),

                            TextInput::make('phone')
                                ->label(__('student_profile.phone_number'))
                                ->tel()
                                ->maxLength(255)
                                ->rules([
                                    fn () => Rule::unique('users', 'phone')->ignore(Auth::id()),
                                ])
                                ->columnSpan(6),

                            FileUpload::make('avatar')
                                ->label(__('student_profile.avatar'))
                                ->placeholder(__('student_profile.choose_image'))
                                ->image()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->disk('public')
                                ->directory('avatars')
                                ->visibility('public')
                                ->imageEditor()
                                ->panelLayout('integrated')
                                ->downloadable()
                                ->openable()
                                ->maxSize(2048)
                                ->deletable()
                                ->deleteUploadedFileUsing(fn () => null)
                                ->columnSpan(12),
                        ]),
                    ])
                    ->columnSpan(12),

                Section::make(__('student_profile.change_password'))
                    ->description(__('student_profile.change_password_description'))
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('password')
                                ->label(__('student_profile.new_password'))
                                ->password()
                                ->revealable()
                                ->rule(Password::default())
                                ->same('password_confirmation')
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->columnSpan(6),

                            TextInput::make('password_confirmation')
                                ->label(__('student_profile.confirm_new_password'))
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->columnSpan(6),
                        ]),
                    ])
                    ->columnSpan(12),
            ]);
    }

    public function save(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $data = $this->form->getState();
        $fullName = trim(implode(' ', array_filter([
            self::normalizeLatinNamePart($data['first_name_en'] ?? null),
            self::normalizeLatinNamePart($data['last_name_en'] ?? null),
        ])));

        $payload = [
            'name' => $fullName ?: $user->name,
            'name_latin' => $fullName ?: $user->name_latin,
            'email' => $data['email'] ?? $user->email,
            'phone' => $data['phone'] ?? null,
            'avatar' => array_key_exists('avatar', $data)
                ? $this->normalizeAvatar($data['avatar'])
                : $this->normalizeAvatar($user->avatar),
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        $user->linkedSystemUser()?->update([
            'name' => $payload['name'],
        ]);

        $freshUser = $user->fresh();
        $nameParts = CandidateDisplayName::partsFor($freshUser);

        Auth::setUser($freshUser);

        $this->form->fill([
            'first_name_en' => $nameParts['first_name_en'],
            'last_name_en' => $nameParts['last_name_en'],
            'email' => $freshUser->email,
            'phone' => $freshUser->phone,
            'avatar' => $this->normalizeAvatar($freshUser->avatar),
            'password' => null,
            'password_confirmation' => null,
        ]);

        Notification::make()
            ->title(NotificationLanguage::trans('student_profile.updated_successfully'))
            ->success()
            ->send();

        $this->redirect(static::getUrl(), navigate: false);
    }

    private function normalizeAvatar(mixed $avatar): ?string
    {
        if (blank($avatar)) {
            return null;
        }

        if (is_string($avatar) && str_starts_with(trim($avatar), '[')) {
            $decoded = json_decode($avatar, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $avatar = $decoded;
            }
        }

        if (is_array($avatar)) {
            $avatar = collect($avatar)->first();
        }

        $avatar = trim((string) $avatar);

        if ($avatar === '' || $avatar === 'Array') {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            $path = parse_url($avatar, PHP_URL_PATH);

            if (is_string($path) && $path !== '') {
                $avatar = $path;
            }
        }

        return str($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();
    }

    private static function normalizeLatinNamePart(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }
}
