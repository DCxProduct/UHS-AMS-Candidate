<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    use Notifiable;
    use SoftDeletes;

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

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() !== 'app') {
            return false;
        }

        return $this->is_active === true
            && in_array($this->registration_type, ['admin', 'student'], true);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }

    public function studentUser(): HasOne
    {
        return $this->hasOne(\App\Models\SystemUser::class);
    }
}
