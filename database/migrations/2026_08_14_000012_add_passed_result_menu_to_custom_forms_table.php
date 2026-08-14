<?php

use App\Support\PassedResultMenuOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->string('passed_result_menu')
                ->default(PassedResultMenuOptions::EXAM_RESULTS)
                ->after('sub_item_type');
        });
    }

    public function down(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->dropColumn('passed_result_menu');
        });
    }
};
