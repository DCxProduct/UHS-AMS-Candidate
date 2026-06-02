<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OldStudentRegistration extends Model
{
    use SoftDeletes;

    protected $table = 'old_student_registrations';

    protected $fillable = [
        'registration_no',
        'user_id',

        'student_id',
        'khmer_name',
        'family_name',
        'first_name',
        'sex',
        'date_of_birth',
        'nationality',
        'religion',
        'place_of_birth',
        'marital_status',

        'current_job',
        'institution',
        'workshop_course',
        'student_type',

        'permanent_no',
        'permanent_street',
        'permanent_sangkat',
        'permanent_khan_district',
        'permanent_city_state_country',

        'current_no',
        'current_street',
        'current_sangkat',
        'current_khan_district',
        'current_city_country',

        'phone_no',
        'email',

        'father_name',
        'father_year_of_birth',
        'father_occupation',

        'mother_name',
        'mother_year_of_birth',
        'mother_occupation',

        'contact_person',
        'contact_no',

        'photo_path',
        'signature_path',
        'generated_pdf_path',

        'status',
        'submitted_at',
        'created_by',
        'updated_by',
        'extra_data',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'submitted_at' => 'datetime',
            'extra_data' => 'array',
        ];
    }
}