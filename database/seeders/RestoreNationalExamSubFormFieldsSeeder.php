<?php

namespace Database\Seeders;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreNationalExamSubFormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = storage_path('app/backups/custom_form_fields.csv');

        if (! file_exists($csvPath)) {
            $this->command->error("CSV not found: {$csvPath}");
            return;
        }

        // old backup form ID => new sub form type
        $map = [
            11 => 'master',
            12 => 'bachelor',
            13 => 'phd',
        ];

        $rows = $this->readCsv($csvPath);

        foreach ($map as $oldFormId => $subItemType) {
            $newForm = CustomForm::query()
                ->where('sub_item_type', $subItemType)
                ->where('menu_placement', 'sub_item')
                ->first();

            if (! $newForm) {
                $this->command->warn("New form not found: {$subItemType}");
                continue;
            }

            $oldFields = collect($rows)
                ->where('custom_form_id', (string) $oldFormId)
                ->sortBy('id')
                ->values();

            DB::table('custom_form_fields')
                ->where('custom_form_id', $newForm->id)
                ->delete();

            $idMap = [];

            foreach ($oldFields as $oldField) {
                $insert = [
                    'custom_form_id' => $newForm->id,
                    'parent_id' => null,
                    'name' => trim((string) $oldField['name']),
                    'label' => $oldField['label'] ?? null,
                    'type' => $oldField['type'] ?? 'text_input',
                    'required' => $this->toBoolean($oldField['required'] ?? false),
                    'options' => filled($oldField['options'] ?? null) ? $oldField['options'] : null,
                    'sort' => (int) ($oldField['sort'] ?? 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // remove columns that do not exist in your database
                $insert = collect($insert)
                    ->filter(fn ($value, $column) => Schema::hasColumn('custom_form_fields', $column))
                    ->toArray();

                $newId = DB::table('custom_form_fields')->insertGetId($insert);

                $idMap[(int) $oldField['id']] = $newId;
            }

            foreach ($oldFields as $oldField) {
                if (blank($oldField['parent_id'] ?? null)) {
                    continue;
                }

                $oldId = (int) $oldField['id'];
                $oldParentId = (int) $oldField['parent_id'];

                if (! isset($idMap[$oldId], $idMap[$oldParentId])) {
                    continue;
                }

                DB::table('custom_form_fields')
                    ->where('id', $idMap[$oldId])
                    ->update([
                        'parent_id' => $idMap[$oldParentId],
                    ]);
            }

            $this->command->info("Imported {$oldFields->count()} fields to {$subItemType} form ID {$newForm->id}");
        }
    }

    private function readCsv(string $path): array
    {
        $file = fopen($path, 'r');

        $headers = fgetcsv($file);
        $rows = [];

        while (($data = fgetcsv($file)) !== false) {
            $rows[] = array_combine($headers, $data);
        }

        fclose($file);

        return $rows;
    }

    private function toBoolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }
}
