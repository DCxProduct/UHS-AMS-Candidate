<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadExcelStudent extends Model
{
    protected $table = 'upload_excel_students';

    protected $fillable = [
        'name',
        'name_latin',
        'academic_year',
        'seat_number',
        'date_of_birth',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];
}