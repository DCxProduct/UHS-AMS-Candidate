<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterScienceNationalExitExamApplication extends Model
{
    use SoftDeletes;

    protected $table = 'national_exit_exam_applications';

    protected $fillable = [
        'user_id',
        'created_by',
        'updated_by',
        'status',
        'submitted_at',

        'application_no',
        'receipt_no',
        'registration_no',
        'exam_type',
        'academic_year',
        'exam_year',
        'exam_session',
        'exam_center',
        'faculty_applied',
        'major_applied',
        'degree_level',
        'training_course',

        'name',
        'last_name',
        'first_name',
        'latin_name',
        'gender',
        'date_of_birth',
        'age',
        'nationality',
        'citizenship',
        'religion',
        'national_id',
        'passport_no',

        'birth_village',
        'birth_commune',
        'birth_district',
        'birth_province',
        'birth_place',

        'phone',
        'telegram_phone',
        'email',

        'current_house_no',
        'current_street_no',
        'current_group',
        'current_village',
        'current_commune',
        'current_district',
        'current_province',
        'current_address',

        'permanent_house_no',
        'permanent_street_no',
        'permanent_group',
        'permanent_village',
        'permanent_commune',
        'permanent_district',
        'permanent_province',
        'permanent_address',

        'education_level',
        'school_name',
        'university_name',
        'institute_name',
        'bac_year',
        'bac_exam_center',
        'bac_room',
        'bac_seat_no',
        'bac_grade',
        'bac_certificate_no',
        'graduation_year',
        'degree_certificate_no',
        'transcript_no',

        'current_job',
        'workplace',
        'position',

        'marital_status',
        'spouse_name',
        'spouse_date_of_birth',
        'spouse_age',
        'spouse_nationality',
        'spouse_occupation',
        'spouse_phone',
        'spouse_address',

        'father_name',
        'father_date_of_birth',
        'father_age',
        'father_nationality',
        'father_occupation',
        'father_phone',
        'father_status',
        'father_address',

        'mother_name',
        'mother_date_of_birth',
        'mother_age',
        'mother_nationality',
        'mother_occupation',
        'mother_phone',
        'mother_status',
        'mother_address',

        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_occupation',
        'guardian_address',

        'receipt_date',
        'receipt_receiver_name',
        'receipt_receiver_position',

        'has_application_form',
        'has_biography',
        'has_certificate',
        'has_transcript',
        'has_permission_letter',
        'has_osce_result',
        'has_photo_4x6',
        'has_other_document',

        'photo_path',
        'signature_path',
        'generated_pdf_path',
        'receipt_file',
        'application_form_file',
        'biography_file',
        'certificate_file',
        'transcript_file',
        'permission_letter_file',
        'osce_result_file',
        'other_document_file',

        'children',
        'siblings',
        'education_histories',
        'work_histories',
        'document_checklist',
        'extra_data',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',

            'date_of_birth' => 'date',
            'spouse_date_of_birth' => 'date',
            'father_date_of_birth' => 'date',
            'mother_date_of_birth' => 'date',
            'receipt_date' => 'date',

            'has_application_form' => 'boolean',
            'has_biography' => 'boolean',
            'has_certificate' => 'boolean',
            'has_transcript' => 'boolean',
            'has_permission_letter' => 'boolean',
            'has_osce_result' => 'boolean',
            'has_photo_4x6' => 'boolean',
            'has_other_document' => 'boolean',

            'children' => 'array',
            'siblings' => 'array',
            'education_histories' => 'array',
            'work_histories' => 'array',
            'document_checklist' => 'array',
            'extra_data' => 'array',
        ];
    }
}