<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_types', function (Blueprint $table): void {
            $table->string('abbreviation', 100)->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::table('payment_types', function (Blueprint $table): void {
            $table->dropColumn('abbreviation');
        });
    }
};
