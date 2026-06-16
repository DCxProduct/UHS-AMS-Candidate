<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SystemUser extends Authenticatable implements FilamentUser, HasAvatar, HasName, CanResetPasswordContract
{
    use Notifiable;
    use SoftDeletes;
    use CanResetPassword;

    protected $table = 'system_users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'roles',
        'permissions',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'roles' => 'array',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'system_user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'app') {
            return false;
        }

        if (! $this->isActiveAccount()) {
            return false;
        }

        return $this->hasJsonRole([
            'Developer',
            'Admin',
            'Finance',
            'Cashier',
            'Registrar',
            'Team UHS',
            'Processing',
            'Student',
        ]);
    }

    public function hasJsonRole(array | string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        $userRoles = $this->roles;

        if (is_string($userRoles)) {
            $decoded = json_decode($userRoles, true);
            $userRoles = is_array($decoded) ? $decoded : [$userRoles];
        }

        if (! is_array($userRoles)) {
            $userRoles = [];
        }

        return collect($userRoles)
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->intersect(
                collect($roles)->map(fn ($role): string => strtolower(trim((string) $role)))
            )
            ->isNotEmpty();
    }

    public function activateAccount(): void
    {
        $this->forceFill([
            'is_active' => true,
        ])->save();

        $this->syncLoginUser();
    }

    public function deactivateAccount(): void
    {
        $this->forceFill([
            'is_active' => false,
        ])->save();

        $this->syncLoginUser();
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active;
    }

    public function syncLoginUser(): void
    {
        $lookup = $this->getLoginUserLookup();

        if (empty($lookup)) {
            return;
        }

        $existingUser = User::query()
            ->where($lookup)
            ->first();

        User::query()->updateOrCreate(
            $lookup,
            [
                'registration_type' => $this->getLoginRegistrationType(),
                'academic_year' => $existingUser?->academic_year,
                'name' => $this->name ?: $this->username ?: $this->email ?: 'System User',
                'name_latin' => $existingUser?->name_latin,
                'username' => $this->username,
                'email' => $this->email,
                'phone' => $this->phone,

                'date_of_birth' => $existingUser?->date_of_birth ?? '2000-01-01',

                'seat_number' => $existingUser?->seat_number,
                'avatar' => $this->avatar,
                'password' => $this->password,
                'email_verified_at' => $this->email_verified_at ?? now(),
                'is_active' => (bool) $this->is_active,
            ]
        );
    }

    protected function getLoginUserLookup(): array
    {
        if (filled($this->username)) {
            return [
                'username' => $this->username,
            ];
        }

        if (filled($this->email)) {
            return [
                'email' => $this->email,
            ];
        }

        if (filled($this->phone)) {
            return [
                'phone' => $this->phone,
            ];
        }

        return [];
    }

    protected function getLoginRegistrationType(): string
    {
        return $this->hasJsonRole('Student') ? 'student' : 'admin';
    }

    public function getFilamentName(): string
    {
        return $this->name
            ?: $this->username
                ?: $this->email
                    ?: 'System User';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatar = $this->avatar;

        if (blank($avatar)) {
            return null;
        }

        if (is_string($avatar) && Str::startsWith(trim($avatar), ['[', '{'])) {
            $decoded = json_decode($avatar, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $avatar = $decoded;
            }
        }

        if (is_array($avatar)) {
            $avatar = collect($avatar)
                ->flatten()
                ->filter()
                ->first();
        }

        $avatar = trim((string) $avatar);

        if ($avatar === '' || $avatar === 'Array') {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        $avatar = Str::of($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();

        if (! Storage::disk('public')->exists($avatar)) {
            return null;
        }

        return Storage::disk('public')->url($avatar);
    }
}
