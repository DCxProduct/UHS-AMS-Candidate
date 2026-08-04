<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('closing_dates', function (Blueprint $table): void {
            if (! Schema::hasColumn('closing_dates', 'name_kh')) {
                $table->string('name_kh', 150)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('closing_dates', function (Blueprint $table): void {
            if (Schema::hasColumn('closing_dates', 'name_kh')) {
                $table->dropColumn('name_kh');
            }
        });
    }
};
