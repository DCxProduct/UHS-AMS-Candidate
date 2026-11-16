<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewStudentAccount extends Model
{
    protected $fillable = [
        'student_id',
        'email',
        'phone',
        'first_name_latin',
        'last_name_latin',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
