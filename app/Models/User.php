<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use Notifiable;
    use SoftDeletes;
    use HasRoles;

    protected $fillable = [
        'registration_type',
        'academic_year',
        'name',
        'name_latin',
        'username',
        'email',
        'email_verified_at',
        'phone',
        'date_of_birth',
        'seat_number',
        'avatar',
        'is_active',
        'password',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'app') {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        return $this->roles()->exists()
            || $this->permissions()->exists()
            || $this->linkedSystemUser()?->isActiveAccount()
            || $this->effectiveRoleNames()->isNotEmpty()
            || in_array($this->registration_type, ['admin', 'student'], true);
    }

    public function hasEffectiveRole(array | string $roles): bool
    {
        $roles = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn ($role): string => Str::lower(trim((string) $role)))
            ->all();

        return $this->effectiveRoleNames()
            ->intersect($roles)
            ->isNotEmpty();
    }

    public function effectiveRoleNames(): Collection
    {
        $loginRoles = $this->getRoleNames()
            ->map(fn (string $role): string => Str::lower(trim($role)));

        $systemRoles = collect($this->linkedSystemUser()?->roles ?? [])
            ->when(
                is_string($this->linkedSystemUser()?->roles),
                function (Collection $collection): Collection {
                    $rawRoles = $this->linkedSystemUser()?->roles;
                    $decoded = json_decode((string) $rawRoles, true);

                    return collect(is_array($decoded) ? $decoded : [$rawRoles]);
                }
            )
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => Str::lower(trim((string) $role)));

        return $loginRoles
            ->merge($systemRoles)
            ->unique()
            ->values();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatar = $this->normalizeAvatarPath($this->avatar);

        if (blank($avatar)) {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        if (! Storage::disk('public')->exists($avatar)) {
            return null;
        }

        return Storage::disk('public')->url($avatar);
    }

    public function studentUser(): HasOne
    {
        return $this->hasOne(\App\Models\SystemUser::class);
    }

    public function linkedSystemUser(): ?SystemUser
    {
        return SystemUser::query()
            ->when(filled($this->username), fn ($query) => $query->orWhere('username', $this->username))
            ->when(filled($this->email), fn ($query) => $query->orWhere('email', $this->email))
            ->when(filled($this->phone), fn ($query) => $query->orWhere('phone', $this->phone))
            ->first();
    }

    private function normalizeAvatarPath(mixed $avatar): ?string
    {
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
            $path = parse_url($avatar, PHP_URL_PATH);

            if (! is_string($path) || ! Str::startsWith($path, ['/storage/', '/public/'])) {
                return $avatar;
            }

            $avatar = $path;
        }

        return Str::of($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();
    }
}
