<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'action',
        'module',
        'description',
        'ip_address',
    ];

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
