<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('source_candidate_id')->nullable()->unique();
            $table->json('candidate_roles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('new_student_accounts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['source_candidate_id']);
            $table->dropColumn(['source_candidate_id', 'candidate_roles']);
        });
    }
};
