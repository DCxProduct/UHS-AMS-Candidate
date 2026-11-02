<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_dashboard_totals', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('applications')->default(0);
            $table->unsignedBigInteger('paid')->default(0);
            $table->unsignedBigInteger('unpaid')->default(0);
        });

        DB::table('cashier_dashboard_totals')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_dashboard_totals');
    }
};
