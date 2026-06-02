<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NationalExitExamApplication extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'national_exit_exam_applications';

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

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';

    protected $fillable = [
        'user_id',
        'created_by',
        'updated_by',
        'reviewed_by',

        'application_no',
        'receipt_no',
        'academic_year',
        'exam_year',
        'exam_session',
        'application_date',

        'faculty_name',
        'major_name',
        'degree_level',
        'exam_class',
        'candidate_from',
        'completed_study_level',
        'completed_study_at',
        'school_name',
        'school_location',
        'foreign_language',

        'full_name_kh',
        'full_name_latin',
        'gender',
        'nationality',
        'date_of_birth',

        'birth_place',
        'birth_village_group',
        'birth_commune',
        'birth_district',
        'birth_province',

        'current_address',
        'current_village_group',
        'current_commune',
        'current_district',
        'current_province',

        'phone',
        'email',
        'contact_address',
        'contact_phone',

        'marital_status',

        'spouse_name',
        'spouse_date_of_birth',
        'spouse_nationality',
        'spouse_occupation',

        'father_name',
        'father_status',
        'father_age',
        'father_birth_place',
        'father_nationality',
        'father_occupation',

        'mother_name',
        'mother_status',
        'mother_age',
        'mother_birth_place',
        'mother_nationality',
        'mother_occupation',

        'photo',
        'national_id_file',
        'birth_certificate_file',
        'diploma_file',
        'transcript_file',
        'other_document_file',

        'payment_amount',
        'payment_status',
        'payment_date',
        'payment_receipt_file',

        'request_reason',
        'notes',
        'rejected_reason',

        'status',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'application_date' => 'date',
            'date_of_birth' => 'date',
            'spouse_date_of_birth' => 'date',
            'payment_date' => 'date',

            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',

            'payment_amount' => 'decimal:2',
            'father_age' => 'integer',
            'mother_age' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NationalExitExamApplication $record): void {
            if (auth()->check()) {
                $record->user_id ??= auth()->id();
                $record->created_by ??= auth()->id();
                $record->updated_by ??= auth()->id();
            }

            $record->status ??= self::STATUS_DRAFT;
            $record->payment_status ??= self::PAYMENT_UNPAID;
        });

        static::updating(function (NationalExitExamApplication $record): void {
            if (auth()->check()) {
                $record->updated_by = auth()->id();
            }
        });
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

    public function documents(): HasMany
    {
        return $this->hasMany(NationalExitExamApplicationDocument::class, 'application_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Options
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

    public static function paymentStatusOptions(): array
    {
        return [
            self::PAYMENT_UNPAID => 'មិនទាន់បង់',
            self::PAYMENT_PAID => 'បានបង់',
        ];
    }

    public static function genderOptions(): array
    {
        return [
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
        ];
    }

    public static function maritalStatusOptions(): array
    {
        return [
            'single' => 'នៅលីវ',
            'married' => 'រៀបការ',
        ];
    }

    public static function parentStatusOptions(): array
    {
        return [
            'alive' => 'រស់',
            'deceased' => 'ស្លាប់',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Default Document Checklist
    |--------------------------------------------------------------------------
    */

    public static function defaultDocuments(): array
    {
        return [
            [
                'document_key' => 'birth_certificate',
                'document_name' => 'សំបុត្រកំណើត ឬ អត្តសញ្ញាណប័ណ្ណ',
                'document_group' => 'required',
                'copies_required' => 1,
                'sort_order' => 1,
            ],
            [
                'document_key' => 'diploma',
                'document_name' => 'សញ្ញាបត្របញ្ជាក់ការបញ្ចប់ការសិក្សា',
                'document_group' => 'required',
                'copies_required' => 1,
                'sort_order' => 2,
            ],
            [
                'document_key' => 'transcript',
                'document_name' => 'ព្រឹត្តិបត្រពិន្ទុ',
                'document_group' => 'required',
                'copies_required' => 1,
                'sort_order' => 3,
            ],
            [
                'document_key' => 'photo_4x6',
                'document_name' => 'រូបថត ៤ x ៦',
                'document_group' => 'required',
                'copies_required' => 2,
                'sort_order' => 4,
            ],
            [
                'document_key' => 'payment_receipt',
                'document_name' => 'បង្កាន់ដៃបង់ប្រាក់',
                'document_group' => 'payment',
                'copies_required' => 1,
                'sort_order' => 5,
            ],
            [
                'document_key' => 'other',
                'document_name' => 'ឯកសារផ្សេងៗ',
                'document_group' => 'optional',
                'copies_required' => 1,
                'sort_order' => 99,
                'is_required' => false,
            ],
        ];
    }

    public function createDefaultDocuments(): void
    {
        foreach (self::defaultDocuments() as $document) {
            $this->documents()->firstOrCreate(
                [
                    'document_key' => $document['document_key'],
                ],
                [
                    'document_name' => $document['document_name'],
                    'document_group' => $document['document_group'] ?? 'required',
                    'copies_required' => $document['copies_required'] ?? 1,
                    'sort_order' => $document['sort_order'] ?? 0,
                    'is_required' => $document['is_required'] ?? true,
                    'is_submitted' => false,
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers / Accessors
    |--------------------------------------------------------------------------
    */

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name_kh
            ?: $this->full_name_latin
            ?: '-';
    }

    public function getGenderLabelAttribute(): string
    {
        return match ($this->gender) {
            'male', 'ប្រុស' => 'ប្រុស',
            'female', 'ស្រី' => 'ស្រី',
            default => '-',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusKhmerOptions()[$this->status] ?? '-';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::paymentStatusOptions()[$this->payment_status] ?? '-';
    }

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

    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }
}