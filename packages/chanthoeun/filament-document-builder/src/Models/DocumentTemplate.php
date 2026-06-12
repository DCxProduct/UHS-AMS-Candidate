<?php

namespace Chanthoeun\FilamentDocumentBuilder\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'model_class',
        'content',
        'page_settings',
        'extra_data_sources',
    ];

    protected $casts = [
        'content' => 'array',
        'page_settings' => 'array',
        'extra_data_sources' => 'array',
    ];
}
