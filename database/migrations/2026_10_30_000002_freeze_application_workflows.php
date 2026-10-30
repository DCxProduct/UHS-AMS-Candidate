<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_form_entries')) {
            return;
        }

        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->json('workflow_snapshot')->nullable();
            });
        }

        $hasWorkflows = Schema::hasTable('workflow_notifications')
            && Schema::hasTable('workflow_notification_forms');
        $capturedAt = now()->toIso8601String();
        $query = DB::table('custom_form_entries as entries')
            ->whereNull('entries.workflow_snapshot')
            ->select('entries.id');

        if ($hasWorkflows) {
            $query->leftJoin('workflow_notification_forms as assignments', 'assignments.custom_form_id', '=', 'entries.custom_form_id')
                ->leftJoin('workflow_notifications as workflows', 'workflows.id', '=', 'assignments.workflow_notification_id')
                ->addSelect(['workflows.id as workflow_id', 'workflows.name as workflow_name', 'workflows.stages as workflow_stages']);
        }

        // Preserve current settings for existing applications before any future edits.
        // Query-builder writes intentionally avoid submission and notification events.
        $query->chunkById(500, function ($entries) use ($capturedAt): void {
            foreach ($entries as $entry) {
                $stages = json_decode((string) ($entry->workflow_stages ?? '[]'), true, flags: JSON_THROW_ON_ERROR) ?? [];
                $requiresPayment = collect($stages)->contains(fn (mixed $stage): bool => is_array($stage)
                    && ($stage['data']['stage_type'] ?? $stage['stage_type'] ?? null) === 'payment');
                $snapshot = [
                    'schema_version' => 1,
                    'captured_at' => $capturedAt,
                    'workflow_id' => $entry->workflow_id ?? null,
                    'name' => $entry->workflow_name ?? null,
                    'stages' => $stages,
                    'has_workflow' => isset($entry->workflow_id),
                    'requires_payment' => $requiresPayment,
                ];

                DB::table('custom_form_entries')->where('id', $entry->id)->whereNull('workflow_snapshot')->update([
                    'workflow_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
        }, 'entries.id', 'id');
    }

    public function down(): void
    {
        if (Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            Schema::table('custom_form_entries', function (Blueprint $table): void {
                $table->dropColumn('workflow_snapshot');
            });
        }
    }
};
