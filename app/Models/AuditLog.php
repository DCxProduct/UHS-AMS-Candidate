<?php

namespace App\Models;

use App\Models\Relations\AccountActorMorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_role',
        'action',
        'module',
        'description',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'records_cleared_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    protected function newMorphTo(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        return new AccountActorMorphTo($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }
}
