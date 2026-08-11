<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->boolean('requires_payment')->default(true)->after('allowed_roles');
        });
    }

    public function down(): void
    {
        Schema::table('custom_forms', function (Blueprint $table): void {
            $table->dropColumn('requires_payment');
        });
    }
};
