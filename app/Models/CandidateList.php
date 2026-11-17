<?php

namespace App\Models;

/** Candidate management and login use the same canonical users record. */
class CandidateList extends User
{
    protected $table = 'users';

    protected $attributes = [
        'registration_type' => 'student',
        'date_of_birth' => '2000-01-01',
        'is_active' => true,
    ];

    public function getMorphClass(): string
    {
        return User::class;
    }
}
