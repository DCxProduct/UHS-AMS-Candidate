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
            $table->string('method', 16)->default('cash')->after('name_kh');
        });

        DB::table('payment_types')
            ->where(function ($query): void {
                $query
                    ->whereRaw('LOWER(key) <> ?', ['cash'])
                    ->whereRaw('LOWER(name_en) NOT LIKE ?', ['%cash%']);
            })
            ->update(['method' => 'bank']);
    }

    public function down(): void
    {
        Schema::table('payment_types', function (Blueprint $table): void {
            $table->dropColumn('method');
        });
    }
};
