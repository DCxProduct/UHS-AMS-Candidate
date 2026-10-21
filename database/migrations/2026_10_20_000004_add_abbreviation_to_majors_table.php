<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('majors', 'abbreviation')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->string('abbreviation', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('majors', 'abbreviation')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->dropUnique('majors_abbreviation_unique');
                $table->dropColumn('abbreviation');
            });
        }
    }
};
