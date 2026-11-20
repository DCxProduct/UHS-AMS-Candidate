<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_buttons', function (Blueprint $table): void {
            $table->string('action_key')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('workflow_buttons')->whereNull('action_key')->exists()) {
            throw new RuntimeException('Cannot require workflow button actions while buttons without actions exist. Assign their actions or remove them before rolling back.');
        }

        Schema::table('workflow_buttons', function (Blueprint $table): void {
            $table->string('action_key')->nullable(false)->change();
        });
    }
};
