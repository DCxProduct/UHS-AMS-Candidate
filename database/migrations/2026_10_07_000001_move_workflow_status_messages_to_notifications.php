<?php

use App\Models\WorkflowNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_notifications')) {
            return;
        }

        WorkflowNotification::query()->each(function (WorkflowNotification $workflow): void {
            $workflow->stages = WorkflowNotification::normalizeStages($workflow->stages);
            $workflow->saveQuietly();
        });
    }

    public function down(): void
    {
        // The migration only normalizes legacy data and does not remove fields.
    }
};
