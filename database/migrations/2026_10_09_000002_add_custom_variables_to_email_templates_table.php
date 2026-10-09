<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-defined {{ variables }} with a fixed value per language.
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->json('custom_variables')->nullable()->after('header_title');
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->dropColumn('custom_variables');
        });
    }
};
