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
        'custom_form_id',
        'schema',
        'is_active',
        'allowed_roles',
        'menu_placement',
        'parent_sidebar',
        'sub_item_type',
    ];

    protected $casts = [
        'schema' => 'array',
        'is_active' => 'boolean',
        'allowed_roles' => 'array',
    ];

    protected static function booted(): void
    {
        static::created(function (CustomForm $customForm): void {
            if ($customForm->menu_placement === 'sidebar' && blank($customForm->custom_form_id)) {
                $customForm->forceFill([
                    'custom_form_id' => $customForm->id,
                    'parent_sidebar' => null,
                    'sub_item_type' => null,
                ])->saveQuietly();
            }
        });

        static::saving(function (CustomForm $customForm): void {
            if ($customForm->menu_placement === 'sidebar') {
                if ($customForm->exists) {
                    $customForm->custom_form_id = $customForm->id;
                }

                $customForm->parent_sidebar = null;
                $customForm->sub_item_type = null;
            }
        });
    }

    public function parentForm()
    {
        return $this->belongsTo(self::class, 'custom_form_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CustomFormEntry::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomFormField::class, 'custom_form_id')->orderBy('sort');
    }
}
