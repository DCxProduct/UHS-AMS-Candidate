<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_guide_books')) {
            return;
        }
        foreach (['applications', 'sort_order'] as $column) {
            if (Schema::hasColumn('system_guide_books', $column)) {
                Schema::table('system_guide_books', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        // These display settings have been removed from the guide-book feature.
    }
};
