<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_types', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')
                ->nullable()
                ->after('is_active');
        });

        DB::table('role_types')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(function (object $roleType, int $index): void {
                DB::table('role_types')
                    ->where('id', $roleType->id)
                    ->update(['sort_order' => $index + 1]);
            });

        Schema::table('role_types', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')
                ->nullable(false)
                ->change();
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('role_types', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
