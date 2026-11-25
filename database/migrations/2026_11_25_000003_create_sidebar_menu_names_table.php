<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_menu_names', function (Blueprint $table): void {
            $table->id();
            $table->string('target_key')->unique();
            $table->string('label_en')->nullable();
            $table->string('label_km')->nullable();
            $table->timestamps();
        });

        // Keep the most recently saved name when existing roles have different names.
        // Existing role-specific ordering is untouched.
        DB::transaction(function (): void {
            $seen = [];
            foreach (DB::table('sidebar_menus')->orderByDesc('updated_at')->orderByDesc('id')->get() as $menu) {
                if (isset($seen[$menu->target_key])) {
                    continue;
                }
                $seen[$menu->target_key] = true;
                DB::table('sidebar_menu_names')->insert([
                    'target_key' => $menu->target_key,
                    'label_en' => $menu->label_en,
                    'label_km' => $menu->label_km,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('sidebar_menus')->where('target_key', $menu->target_key)->update([
                    'label_en' => $menu->label_en, 'label_km' => $menu->label_km,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_menu_names');
    }
};
