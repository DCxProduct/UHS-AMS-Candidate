<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationalEntranceExamEducationHistory extends Model
{
    protected $guarded = [];

    public function application(): BelongsTo
    {
        return $this->belongsTo(
            NationalEntranceExamApplication::class,
            'national_entrance_exam_application_id'
        );
    }
}