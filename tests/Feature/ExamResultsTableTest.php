<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use ReflectionMethod;
use Tests\TestCase;

class ExamResultsTableTest extends TestCase
{
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
