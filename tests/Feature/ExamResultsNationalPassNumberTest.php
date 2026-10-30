<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Role;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExamResultsNationalPassNumberTest extends TestCase
{
    use RefreshDatabase;

    public static function nationalNumbers(): array
    {
        return [
            'leading zeroes' => ['00017', '00017'],
            'integer value' => [42, '42'],
            'zero value' => [0, '0'],
            'missing value' => [null, '-'],
        ];
    }

    #[DataProvider('nationalNumbers')]
    public function test_both_excel_sheets_use_only_the_national_pass_number_and_preserve_its_text(mixed $number, string $expected): void
    {
        $record = $this->record($number);

        foreach (['en' => 'National Exam Pass List Number', 'km' => 'លេខរៀងជាប់ថ្នាក់ជាតិ'] as $locale => $label) {
            app()->setLocale($locale);
            $response = ExamResultsTable::downloadExcel([$record], ['national_exam_pass_list_number']);
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;

            try {
                $this->assertTrue($zip->open($path));
                foreach ([1 => 'national_exam_pass_list_number', 2 => $label] as $number => $heading) {
                    $sheet = $this->sheet($zip, $number);
                    $this->assertSame([$heading], $this->rowValues($sheet, 1));
                    $this->assertSame([$expected], $this->rowValues($sheet, 2));
                }
            } finally {
                $zip->close();
                @unlink($path);
            }
        }
    }

    public function test_full_and_stale_selected_exports_cannot_reintroduce_id_or_seat_columns(): void
    {
        app()->setLocale('en');
        $record = $this->record('00017');
        $definitions = (new ReflectionMethod(ExamResultsTable::class, 'exportColumnDefinitions'))->invoke(null);
        $this->assertArrayNotHasKey('id', $definitions);
        $this->assertArrayNotHasKey('seat_number', $definitions);
        $this->assertArrayNotHasKey('candidate_id', $definitions);
        $this->assertArrayHasKey('no', $definitions);
        $this->assertSame('national_exam_pass_list_number', $definitions['national_exam_pass_list_number']['field_key']);

        foreach ([null, ['id', 'seat_number', 'national_exam_pass_list_number']] as $keys) {
            $response = ExamResultsTable::downloadExcel([$record], $keys);
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;

            try {
                $this->assertTrue($zip->open($path));
                foreach ([1 => 'national_exam_pass_list_number', 2 => 'National Exam Pass List Number'] as $number => $heading) {
                    $sheet = $this->sheet($zip, $number);
                    $headings = $this->rowValues($sheet, 1);
                    $this->assertNotContains('id', $headings);
                    $this->assertNotContains('ID', $headings);
                    $this->assertNotContains('seat_number', $headings);
                    $this->assertNotContains('Seat Number', $headings);
                    $this->assertContains($heading, $headings);
                    $index = array_search($heading, $headings, true);
                    $this->assertSame('00017', $this->rowValues($sheet, 2)[$index]);
                    if ($keys !== null) {
                        $this->assertSame([$heading], $headings);
                    }
                }
            } finally {
                $zip->close();
                @unlink($path);
            }
        }
    }

    public static function menus(): array
    {
        return [[ListExamResults::class], [ListExitExamResults::class]];
    }

    #[DataProvider('menus')]
    public function test_both_menus_show_and_export_the_national_pass_number_without_id_or_seat(string $page): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale('km');
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin', 'name' => 'National result administrator', 'username' => 'national_result_admin',
            'email' => 'national_result_admin@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        foreach (['ExamResult', 'ExitExamResult'] as $resource) {
            $admin->givePermissionTo(Permission::findOrCreate('ViewAny:'.$resource, 'web'));
        }
        $this->actingAs($admin);
        $component = Livewire::test($page)
            ->assertTableColumnDoesNotExist('id')
            ->assertTableColumnDoesNotExist('seat_number')
            ->assertTableColumnExists('no')
            ->assertTableColumnExists('candidate_id')
            ->assertTableColumnExists('national_exam_pass_list_number')
            ->assertTableColumnVisible('national_exam_pass_list_number');
        $instance = $component->instance();
        $keys = (new ReflectionMethod($instance, 'visibleExportColumnKeys'))->invoke($instance);
        $this->assertContains('national_exam_pass_list_number', $keys);
        $this->assertNotContains('id', $keys);
        $this->assertNotContains('seat_number', $keys);

        $column = $instance->getTable()->getColumn('national_exam_pass_list_number')->record($this->record('00017'));
        $this->assertSame('លេខរៀងជាប់ថ្នាក់ជាតិ', $column->getLabel());
        $this->assertTrue($column->isSearchable());
        $this->assertSame('00017', $column->getState());
    }

    private function record(mixed $nationalNumber): CustomFormEntry
    {
        $record = new CustomFormEntry;
        $record->id = 123;
        $record->candidate_id = 'BNM260001';
        $record->data = [
            'national_exam_pass_list_number' => $nationalNumber,
            'seat_number' => 'SEAT-OLD', 'list_number' => 'LIST-OLD',
            'national_registration_number' => 'REGISTRATION-OLD',
        ];
        $record->setRelation('creator', new User(['name' => 'National candidate', 'seat_number' => 'CREATOR-SEAT-OLD']));

        return $record;
    }

    private function sheet(\ZipArchive $zip, int $number): \SimpleXMLElement
    {
        $sheet = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$number}.xml"));
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $sheet;
    }

    private function rowValues(\SimpleXMLElement $sheet, int $row): array
    {
        return array_map(
            static fn (\SimpleXMLElement $value): string => (string) $value,
            $sheet->xpath('//s:row[@r="'.$row.'"]/s:c/s:is/s:t'),
        );
    }
}
