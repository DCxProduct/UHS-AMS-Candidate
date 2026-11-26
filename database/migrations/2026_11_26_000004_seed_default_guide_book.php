<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_guide_books') || DB::table('system_guide_books')->exists()) {
            return;
        }

        DB::table('system_guide_books')->insert([
            'id' => 1,
            'title_en' => 'System User Guide',
            'title_km' => 'សៀវភៅណែនាំប្រើប្រាស់ប្រព័ន្ធ',
            'description_en' => 'Guide to using the system.',
            'description_km' => 'សេចក្ដីណែនាំអំពីការប្រើប្រាស់ប្រព័ន្ធ។',
            'file_path' => '',
            'file_name' => null,
            'is_published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep guides and uploaded files when rolling back the default-data setup.
    }
};
