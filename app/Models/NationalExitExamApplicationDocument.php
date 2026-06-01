<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NationalExitExamApplicationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'document_key',
        'document_name',
        'is_required',
        'is_submitted',
        'file_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_submitted' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(NationalExitExamApplication::class, 'application_id');
    }
}