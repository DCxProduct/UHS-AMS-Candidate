<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NationalExitExamApplicationDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'national_exit_exam_application_documents';

    protected $fillable = [
        'application_id',
        'document_key',
        'document_name',
        'document_group',
        'sort_order',
        'is_required',
        'is_submitted',
        'copies_required',
        'file_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_submitted' => 'boolean',
            'sort_order' => 'integer',
            'copies_required' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function application(): BelongsTo
    {
        return $this->belongsTo(NationalExitExamApplication::class, 'application_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Options
    |--------------------------------------------------------------------------
    */

    public static function documentGroupOptions(): array
    {
        return [
            'required' => 'ឯកសារចាំបាច់',
            'optional' => 'ឯកសារបន្ថែម',
            'payment' => 'ឯកសារបង់ប្រាក់',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getDocumentGroupLabelAttribute(): string
    {
        return self::documentGroupOptions()[$this->document_group] ?? '-';
    }

    public function getSubmittedLabelAttribute(): string
    {
        return $this->is_submitted ? 'បានដាក់' : 'មិនទាន់ដាក់';
    }

    public function getRequiredLabelAttribute(): string
    {
        return $this->is_required ? 'ចាំបាច់' : 'មិនចាំបាច់';
    }
}