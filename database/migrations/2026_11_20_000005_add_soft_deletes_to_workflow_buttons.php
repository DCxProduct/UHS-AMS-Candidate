<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workflow_buttons', 'deleted_at')) {
            Schema::table('workflow_buttons', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workflow_buttons', 'deleted_at')) {
            return;
        }

        if (DB::table('workflow_buttons')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Cannot remove workflow button deletion markers while deleted buttons exist. Restore them or permanently remove unused buttons before rolling back.');
        }

        Schema::table('workflow_buttons', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
