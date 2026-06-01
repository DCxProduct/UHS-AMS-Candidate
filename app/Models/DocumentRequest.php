<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $fillable = [
        'photo',
        'pdf_file',

        'request_type',
        'request_documents',
        'other_request_type',

        'student_id',
        'name_kh',
        'family_name_kh',
        'first_name_kh',
        'family_name_en',
        'first_name_en',
        'gender',
        'student_type',

        'birth_date',
        'birth_place',
        'current_address',
        'village',
        'province',
        'phone',
        'email',

        'current_status',
        'current_studying',
        'current_year',
        'academic_year',

        'promotion',
        'major',
        'year_enrollment',
        'graduation_year',
        'faculty',

        'languages',
        'khmer_copies',
        'english_copies',
        'french_copies',
        'sealed_envelope_copies',
        'stamp_copies',

        'diploma_original',
        'diploma_copy',
        'diploma_copy_number',

        'received_day',
        'received_month',
        'received_year',

        'request_day',
        'request_month',
        'request_year',

        'applicant_signature_name',
        'office_permission_no',
        'verified_signature',
        'office_process',

        'purpose',
        'is_confirmed',
        'office_note',
        'status',
    ];

    protected $casts = [
        'languages' => 'array',
        'request_documents' => 'array',
        'attachments' => 'array',

        'current_studying' => 'boolean',
        'diploma_original' => 'boolean',
        'diploma_copy' => 'boolean',
        'verified_signature' => 'boolean',
        'is_confirmed' => 'boolean',

        'birth_date' => 'date',
    ];
}
