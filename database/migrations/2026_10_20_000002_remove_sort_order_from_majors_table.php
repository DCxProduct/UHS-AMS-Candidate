<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('majors', 'sort_order')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->dropColumn('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('majors') && ! Schema::hasColumn('majors', 'sort_order')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }
    }
};
