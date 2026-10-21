<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['majors_name_en_unique', 'majors_abbreviation_unique'] as $index) {
            if (Schema::hasIndex('majors', $index)) {
                Schema::table('majors', function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index);
                });
            }
        }
    }

    public function down(): void
    {
        $hasDuplicateNames = DB::table('majors')
            ->select('name_en')
            ->groupBy('name_en')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        $hasDuplicateAbbreviations = DB::table('majors')
            ->whereNotNull('abbreviation')
            ->select('abbreviation')
            ->groupBy('abbreviation')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateNames || $hasDuplicateAbbreviations) {
            throw new RuntimeException(
                'Cannot restore unique major name and abbreviation indexes while duplicate values exist.'
            );
        }

        if (! Schema::hasIndex('majors', 'majors_name_en_unique')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->unique('name_en');
            });
        }

        if (! Schema::hasIndex('majors', 'majors_abbreviation_unique')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->unique('abbreviation');
            });
        }
    }
};
