<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            if (! Schema::hasColumn('roles', 'role_type_key')) {
                $table->string('role_type_key')->nullable()->after('name_kh');
            }
        });

        DB::table('roles')
            ->whereNull('role_type_key')
            ->update([
                'role_type_key' => DB::raw("
                    CASE
                        WHEN LOWER(name) = 'candidate' THEN 'user'
                        ELSE 'staff'
                    END
                "),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        Schema::table('roles', function (Blueprint $table): void {
            if (Schema::hasColumn('roles', 'role_type_key')) {
                $table->dropColumn('role_type_key');
            }
        });
    }
};
