<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        Schema::table($rolesTable, function (Blueprint $table): void {
            $table->boolean('restrict_custom_form_access')->default(false);
            $table->json('allowed_custom_form_ids')->nullable();
        });
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');

        Schema::table($rolesTable, function (Blueprint $table): void {
            $table->dropColumn(['restrict_custom_form_access', 'allowed_custom_form_ids']);
        });
    }
};
