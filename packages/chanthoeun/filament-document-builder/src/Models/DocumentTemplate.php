<?php

namespace Chanthoeun\FilamentDocumentBuilder\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'model_class',
        'custom_form_id',
        'content',
        'page_settings',
        'extra_data_sources',
    ];

    protected $casts = [
        'page_settings' => 'array',
        'extra_data_sources' => 'array',
    ];

    public function customForm()
    {
        // Make sure this points to your CustomForm model
        return $this->belongsTo(\Chanthoeun\FilamentCustomForms\Models\CustomForm::class, 'custom_form_id');
    }
}
