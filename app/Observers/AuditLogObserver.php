<?php

namespace App\Observers;

use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditLogObserver
{
    public function created(Model $model): void
    {
        AuditLogger::logModelEvent('created', $model, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = collect($model->getChanges())
            ->except(['updated_at'])
            ->all();

        if ($changes === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($changes) as $key) {
            $oldValues[$key] = $model->getOriginal($key);
        }

        AuditLogger::logModelEvent('updated', $model, $oldValues, $changes);
    }

    public function deleted(Model $model): void
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        AuditLogger::logModelEvent('deleted', $model, $model->getAttributes(), []);
    }

    public function forceDeleted(Model $model): void
    {
        AuditLogger::logModelEvent('deleted', $model, $model->getAttributes(), []);
    }
}
