<?php

namespace Chanthoeun\FilamentCustomForms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;



class CustomForm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'schema',
        'is_active',
        'allowed_roles',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'allowed_roles' => 'array',
    ];



    public function entries(): HasMany
    {
        return $this->hasMany(CustomFormEntry::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFormField::class)->orderBy('sort');
    }
}
