<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class NationalExitExamApplication extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'application_no',
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
        'candidate_from',
        'completed_study_level',
        'completed_study_at',
        'academic_year',
        'exam_class',
        'exam_session_date',
        'phone',
        'current_address',
        'contact_address',
        'contact_phone',
        'education_level',
        'school_name',
        'school_location',
        'foreign_language',
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
        'payment_amount',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'spouse_date_of_birth' => 'date',
            'payment_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (NationalExitExamApplication $record): void {
            if (blank($record->application_no)) {
                $record->forceFill([
                    'application_no' => 'NEE-' . now()->format('Y') . '-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }

            $record->ensureDefaultDocuments();
        });
    }

    public function documents(): HasMany
    {
        return $this->hasMany(NationalExitExamApplicationDocument::class, 'application_id');
    }

    public function getGenderKhAttribute(): string
    {
        return match ($this->gender) {
            'male' => 'ប្រុស',
            'female' => 'ស្រី',
            default => '',
        };
    }

    public function getMaritalStatusKhAttribute(): string
    {
        return match ($this->marital_status) {
            'single' => 'នៅលីវ',
            'married' => 'មានគ្រួសារ',
            default => '',
        };
    }

    public function getStatusKhAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'ព្រាង',
            'submitted' => 'បានដាក់ពាក្យ',
            'approved' => 'បានអនុម័ត',
            'rejected' => 'បដិសេធ',
            default => '',
        };
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (blank($this->photo)) {
            return null;
        }

        return Storage::disk('public')->url($this->photo);
    }

    public static function defaultDocumentRows(): array
    {
        return [
            [
                'document_key' => 'receipt',
                'document_name' => 'បង្កាន់ដៃទទួលពាក្យ',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'application_form',
                'document_name' => 'ពាក្យសុំចុះឈ្មោះប្រឡង',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'bio_form',
                'document_name' => 'ប្រវត្តិរូបសង្ខេបបានបិទរូបថតថ្មី ៤ x ៦',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'high_school_certificate',
                'document_name' => 'សញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ ឬ ព្រឹត្តិបត្រពិន្ទុ',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'associate_or_medical_certificate',
                'document_name' => 'សញ្ញាបត្រ ឬ វិញ្ញាបនបត្របញ្ជាក់ការសិក្សាកម្រិតបរិញ្ញាបត្ររង ឬ គ្រូពេទ្យមធ្យម',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'completion_certificate',
                'document_name' => 'វិញ្ញាបនបត្របញ្ចប់ការសិក្សាដោយជោគជ័យ',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'transcript',
                'document_name' => 'ព្រឹត្តិបត្រពិន្ទុការសិក្សាគ្រប់មុខវិជ្ជា និងគ្រប់ឆ្នាំសិក្សា',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'national_exam_result',
                'document_name' => 'លទ្ធផលប្រឡងថ្នាក់ជាតិចូលរៀនក្នុងវិស័យសុខាភិបាល',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'study_permission_letter',
                'document_name' => 'លិខិតអនុញ្ញាតឱ្យចូលរៀន',
                'is_required' => true,
                'is_submitted' => false,
            ],
            [
                'document_key' => 'final_result_osce',
                'document_name' => 'បញ្ជីលទ្ធផលប្រឡងបញ្ចប់ការសិក្សា ទ្រឹស្តី និង OSCE',
                'is_required' => true,
                'is_submitted' => false,
            ],
        ];
    }

    public function ensureDefaultDocuments(): void
    {
        foreach (self::defaultDocumentRows() as $document) {
            $this->documents()->firstOrCreate(
                [
                    'document_key' => $document['document_key'],
                ],
                [
                    'document_name' => $document['document_name'],
                    'is_required' => $document['is_required'],
                    'is_submitted' => $document['is_submitted'],
                ]
            );
        }
    }
}