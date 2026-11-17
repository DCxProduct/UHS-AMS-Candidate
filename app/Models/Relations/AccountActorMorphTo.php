<?php

namespace App\Models\Relations;

use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Resolve historical account actors without changing their stored morph type. */
class AccountActorMorphTo extends MorphTo
{
    public function __construct(Builder $query, Model $parent, $foreignKey, $ownerKey, $type, $relation)
    {
        if ($query->getModel() instanceof User) {
            $query = (new AccountUser)->setConnection($query->getModel()->getConnectionName())->newQuery();
            $ownerKey = 'id';
        }

        parent::__construct($query, $parent, $foreignKey, $ownerKey, $type, $relation);
    }

    public function createModelByType($type)
    {
        $model = parent::createModelByType($type);

        return $model instanceof User
            ? (new AccountUser)->setConnection($model->getConnectionName())
            : $model;
    }
}
