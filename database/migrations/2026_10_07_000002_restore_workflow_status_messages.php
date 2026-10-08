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
            $stages = $workflow->stages ?? [];
            $changed = false;

            foreach ($stages as $key => $stage) {
                if (! is_array($stage)) {
                    continue;
                }

                $wrapped = is_array($stage['data'] ?? null);
                $data = $wrapped ? $stage['data'] : $stage;

                if (blank($data['status_message'] ?? null) && filled($data['notification_message'] ?? null)) {
                    $data['status_message'] = $data['notification_message'];
                    $changed = true;
                }

                $stages[$key] = $wrapped ? [...$stage, 'data' => $data] : $data;
            }

            if ($changed) {
                $workflow->stages = $stages;
                $workflow->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        // Status messages are retained when rolling back this compatibility migration.
    }
};
