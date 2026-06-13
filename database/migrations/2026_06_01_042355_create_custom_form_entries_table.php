<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('custom_form_entries')) {
            Schema::create('custom_form_entries', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('custom_form_id')
                    ->constrained('custom_forms')
                    ->cascadeOnDelete();

                $table->json('data')->nullable();

                if (Schema::hasTable('users')) {
                    $table->foreignId('created_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();

                    $table->foreignId('reviewed_by')
                        ->nullable()
                        ->constrained('users')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('created_by')->nullable();
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                }
                $table->string('review_status')
                    ->default('pending');

                $table->text('review_note')
                    ->nullable();

                $table->timestamp('reviewed_at')
                    ->nullable();

                $table->timestamps();

                $table->index('custom_form_id');
                $table->index('created_by');
                $table->index('review_status');
                $table->index('reviewed_by');
            });

            return;
        }
        Schema::table('custom_form_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('custom_form_entries', 'review_status')) {
                $table->string('review_status')
                    ->default('pending')
                    ->after('created_by');
            }

            if (! Schema::hasColumn('custom_form_entries', 'review_note')) {
                $table->text('review_note')
                    ->nullable()
                    ->after('review_status');
            }

            if (! Schema::hasColumn('custom_form_entries', 'reviewed_by')) {
                if (Schema::hasTable('users')) {
                    $table->foreignId('reviewed_by')
                        ->nullable()
                        ->after('review_note')
                        ->constrained('users')
                        ->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('reviewed_by')
                        ->nullable()
                        ->after('review_note');
                }
            }

            if (! Schema::hasColumn('custom_form_entries', 'reviewed_at')) {
                $table->timestamp('reviewed_at')
                    ->nullable()
                    ->after('reviewed_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_form_entries');
    }
};
