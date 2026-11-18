<?php

namespace App\Services;

use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\WorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WorkflowNotificationDuplicator
{
    public function duplicate(WorkflowNotification $source): WorkflowNotification
    {
        $this->authorize($source);

        return DB::transaction(function () use ($source): WorkflowNotification {
            // Serialize repeated clicks on the same template and read its latest settings.
            $current = WorkflowNotification::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($current);

            return $current->duplicateWithName($this->copyName((string) $current->name));
        });
    }

    private function authorize(WorkflowNotification $source): void
    {
        abort_unless(
            WorkflowNotificationResource::canAccess()
                && WorkflowNotificationResource::canViewAny()
                && WorkflowNotificationResource::can('duplicate', $source),
            403,
        );
    }

    private function copyName(string $original): string
    {
        $existing = WorkflowNotification::query()->pluck('name')
            ->mapWithKeys(fn (string $name): array => [Str::lower($name) => true])->all();
        $label = __('workflow_notifications.duplicate.copy_suffix');

        for ($number = 1; ; $number++) {
            $suffix = ' ('.$label.($number === 1 ? '' : ' '.$number).')';
            $name = mb_substr(trim($original), 0, max(0, 255 - mb_strlen($suffix))).$suffix;
            if (! isset($existing[Str::lower($name)])) {
                return $name;
            }
        }
    }
}
