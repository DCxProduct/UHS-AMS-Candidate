<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('majors', function (Blueprint $table): void {
            $table->string('name_en', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('majors')
            ->whereNull('name_en')
            ->get(['id', 'name_kh', 'abbreviation', 'key'])
            ->each(function (object $major): void {
                DB::table('majors')
                    ->where('id', $major->id)
                    ->update(['name_en' => $major->name_kh ?: $major->abbreviation ?: $major->key]);
            });

        Schema::table('majors', function (Blueprint $table): void {
            $table->string('name_en', 255)->nullable(false)->change();
        });
    }
};
