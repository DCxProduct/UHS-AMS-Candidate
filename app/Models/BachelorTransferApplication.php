<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BachelorTransferApplication extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'bachelor_transfer_applications';

    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        /*
        |--------------------------------------------------------------------------
        | User / System
        |--------------------------------------------------------------------------
        */
        'user_id',
        'created_by',
        'updated_by',
        'reviewed_by',

        /*
        |--------------------------------------------------------------------------
        | Application Basic Information
        |--------------------------------------------------------------------------
        */
        'application_no',
        'receipt_no',
        'academic_year',
        'application_date',

        /*
        |--------------------------------------------------------------------------
        | Transfer Information
        |--------------------------------------------------------------------------
        */
        'transfer_from_university',
        'transfer_from_faculty',
        'transfer_from_major',
        'transfer_from_year',
        'transfer_from_semester',

        'transfer_to_university',
        'transfer_to_faculty',
        'transfer_to_major',
        'transfer_to_year',
        'transfer_to_semester',

        /*
        |--------------------------------------------------------------------------
        | Student Name Khmer
        |--------------------------------------------------------------------------
        */
        'family_name_kh',
        'given_name_kh',
        'full_name_kh',

        /*
        |--------------------------------------------------------------------------
        | Student Name English
        |--------------------------------------------------------------------------
        */
        'family_name_en',
        'given_name_en',
        'full_name_en',

        /*
        |--------------------------------------------------------------------------
        | Personal Information
        |--------------------------------------------------------------------------
        */
        'gender',
        'date_of_birth',
        'place_of_birth',
        'nationality',
        'marital_status',

        /*
        |--------------------------------------------------------------------------
        | Contact Information
        |--------------------------------------------------------------------------
        */
        'phone',
        'telegram_phone',
        'email',
        'current_address',
        'permanent_address',

        /*
        |--------------------------------------------------------------------------
        | Identity Information
        |--------------------------------------------------------------------------
        */
        'national_id_card',
        'passport_no',
        'student_card_no',

        /*
        |--------------------------------------------------------------------------
        | Family Information
        |--------------------------------------------------------------------------
        */
        'father_name',
        'father_occupation',
        'father_phone',

        'mother_name',
        'mother_occupation',
        'mother_phone',

        'guardian_name',
        'guardian_relationship',
        'guardian_phone',
        'guardian_address',

        /*
        |--------------------------------------------------------------------------
        | High School / BacII Information
        |--------------------------------------------------------------------------
        */
        'high_school_name',
        'high_school_province',
        'bacii_year',
        'bacii_grade',
        'bacii_exam_center',

        /*
        |--------------------------------------------------------------------------
        | Previous University Study Information
        |--------------------------------------------------------------------------
        */
        'previous_student_id',
        'previous_academic_year',
        'previous_result',
        'previous_gpa',

        /*
        |--------------------------------------------------------------------------
        | Dynamic JSON Records
        |--------------------------------------------------------------------------
        */
        'education_records',
        'family_records',
        'attachment_checklist',

        /*
        |--------------------------------------------------------------------------
        | Files
        |--------------------------------------------------------------------------
        */
        'photo',
        'national_id_file',
        'passport_file',
        'bacii_certificate_file',
        'transcript_file',
        'student_card_file',
        'transfer_letter_file',
        'other_document_file',

        /*
        |--------------------------------------------------------------------------
        | Request / Declaration
        |--------------------------------------------------------------------------
        */
        'request_reason',
        'student_declaration',
        'admin_note',

        /*
        |--------------------------------------------------------------------------
        | Status Workflow
        |--------------------------------------------------------------------------
        */
        'status',
        'submitted_at',
        'reviewed_at',

        /*
        |--------------------------------------------------------------------------
        | Signature
        |--------------------------------------------------------------------------
        */
        'student_signed_date',
        'admin_signed_date',
        'student_signature',
        'admin_signature',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'date_of_birth' => 'date',

            'student_signed_date' => 'date',
            'admin_signed_date' => 'date',

            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',

            'previous_gpa' => 'decimal:2',

            'education_records' => 'array',
            'family_records' => 'array',
            'attachment_checklist' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Options
    |--------------------------------------------------------------------------
    */

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_REVIEWING => 'Reviewing',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public static function statusKhmerOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'ព្រាង',
            self::STATUS_SUBMITTED => 'បានដាក់ស្នើ',
            self::STATUS_REVIEWING => 'កំពុងពិនិត្យ',
            self::STATUS_APPROVED => 'បានអនុម័ត',
            self::STATUS_REJECTED => 'បានបដិសេធ',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isReviewing(): bool
    {
        return $this->status === self::STATUS_REVIEWING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name_kh
            ?: $this->full_name_en
            ?: trim(($this->family_name_kh ?? '') . ' ' . ($this->given_name_kh ?? ''));
    }

    public function getTransferTitleAttribute(): string
    {
        return trim(
            ($this->transfer_from_university ?? '') .
            ' ទៅ ' .
            ($this->transfer_to_university ?? '')
        );
    }
}