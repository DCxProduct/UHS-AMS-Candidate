<?php

use App\Enums\WorkflowStageType as StageBehavior;
use App\Models\WorkflowStageType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $settingsColumns = ['review_actions', 'stage_settings', 'enabled_actions'];

        if (! Schema::hasTable('workflow_stage_types')) {
            Schema::create('workflow_stage_types', function (Blueprint $table) use ($settingsColumns): void {
                $table->id();
                $table->string('label_en')->unique();
                $table->string('label_km')->unique();
                $table->string('behavior')->index();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                foreach ($settingsColumns as $column) {
                    $table->json($column)->nullable();
                }
                $table->timestamps();
            });
        } else {
            foreach ($settingsColumns as $column) {
                if (! Schema::hasColumn('workflow_stage_types', $column)) {
                    Schema::table('workflow_stage_types', function (Blueprint $table) use ($column): void {
                        $table->json($column)->nullable();
                    });
                }
            }
        }

        foreach (array_keys(StageBehavior::options()) as $behavior) {
            if (DB::table('workflow_stage_types')->where('behavior', $behavior)->where('is_system', true)->exists()) {
                continue;
            }

            $labels = WorkflowStageType::defaultLabels($behavior);
            DB::table('workflow_stage_types')->insert([
                'label_en' => $labels['en'],
                'label_km' => $labels['km'],
                'behavior' => $behavior,
                'is_system' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_types');
    }
};
