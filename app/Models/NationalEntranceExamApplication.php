<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NationalEntranceExamApplication extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'spouse_date_of_birth' => 'date',
            'father_date_of_birth' => 'date',
            'mother_date_of_birth' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'extra_data' => 'array',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(
            NationalEntranceExamApplicationDocument::class,
            'national_entrance_exam_application_id'
        );
    }

    public function educationHistories(): HasMany
    {
        return $this->hasMany(
            NationalEntranceExamEducationHistory::class,
            'national_entrance_exam_application_id'
        );
    }
}