<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A stable numeric key shared by the two account stores; no profile data. */
class AccountIdentity extends Model
{
    protected $fillable = ['id', 'storage'];
}
