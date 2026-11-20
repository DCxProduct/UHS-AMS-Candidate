<?php

namespace Database\Seeders;

use App\Models\WorkflowButton;
use App\Support\WorkflowStageTypeSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class WorkflowButtonSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (WorkflowStageTypeSettings::buttonActions() as $key => $action) {
                WorkflowButton::withTrashed()->firstOrCreate(
                    ['action_key' => $key],
                    [
                        'label_en' => Lang::get('workflow_stage_types.button_actions.'.$key, [], 'en'),
                        'label_km' => Lang::get('workflow_stage_types.button_actions.'.$key, [], 'km'),
                        'icon' => $action['icon'],
                        'color' => match ($key) {
                            'accept', 'paid', 'passed' => 'success',
                            'send_back' => 'warning',
                            'reject' => 'danger',
                            default => 'info',
                        },
                        'is_active' => true,
                    ],
                );
            }
        });
    }
}
