<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
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

    public function canAccessPanel(Panel $panel): bool
    {
        if (! (bool) $this->is_active) {
            return false;
        }

        if ($panel->getId() === 'app') {
            return in_array((string) $this->registration_type, [
                'admin',
                'student',
            ], true);
        }

        return false;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if (blank($this->avatar)) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }
}
