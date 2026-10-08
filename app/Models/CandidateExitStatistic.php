<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function customFormEntry(): BelongsTo
    {
        return $this->belongsTo(CustomFormEntry::class, 'custom_form_entry_id');
    }
}
