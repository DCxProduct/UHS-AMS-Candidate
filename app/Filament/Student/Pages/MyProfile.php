<?php

namespace App\Filament\Student\Pages;

use App\Models\User;
use Filament\Actions\Action;
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

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();

        $this->form->fill([
            'name' => $user->name,
            'username' => $user->username,
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
            ->columns(12)
            ->components([
                Section::make(__('student_profile.profile_information'))
                    ->description(__('student_profile.profile_information_description'))
                    ->schema([
                        Grid::make(12)->schema([
                            TextInput::make('name')
                                ->label(__('student_profile.full_name'))
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(6),

                            TextInput::make('username')
                                ->label(__('student_profile.username'))
                                ->maxLength(255)
                                ->rules([
                                    fn () => Rule::unique('users', 'username')->ignore(Auth::id()),
                                ])
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
                                ->image()
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

        $payload = [
            'name' => $data['name'] ?? $user->name,
            'username' => $data['username'] ?? null,
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

        $freshUser = $user->fresh();

        $this->form->fill([
            'name' => $freshUser->name,
            'username' => $freshUser->username,
            'email' => $freshUser->email,
            'phone' => $freshUser->phone,
            'avatar' => $this->normalizeAvatar($freshUser->avatar),
            'password' => null,
            'password_confirmation' => null,
        ]);

        Notification::make()
            ->title(__('student_profile.updated_successfully'))
            ->success()
            ->send();
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

        return str($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();
    }
}
