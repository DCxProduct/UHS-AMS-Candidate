<?php

namespace Database\Seeders;

use App\Models\DegreeLevel;
use Illuminate\Database\Seeder;

class DegreeLevelSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->records() as $record) {
            DegreeLevel::query()->updateOrCreate(
                ['key' => $record['key']],
                $record,
            );
        }
    }

    private function records(): array
    {
        return [
            [
                'key' => 'associate',
                'label_en' => 'Associate',
                'label_kh' => 'បរិញ្ញាបត្ររង',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'key' => 'bachelor',
                'label_en' => 'Bachelor',
                'label_kh' => 'បរិញ្ញាបត្រ',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'key' => 'master_of_science',
                'label_en' => 'Master\'s Degree',
                'label_kh' => 'បរិញ្ញាបត្រជាន់ខ្ពស់',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'key' => 'dental_surgeon',
                'label_en' => 'Dental Surgeon',
                'label_kh' => 'ទន្តបណ្ឌិត',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'key' => 'doctor_of_medicine',
                'label_en' => 'Doctor of Medicine',
                'label_kh' => 'វេជ្ជបណ្ឌិត',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'key' => 'medical_specialty',
                'label_en' => 'Medical Specialty',
                'label_kh' => 'វេជ្ជបណ្ឌិតឯកទេស',
                'is_active' => true,
                'sort_order' => 6,
            ],
        ];
    }
}
