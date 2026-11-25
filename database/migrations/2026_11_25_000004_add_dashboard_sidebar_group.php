<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $key = 'group:dashboard';
            DB::table('sidebar_menu_names')->insertOrIgnore([
                'target_key' => $key,
                'label_en' => 'Dashboard',
                'label_km' => 'ផ្ទាំងព័ត៌មាន',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $name = DB::table('sidebar_menu_names')->where('target_key', $key)->first();
            // Add the heading for configured Dashboards without changing any saved menu order or name.
            $roleIds = DB::table('sidebar_menus')->where('target_key', 'item:App\\Filament\\Pages\\Dashboard')->pluck('role_id');
            foreach ($roleIds as $roleId) {
                DB::table('sidebar_menus')->insertOrIgnore([
                    'role_id' => $roleId,
                    'target_key' => $key,
                    'label_en' => $name->label_en,
                    'label_km' => $name->label_km,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('sidebar_menus')->where('target_key', 'group:dashboard')->delete();
        DB::table('sidebar_menu_names')->where('target_key', 'group:dashboard')->delete();
    }
};
