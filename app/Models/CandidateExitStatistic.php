<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateExitStatistic extends Model
{
    protected $fillable = [
        'custom_form_entry_id',
        'form_type',
        'academic_year',
        'user_type',
        'seat_number',
        'first_name_kh',
        'last_name_kh',
        'first_name_en',
        'last_name_en',
        'gender',
        'major',
        'date_of_birth',
        'candidate_status',
        'candidate_reviewed_at',
        'hidden_from_statistics',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'candidate_reviewed_at' => 'datetime',
            'hidden_from_statistics' => 'boolean',
        ];
    }
}
