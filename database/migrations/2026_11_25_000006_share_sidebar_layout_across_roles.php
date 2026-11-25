<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $seen = [];
            // Resolve old role-specific layouts using the most recently saved setting.
            foreach (DB::table('sidebar_menus')->where('target_key', 'not like', 'item:custom-form-entry-%')
                ->orderByDesc('updated_at')->orderByDesc('id')->get() as $menu) {
                if (isset($seen[$menu->target_key])) {
                    continue;
                }
                $seen[$menu->target_key] = true;
                DB::table('sidebar_menus')->where('target_key', $menu->target_key)->update([
                    'sort_order' => $menu->sort_order,
                    'navigation_group' => $menu->navigation_group,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Saved layout data is retained; there is no schema change to roll back.
    }
};
