<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_types', function (Blueprint $table): void {
            $table->string('group_name')->nullable()->after('label_kh');
        });
    }

    public function down(): void
    {
        Schema::table('user_types', function (Blueprint $table): void {
            $table->dropColumn('group_name');
        });
    }
};
