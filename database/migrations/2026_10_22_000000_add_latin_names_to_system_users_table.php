<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_users', function (Blueprint $table): void {
            $table->string('first_name_latin', 75)->nullable()->after('name');
            $table->string('last_name_latin', 75)->nullable()->after('first_name_latin');
        });
    }

    public function down(): void
    {
        Schema::table('system_users', function (Blueprint $table): void {
            $table->dropColumn(['first_name_latin', 'last_name_latin']);
        });
    }
};
