<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('majors', 'key')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->string('key', 100)->nullable()->unique();
            });
        }

        DB::table('majors')
            ->whereNull('key')
            ->orWhere('key', '')
            ->orderBy('id')
            ->chunkById(100, function ($majors): void {
                foreach ($majors as $major) {
                    $baseKey = Str::of((string) $major->name_en)
                        ->trim()
                        ->lower()
                        ->replaceMatches('/[^a-z0-9_-]+/', '_')
                        ->replaceMatches('/_+/', '_')
                        ->trim('_')
                        ->toString();
                    $baseKey = $baseKey !== '' ? $baseKey : 'major';
                    $key = $baseKey;
                    $suffix = 2;

                    while (DB::table('majors')->where('key', $key)->where('id', '!=', $major->id)->exists()) {
                        $key = $baseKey.'_'.$suffix++;
                    }

                    DB::table('majors')->where('id', $major->id)->update(['key' => $key]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('majors', 'key')) {
            Schema::table('majors', function (Blueprint $table): void {
                $table->dropUnique('majors_key_unique');
                $table->dropColumn('key');
            });
        }
    }
};
