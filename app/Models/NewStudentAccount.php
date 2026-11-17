<?php

namespace App\Models;

use App\Support\AccountContacts;
use App\Support\AccountContactUniqueness;
use App\Support\AccountStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class NewStudentAccount extends Model
{
    protected $fillable = [
        'student_id',
        'email',
        'phone',
        'first_name_latin',
        'last_name_latin',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [
        'user_id',
        'source_candidate_id',
        'candidate_roles',
        'auth_id',
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'candidate_roles' => 'array',
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $account): void {
            $account->email = AccountContacts::email($account->email);
            $account->phone = AccountContacts::phone($account->phone);
            if (! AccountStorage::ready()) {
                return;
            }
            if ($account->auth_id === null) {
                $identity = AccountIdentity::query()->create(['storage' => 'student']);
                $account->forceFill(['auth_id' => $identity->id, 'user_id' => $identity->id]);
            }
            $name = trim($account->first_name_latin.' '.$account->last_name_latin);
            $username = 'student_'.mb_strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', $account->student_id));
            if (AccountUser::withTrashed()->where('username', $username)->where('id', '!=', $account->auth_id)->exists()) {
                $username .= '_'.$account->auth_id;
            }
            $account->forceFill([
                'registration_type' => 'student',
                'name' => $account->name ?: $name,
                'name_latin' => $account->isDirty(['first_name_latin', 'last_name_latin']) ? $name : ($account->name_latin ?: $name),
                'username' => $account->username ?: $username,
                'password' => $account->password ?: Hash::make(Str::random(64)),
                'locale' => $account->locale ?: config('app.locale', 'km'),
            ]);
        });
        static::saving(fn (self $account) => AccountContactUniqueness::assertForAuth($account));
        static::deleted(function (self $account): void {
            if ($account->auth_id === null || ! AccountStorage::ready()) {
                return;
            }
            // Canonical account deletion uses the same stable ownership key.
            DB::table('account_identities')->where('id', $account->auth_id)->where('storage', 'student')->delete();
        });
    }

    public function save(array $options = [])
    {
        // Provisioning the stable identity must roll back if validation or the
        // account insert fails, including creates from the management resource.
        return DB::transaction(fn () => parent::save($options));
    }

    /** Preserve the account classification after its candidate row is moved. */
    public function getRolesAttribute(): array
    {
        return $this->candidate_roles ?? [];
    }

    public function setRolesAttribute(array $roles): void
    {
        $this->setAttribute('candidate_roles', $roles);
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active;
    }
}
