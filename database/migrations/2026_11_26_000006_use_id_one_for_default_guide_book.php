<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_guide_books')) {
            return;
        }
        DB::transaction(function (): void {
            $guides = DB::table('system_guide_books');
            if (! (clone $guides)->where('id', 1)->exists()) {
                $existing = (clone $guides)->orderBy('id')->lockForUpdate()->first();
                if ($existing) {
                    (clone $guides)->where('id', $existing->id)->update(['id' => 1]);
                }
            }
            if (DB::getDriverName() === 'pgsql' && $guides->exists()) {
                DB::statement("SELECT setval(pg_get_serial_sequence('system_guide_books', 'id'), (SELECT MAX(id) FROM system_guide_books), true)");
            }
        });
    }

    public function down(): void
    {
        // Preserve guide contents and the canonical ID on rollback.
    }
};
