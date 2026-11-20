<?php

use App\Models\WorkflowStageType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        WorkflowStageType::ensureCandidateIdGenerationAvailable();
    }

    public function down(): void
    {
        // Keep catalog records because workflows may already reference the new checkpoint.
    }
};
