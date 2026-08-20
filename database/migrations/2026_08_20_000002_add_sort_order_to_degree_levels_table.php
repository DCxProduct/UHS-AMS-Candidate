<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('degree_levels', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')
                ->nullable()
                ->after('is_active');
        });

        DB::table('degree_levels')
            ->orderBy('id')
            ->get(['id'])
            ->values()
            ->each(function (object $degreeLevel, int $index): void {
                DB::table('degree_levels')
                    ->where('id', $degreeLevel->id)
                    ->update(['sort_order' => $index + 1]);
            });

        Schema::table('degree_levels', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')
                ->nullable(false)
                ->change();
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('degree_levels', function (Blueprint $table): void {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
