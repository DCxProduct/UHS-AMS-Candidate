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

class ExamResultsKhmerNameTest extends TestCase
{
    use RefreshDatabase;

    public static function names(): array
    {
        return [
            'explicit parts' => [
                ['first_name_kh' => ' សុខ ', 'last_name_kh' => ' ដារា វីរៈ ', 'name_khmer' => 'ចាស់ ផ្សេង'],
                null, ['សុខ', 'ដារា វីរៈ'],
            ],
            'legacy combined' => [
                ['name_khmer' => ' សុខ   ដារា វីរៈ '], null, ['សុខ', 'ដារា វីរៈ'],
            ],
            'first part only' => [
                ['first_name_kh' => 'សុខ', 'name_khmer' => 'ចាស់ ផ្សេង'], 'ម្ចាស់ គណនី', ['សុខ', '-'],
            ],
            'last part only' => [
                ['last_name_kh' => 'ដារា', 'name_khmer' => 'ចាស់ ផ្សេង'], 'ម្ចាស់ គណនី', ['-', 'ដារា'],
            ],
            'blank name' => [['name_khmer' => '  '], null, ['-', '-']],
            'creator fallback' => [[], 'កែវ សុភា នីតា', ['កែវ', 'សុភា នីតា']],
            'single legacy word' => [['name_khmer' => 'គន្ធា'], null, ['គន្ធា', '-']],
        ];
    }

    #[DataProvider('names')]
    public function test_both_excel_sheets_keep_separate_khmer_name_parts_and_legacy_fallbacks(array $data, ?string $creatorName, array $expected): void
    {
        $record = new CustomFormEntry;
        $record->data = $data;
        $record->setRelation('creator', $creatorName === null ? null : new User(['name' => $creatorName]));

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $definitions = (new ReflectionMethod(ExamResultsTable::class, 'exportColumnDefinitions'))->invoke(null);
            $this->assertArrayNotHasKey('name_khmer', $definitions);
            $this->assertSame('first_name_kh', $definitions['first_name_khmer']['field_key']);
            $this->assertSame('last_name_kh', $definitions['last_name_khmer']['field_key']);
            $response = ExamResultsTable::downloadExcel([$record], ['first_name_khmer', 'last_name_khmer']);
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;

            try {
                $this->assertTrue($zip->open($path));
                $this->assertStringContainsString('name="Database Export"', $zip->getFromName('xl/workbook.xml'));
                $this->assertStringContainsString('name="Clean Data"', $zip->getFromName('xl/workbook.xml'));
                $labels = $locale === 'km'
                    ? ['នាមត្រកូល (ខ្មែរ)', 'នាមខ្លួន (ខ្មែរ)']
                    : ['First Name (Khmer)', 'Last Name (Khmer)'];

                foreach ([1 => ['first_name_kh', 'last_name_kh'], 2 => $labels] as $number => $headings) {
                    $sheet = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$number}.xml"));
                    $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                    $this->assertSame($headings, $this->rowValues($sheet, 1));
                    $this->assertSame($expected, $this->rowValues($sheet, 2));
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
    public function test_both_menus_show_and_export_the_two_visible_khmer_columns(string $page): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale('km');
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin', 'name' => 'Khmer result administrator', 'username' => 'khmer_result_admin',
            'email' => 'khmer_result_admin@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        foreach (['ExamResult', 'ExitExamResult'] as $resource) {
            $admin->givePermissionTo(Permission::findOrCreate('ViewAny:'.$resource, 'web'));
        }
        $this->actingAs($admin);
        $component = Livewire::test($page)
            ->assertTableColumnExists('first_name_khmer')
            ->assertTableColumnExists('last_name_khmer')
            ->assertTableColumnDoesNotExist('name_khmer')
            ->assertTableColumnVisible('first_name_khmer')
            ->assertTableColumnVisible('last_name_khmer');
        $instance = $component->instance();
        $keys = (new ReflectionMethod($instance, 'visibleExportColumnKeys'))->invoke($instance);
        $this->assertContains('first_name_khmer', $keys);
        $this->assertContains('last_name_khmer', $keys);
        $this->assertNotContains('name_khmer', $keys);

        $record = new CustomFormEntry;
        $record->id = 123;
        $record->data = ['first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារា'];
        $record->setRelation('creator', null);
        foreach (['first_name_khmer' => 'សុខ', 'last_name_khmer' => 'ដារា'] as $key => $expected) {
            $column = $instance->getTable()->getColumn($key)->record($record);
            $this->assertSame($expected, $column->getState());
        }
    }

    private function rowValues(\SimpleXMLElement $sheet, int $row): array
    {
        return array_map(
            static fn (\SimpleXMLElement $value): string => (string) $value,
            $sheet->xpath('//s:row[@r="'.$row.'"]/s:c/s:is/s:t'),
        );
    }
}
