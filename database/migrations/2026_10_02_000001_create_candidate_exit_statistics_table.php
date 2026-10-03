<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_exit_statistics', function (Blueprint $table): void {
            $table->id();
            $table->string('form_type')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('user_type')->nullable();
            $table->string('seat_number')->nullable();
            $table->string('first_name_kh')->nullable();
            $table->string('last_name_kh')->nullable();
            $table->string('first_name_en')->nullable();
            $table->string('last_name_en')->nullable();
            $table->string('gender')->nullable();
            $table->string('major')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('candidate_status')->default('pending');
            $table->timestamp('candidate_reviewed_at')->nullable();
            $table->timestamps();

            $table->index('form_type');
            $table->index('academic_year');
            $table->index('user_type');
            $table->index('seat_number');
            $table->index('gender');
            $table->index('major');
            $table->index('candidate_status');
            $table->index('candidate_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_exit_statistics');
    }
};
