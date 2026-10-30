<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Models\Major;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class ExamResultsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_uses_major_keys_in_database_export_and_labels_in_clean_data_with_formatted_birth_dates(): void
    {
        $major = Major::query()->create([
            'key' => 'bbn',
            'abbreviation' => 'BBN',
            'name_en' => 'Nursing',
            'name_kh' => 'គិលានុបដ្ឋាក',
            'is_active' => true,
        ]);

        foreach (['en' => 'BBN-Nursing', 'km' => 'BBN-គិលានុបដ្ឋាក'] as $locale => $label) {
            app()->setLocale($locale);

            foreach (['major', 'selected_major'] as $majorField) {
                $record = new CustomFormEntry;
                $record->data = [$majorField => (string) $major->getKey(), 'date_of_birth' => '2000-03-21 00:00:00'];
                $record->setRelation('creator', null);

                $response = ExamResultsTable::downloadExcel([$record], ['major', 'date_of_birth']);
                $path = $response->getFile()->getPathname();
                $zip = new \ZipArchive;

                try {
                    $this->assertTrue($zip->open($path));

                    foreach ([1 => 'bbn', 2 => $label] as $sheetNumber => $expectedMajor) {
                        $sheet = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$sheetNumber}.xml"));
                        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                        $values = array_map(
                            static fn (\SimpleXMLElement $value): string => (string) $value,
                            $sheet->xpath('//s:row[@r="2"]/s:c/s:is/s:t'),
                        );

                        $this->assertSame([$expectedMajor, '21-03-2000'], $values);
                    }
                } finally {
                    $zip->close();
                    @unlink($path);
                }
            }
        }
    }

    public function test_excel_birth_date_uses_creator_fallback_and_preserves_missing_values(): void
    {
        $record = new CustomFormEntry;
        $record->data = [];
        $record->setRelation('creator', new User(['date_of_birth' => '2001-12-05']));

        $definitions = $this->exportDefinitions();

        $this->assertSame('05-12-2001', $definitions['date_of_birth']['clean']($record));

        $record->setRelation('creator', null);
        $this->assertSame('-', $definitions['date_of_birth']['clean']($record));
        $this->assertSame('-', $definitions['major']['clean']($record));
    }

    public function test_database_export_preserves_major_values_already_stored_as_keys(): void
    {
        $record = new CustomFormEntry;
        $record->data = ['selected_major' => 'bbn'];

        $definitions = $this->exportDefinitions();

        $this->assertSame('bbn', $definitions['major']['clean']($record));
    }

    public function test_latin_names_are_exported_as_separate_columns(): void
    {
        $record = new CustomFormEntry;
        $record->data = [
            'first_name_en' => 'SOVANNRITH',
            'last_name_en' => 'VAN',
        ];

        $definitions = $this->exportDefinitions();

        $this->assertArrayHasKey('first_name_latin', $definitions);
        $this->assertArrayHasKey('last_name_latin', $definitions);
        $this->assertArrayNotHasKey('name_latin', $definitions);
        $this->assertSame('SOVANNRITH', $definitions['first_name_latin']['value']($record));
        $this->assertSame('VAN', $definitions['last_name_latin']['value']($record));
    }

    public function test_candidate_id_is_not_in_exam_result_exports(): void
    {
        $this->assertArrayNotHasKey('candidate_id', $this->exportDefinitions());
    }

    public function test_latin_name_columns_fall_back_to_the_legacy_combined_name(): void
    {
        $record = new CustomFormEntry;
        $record->data = [
            'name_latin' => 'SOVANNRITH VAN',
        ];

        $definitions = $this->exportDefinitions();

        $this->assertSame('SOVANNRITH', $definitions['first_name_latin']['value']($record));
        $this->assertSame('VAN', $definitions['last_name_latin']['value']($record));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function exportDefinitions(): array
    {
        $method = new ReflectionMethod(ExamResultsTable::class, 'exportColumnDefinitions');
        $method->setAccessible(true);

        return $method->invoke(null);
    }
}
