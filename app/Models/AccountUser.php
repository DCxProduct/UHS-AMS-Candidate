<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

/** Read both account stores while returning their concrete writable auth model. */
class AccountUser extends User
{
    protected $table = 'account_users';

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (array) $attributes;
        $logicalId = $attributes['id'] ?? null;
        $storage = $attributes['account_store'] ?? null;
        $studentRecordId = $attributes['student_record_id'] ?? null;

        // Eager-load projections such as ['id', 'name'] may omit view metadata.
        // Resolve just those identities without altering projections/aggregates.
        if ($storage === null && $logicalId !== null && Schema::hasTable('account_identities')) {
            $storage = AccountIdentity::query()->whereKey($logicalId)->value('storage');
        }
        if ($storage === 'student' && $studentRecordId === null && $logicalId !== null) {
            $studentRecordId = DB::table('new_student_accounts')->where('auth_id', $logicalId)->value('id');
        }

        unset($attributes['account_store'], $attributes['student_record_id']);
        if ($storage === 'student') {
            $attributes['auth_id'] = $logicalId;
            $attributes['id'] = $studentRecordId;
            $model = (new StudentLogin)->newInstance([], true);
        } else {
            unset($attributes['student_id'], $attributes['auth_id'], $attributes['archived_at']);
            $model = (new User)->newInstance([], true);
        }

        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?? $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    public function getMorphClass(): string
    {
        return User::class;
    }

    public function save(array $options = [])
    {
        throw new LogicException('AccountUser is a read model; save its hydrated User or StudentLogin instead.');
    }
}
