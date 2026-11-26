<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_excel_account_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 100);
            $table->string('source_user_id', 100);
            $table->string('student_id', 100)->unique();
            $table->foreignId('new_student_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('profile_entry_id')->constrained('custom_form_entries')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['source_system', 'source_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_excel_account_receipts');
    }
};
