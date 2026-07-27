<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('users_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('form_id')->nullable()->constrained('custom_forms')->nullOnDelete();
            $table->string('receipt_number')->nullable();
            $table->string('type_payment')->nullable();
            $table->string('status_payt')->nullable();
            $table->decimal('amount_usd', 12, 2)->nullable();
            $table->decimal('amount_kh', 14, 2)->nullable();
            $table->timestamp('datetime_pay')->nullable();
            $table->boolean('status')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
