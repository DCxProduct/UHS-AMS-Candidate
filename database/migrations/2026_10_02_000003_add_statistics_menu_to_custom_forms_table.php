<?php

use App\Support\StatisticsMenuOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->string('statistics_menu')
                ->default(StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS)
                ->after('passed_result_menu');
        });
    }

    public function down(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->dropColumn('statistics_menu');
        });
    }
};
