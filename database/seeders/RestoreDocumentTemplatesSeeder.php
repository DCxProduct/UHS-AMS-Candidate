<?php

namespace Database\Seeders;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestoreDocumentTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/backups/document_templates.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found: {$csvPath}");
            return;
        }

        /*
         Use backup template TYPE, not template ID.

         Backup:
         custom_form_1  => Profile
         custom_form_2  => National Exam
         custom_form_11 => Master
         custom_form_12 => Bachelor
         custom_form_13 => PhD
        */
        $formTypeMap = [
            1 => 'profile',
            2 => 'national-examination-registration',
            11 => 'master',
            12 => 'bachelor',
            13 => 'phd',
        ];

        $rows = $this->readCsv($csvPath);

        foreach ($rows as $row) {
            $oldFormId = $this->oldFormIdFromType($row['type'] ?? '');
            $mapValue = $formTypeMap[$oldFormId] ?? null;

            if (! $mapValue) {
                $this->command->warn("Skip template {$row['name']} because type {$row['type']} is not mapped.");
                continue;
            }

            $newForm = CustomForm::query()
                ->where('slug', $mapValue)
                ->orWhere('sub_item_type', $mapValue)
                ->first();

            if (! $newForm) {
                $this->command->warn("Skip template {$row['name']} because form not found: {$mapValue}");
                continue;
            }

            $newType = 'custom_form_' . $newForm->id;

            DB::table('document_templates')->updateOrInsert(
                ['type' => $newType],
                [
                    'name' => $row['name'] ?? ucfirst($mapValue) . ' Template',
                    'type' => $newType,
                    'custom_form_id' => $newForm->id,
                    'model_class' => 'Chanthoeun\\FilamentCustomForms\\Models\\CustomFormEntry',
                    'content' => $this->cleanHtml($row['content'] ?? null),
                    'page_settings' => $this->cleanJson($row['page_settings'] ?? null),
                    'extra_data_sources' => $this->cleanJson($row['extra_data_sources'] ?? null),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $this->command->info("Imported {$row['name']} => {$newType}");
        }
    }

    private function oldFormIdFromType(?string $type): int
    {
        $type = trim((string) $type);

        if (preg_match('/custom_form_(\d+)/', $type, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function readCsv(string $path): array
    {
        $file = fopen($path, 'r');

        $headers = fgetcsv($file);
        $headers = array_map(fn ($header) => trim((string) $header), $headers);

        $rows = [];

        while (($data = fgetcsv($file)) !== false) {
            if (count($headers) !== count($data)) {
                continue;
            }

            $rows[] = array_combine($headers, $data);
        }

        fclose($file);

        return $rows;
    }

    private function cleanHtml(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value);

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) {
            return $decoded;
        }

        return stripslashes($value);
    }

    private function cleanJson(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
