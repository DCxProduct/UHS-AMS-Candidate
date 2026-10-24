<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_types', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->change();
            $table->string('name_kh')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('payment_types')->whereNull('name_en')->update(['name_en' => '']);
        DB::table('payment_types')->whereNull('name_kh')->update(['name_kh' => '']);

        Schema::table('payment_types', function (Blueprint $table): void {
            $table->string('name_en')->nullable(false)->change();
            $table->string('name_kh')->nullable(false)->change();
        });
    }
};
