<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_stage_types', function (Blueprint $table): void {
            $table->string('behavior')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('workflow_stage_types')->whereNull('behavior')->exists()) {
            throw new RuntimeException('Cannot require a stage action while display-only stage types exist. Update or remove those stage types before rolling back.');
        }

        Schema::table('workflow_stage_types', function (Blueprint $table): void {
            $table->string('behavior')->nullable(false)->change();
        });
    }
};
