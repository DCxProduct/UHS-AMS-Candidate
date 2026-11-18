<?php

use App\Support\AccountStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable();
            $table->index(['archived_at', 'id'], 'new_student_accounts_archive_list_index');
        });

        DB::statement('DROP VIEW IF EXISTS account_users');
        AccountStorage::createView();
    }

    public function down(): void
    {
        // Keep archived accounts disabled when reverting this UI feature.
        DB::statement('DROP VIEW IF EXISTS account_users');
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->dropIndex('new_student_accounts_archive_list_index');
            $table->dropColumn('archived_at');
        });
        AccountStorage::createView();
    }
};
