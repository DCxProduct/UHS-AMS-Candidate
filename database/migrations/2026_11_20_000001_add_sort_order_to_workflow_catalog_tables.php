<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workflow_notifications', 'workflow_stage_types', 'workflow_buttons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->nullable()->index();
            });

            // Start catalogs in their default order; administrators can save a different order later.
            $query = DB::table($tableName);

            if ($tableName === 'workflow_notifications') {
                $query->orderByDesc('updated_at');
            } elseif ($tableName === 'workflow_stage_types') {
                $query->orderByRaw(
                    'CASE WHEN is_system THEN CASE behavior'
                        .' WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3'
                        .' WHEN ? THEN 4 WHEN ? THEN 5 WHEN ? THEN 6 ELSE 7 END ELSE 7 END',
                    ['form_submission', 'review', 'payment', 'candidate_id_generation', 'verification', 'awaiting_results', 'completed'],
                );
            } else {
                $query->orderByRaw(
                    'CASE action_key WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3'
                        .' WHEN ? THEN 4 WHEN ? THEN 5 WHEN ? THEN 6 WHEN ? THEN 7 WHEN ? THEN 8 ELSE 9 END',
                    ['submitted', 'accept', 'send_back', 'reject', 'paid', 'generate_candidate_id', 'verify', 'passed', 'notify'],
                );
            }

            foreach ($query->orderBy('id')->pluck('id') as $index => $id) {
                DB::table($tableName)->where('id', $id)->update(['sort_order' => $index + 1]);
            }
        }
    }

    public function down(): void
    {
        foreach (['workflow_notifications', 'workflow_stage_types', 'workflow_buttons'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropIndex(['sort_order']);
                $table->dropColumn('sort_order');
            });
        }
    }
};
