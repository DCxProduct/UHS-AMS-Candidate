<?php

namespace App\Models;

use App\Models\Concerns\PreventsStudentAccountDeletion;
use App\Support\CandidateLatinName;
use App\Support\StudentAccountArchive;

/** Authentication/profile access to a canonical New Student Account record. */
class StudentLogin extends User
{
    use PreventsStudentAccountDeletion;

    protected $table = 'new_student_accounts';

    protected $primaryKey = 'auth_id';

    protected function casts(): array
    {
        return [...parent::casts(), 'archived_at' => 'datetime'];
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            StudentAccountArchive::protectState($this);

            return parent::save($options);
        });
    }

    public function getIdAttribute(): ?int
    {
        return isset($this->attributes['auth_id']) ? (int) $this->attributes['auth_id'] : null;
    }

    public function getMorphClass(): string
    {
        // Existing roles, direct permissions, notifications and OTPs retain
        // the original logical user ID throughout the storage transfer.
        return User::class;
    }

    public function linkedSystemUser(): ?SystemUser
    {
        return null;
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (self $user): void {
            if ($user->isDirty('name_latin')) {
                [$first, $last] = CandidateLatinName::split($user->name_latin);
                $user->first_name_latin = $first;
                $user->last_name_latin = $last;
            }
        });
    }
}
