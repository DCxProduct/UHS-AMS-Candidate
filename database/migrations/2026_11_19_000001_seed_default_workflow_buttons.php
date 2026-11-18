<?php

use Database\Seeders\WorkflowButtonSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new WorkflowButtonSeeder)->run();
    }

    public function down(): void
    {
        // Keep button records: administrators may have edited or selected them since seeding.
    }
};
