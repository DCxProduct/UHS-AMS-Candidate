<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('integration_source')->nullable();
            $table->string('integration_payment_id')->nullable();
            $table->string('integration_cashier_name')->nullable();
            $table->unique(['integration_source', 'integration_payment_id'], 'payments_integration_unique');
        });

        Schema::create('ams_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_form_entry_id')->unique()->constrained('custom_form_entries')->cascadeOnDelete();
            $table->string('ams_request_id')->nullable()->unique();
            $table->string('sync_status')->default('pending');
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('local_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('external_payment_id')->nullable();
            $table->unsignedBigInteger('last_event_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_payment_requests');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_integration_unique');
            $table->dropColumn(['integration_source', 'integration_payment_id', 'integration_cashier_name']);
        });
    }
};
