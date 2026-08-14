<?php

namespace Database\Seeders;

use App\Models\UserType;
use App\Support\UserTypeOptions;
use Illuminate\Database\Seeder;

class CandidateTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserTypeOptions::defaultRecords() as $record) {
            UserType::query()->updateOrCreate(
                ['key' => $record['key']],
                $record,
            );
        }

        UserType::query()
            ->where('key', 'master')
            ->delete();
    }
}
