<?php

use App\Support\SubmissionNextSteps;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_form_entries', function (Blueprint $table): void {
            $table->json('submission_next_steps_snapshot')->nullable();
        });

        // Preserve the current messages for existing completed submissions before settings change.
        CustomForm::withTrashed()->chunkById(100, function ($forms): void {
            foreach ($forms as $form) {
                SubmissionNextSteps::freezeSubmittedEntries($form);
            }
        });
    }

    public function down(): void
    {
        Schema::table('custom_form_entries', function (Blueprint $table): void {
            $table->dropColumn('submission_next_steps_snapshot');
        });
    }
};
