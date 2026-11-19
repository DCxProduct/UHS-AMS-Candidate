<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\ListMefInvoiceCodes;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\MefInvoiceCodeImportService;
use App\Support\FilamentActionPermissions;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Throwable;
use ZipArchive;

class MefInvoiceCodeTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const FILENAME = 'mef-invoice-codes-template.xlsx';

    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->actor('template-importer', ['ViewAny', 'ImportExcel']);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app()->setLocale('en');
        parent::tearDown();
    }

    public static function labels(): array
    {
        return [['en', 'Download Excel Template'], ['km', 'ទាញយកគំរូ Excel']];
    }

    #[DataProvider('labels')]
    public function test_importers_download_the_current_page_beside_import_with_localized_label_and_no_record_changes(string $locale, string $label): void
    {
        app()->setLocale($locale);
        MefInvoiceCode::query()->create(['mef_code' => 'PRIVATE-EXISTING-CODE', 'invoice_code' => '000--56789']);
        $before = DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all();
        $permissions = auth()->user()->roles->sole()->permissions->pluck('name')->sort()->values()->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)
            ->assertActionVisible('download_template')
            ->assertActionHasLabel('download_template', $label);
        $component->mountAction('download_template')->assertSet('mountedActions', []);
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $bytes = $this->downloadEffectBytes($component);
        $this->assertNotSame(file_get_contents($this->templatePath()), $bytes);
        $this->assertSame([['mef_code', 'invoice_code'], ['PRIVATE-EXISTING-CODE', '000--56789']], $this->workbookRows($bytes));

        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($permissions, auth()->user()->roles->sole()->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertDatabaseCount('payments', 0);
        $this->assertNotContains('DownloadExcel:MefInvoiceCode', FilamentActionPermissions::all());
        $this->assertDatabaseMissing('permissions', ['name' => 'DownloadExcel:MefInvoiceCode', 'guard_name' => 'web']);
    }

    public function test_real_asset_has_only_the_import_headers_and_both_entry_columns_use_text_format(): void
    {
        $zip = $this->openTemplate();
        try {
            $sheet = $this->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertContains($sheet->evaluate('string(//s:sheetViews/s:sheetView/@showGridLines)'), ['1', 'true']);
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            $sharedStrings = [];
            if ($shared !== false) {
                foreach ($this->xml($shared)->query('//s:si') as $item) {
                    $sharedStrings[] = $item->textContent;
                }
            }
            $values = [];
            foreach ($sheet->query('//s:sheetData/s:row/s:c') as $cell) {
                $value = $cell->getAttribute('t') === 's'
                    ? ($sharedStrings[(int) $sheet->evaluate('string(s:v)', $cell)] ?? '')
                    : $sheet->evaluate('string(s:is/s:t | s:v)', $cell);
                if (trim($value) !== '') {
                    $values[$cell->getAttribute('r')] = $value;
                }
            }
            $this->assertSame(['A1' => 'mef_code', 'B1' => 'invoice_code'], $values);
            $this->assertSame(1, $this->xml($zip->getFromName('xl/workbook.xml'))->query('//s:sheets/s:sheet')->length);
            $styles = $this->xml($zip->getFromName('xl/styles.xml'));
            foreach ([1, 2] as $number) {
                $style = $this->columnStyle($sheet, $number);
                $format = $styles->query('//s:cellXfs/s:xf')->item($style);
                $this->assertInstanceOf(DOMElement::class, $format);
                $formatId = $format->getAttribute('numFmtId');
                $this->assertTrue($formatId === '49' || $styles->evaluate('string(//s:numFmt[@numFmtId="'.$formatId.'"]/@formatCode)') === '@');
                $border = $styles->query('//s:borders/s:border')->item((int) $format->getAttribute('borderId'));
                foreach (['left', 'right', 'top', 'bottom'] as $side) {
                    $this->assertSame('thin', $styles->evaluate('string(s:'.$side.'/@style)', $border));
                }
            }
            foreach (['A1', 'B1', 'A2', 'B2'] as $reference) {
                $cell = $sheet->query('//s:sheetData/s:row/s:c[@r="'.$reference.'"]')->item(0);
                $this->assertInstanceOf(DOMElement::class, $cell);
                $this->assertBoxedCell($cell, $styles, requireText: ! in_array($reference, ['A1', 'B1'], true));
            }
        } finally {
            $zip->close();
        }
        try {
            app(MefInvoiceCodeImportService::class)->import(new UploadedFile($this->templatePath(), self::FILENAME, self::MIME, null, true));
            $this->fail('An unfilled template must report no data rows.');
        } catch (ValidationException $error) {
            $this->assertSame([__('mef_invoice_codes.import.errors.no_rows')], $error->errors()['file']);
        }
        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_current_pagination_exports_ten_or_fifty_rows_and_only_the_requested_second_page_in_table_order(): void
    {
        $hash = hash_file('sha256', $this->templatePath());
        $pairs = [];
        foreach (range(1, 55) as $index) {
            $pairs[] = [sprintf('MEF-%03d', $index), sprintf('000--%04d', $index)];
            MefInvoiceCode::query()->create(['mef_code' => $pairs[$index - 1][0], 'invoice_code' => $pairs[$index - 1][1]]);
        }
        $before = DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->set('tableRecordsPerPage', 10)->sortTable('mef_code', 'asc');
        $header = [['mef_code', 'invoice_code']];
        $component->mountAction('download_template')->assertSet('mountedActions', []);
        $this->assertSame([...$header, ...array_slice($pairs, 0, 10)], $this->workbookRows($this->downloadEffectBytes($component)));
        $component->call('gotoPage', 2);
        $this->assertSame([...$header, ...array_slice($pairs, 10, 10)], $this->workbookRows($this->downloadedBytes($component)));
        $component->set('tableRecordsPerPage', 50);
        $this->assertSame([...$header, ...array_slice($pairs, 0, 50)], $this->workbookRows($this->downloadedBytes($component)));
        $component->call('gotoPage', 2);
        $this->assertSame([...$header, ...array_slice($pairs, 50, 5)], $this->workbookRows($this->downloadedBytes($component)));
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($hash, hash_file('sha256', $this->templatePath()));
    }

    public function test_exports_follow_search_usage_filter_and_sort_and_zero_visible_rows_produce_only_headers(): void
    {
        $used = [];
        $unused = [];
        foreach (range(1, 8) as $index) {
            $pair = [sprintf('MATCH-%03d', $index), sprintf('000--%04d', 2000 + $index)];
            MefInvoiceCode::query()->create(['mef_code' => $pair[0], 'invoice_code' => $pair[1]]);
            if ($index % 2 === 0) {
                Payment::query()->create(['receipt_number' => $pair[1], 'amount_usd' => 5, 'status_payt' => 'paid']);
                $used[] = $pair;
            } else {
                $unused[] = $pair;
            }
        }
        MefInvoiceCode::query()->create(['mef_code' => 'OTHER-OUTSIDE-SEARCH', 'invoice_code' => '000--2998']);
        $hidden = MefInvoiceCode::query()->create(['mef_code' => 'MATCH-999', 'invoice_code' => '000--2999']);
        $hidden->forceFill(['records_cleared_at' => now()])->save();
        Payment::query()->create(['receipt_number' => $hidden->invoice_code, 'amount_usd' => 5, 'status_payt' => 'paid']);
        $before = DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all();
        $payments = DB::table('payments')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->searchTable('match')->filterTable('usage_status', 'used')->sortTable('mef_code', 'desc');
        $this->assertSame([['mef_code', 'invoice_code'], ...array_reverse($used)], $this->workbookRows($this->downloadedBytes($component)));
        $component->filterTable('usage_status', 'unused')->sortTable('mef_code', 'asc');
        $this->assertSame([['mef_code', 'invoice_code'], ...$unused], $this->workbookRows($this->downloadedBytes($component)));
        $component->searchTable('nothing-visible');
        $this->assertSame([['mef_code', 'invoice_code']], $this->workbookRows($this->downloadedBytes($component)));
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($payments, DB::table('payments')->get()->map(fn ($row): array => (array) $row)->all());
    }

    public function test_actual_export_round_trips_leading_zero_and_symbol_codes_as_text_without_modifying_the_base_asset(): void
    {
        $hash = hash_file('sha256', $this->templatePath());
        $pairs = [
            ['mef_code' => '0000123456', 'invoice_code' => '000--001/002.03+#'],
            ['mef_code' => '0000000007', 'invoice_code' => '000_000-0007'],
        ];
        foreach ($pairs as $pair) {
            MefInvoiceCode::query()->create($pair);
        }
        $before = DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->sortTable('mef_code', 'desc');
        $bytes = $this->downloadedBytes($component);
        $this->assertSame([['mef_code', 'invoice_code'], ...array_map('array_values', $pairs)], $this->workbookRows($bytes));
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $path = tempnam(sys_get_temp_dir(), 'mef-export-round-trip-');
        $this->assertNotFalse($path);
        file_put_contents($path, $bytes);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $sheet = $this->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
            foreach ($sheet->query('//s:sheetData/s:row[@r != "1"]/s:c') as $cell) {
                $this->assertSame('inlineStr', $cell->getAttribute('t'));
            }
            $zip->close();
            // Simulate an empty import destination using only this test's isolated database.
            MefInvoiceCode::query()->delete();
            $file = new UploadedFile($path, self::FILENAME, self::MIME, null, true);
            $this->assertSame(['created' => 2, 'existing' => 0], app(MefInvoiceCodeImportService::class)->import($file));
            $this->assertSame($pairs, MefInvoiceCode::query()->orderBy('id')->get(['mef_code', 'invoice_code'])->toArray());
            $this->assertSame(['created' => 0, 'existing' => 2], app(MefInvoiceCodeImportService::class)->import($file));
            $this->assertSame($pairs[0]['invoice_code'], MefInvoiceCode::resolveReceiptNumber($pairs[0]['mef_code']));
            $this->assertSame($hash, hash_file('sha256', $this->templatePath()));
        } finally {
            @unlink($path);
        }
    }

    public function test_four_selected_rows_across_pages_are_exported_in_table_order_and_import_permission_does_not_allow_clear(): void
    {
        $codes = collect(range(1, 12))->map(fn (int $index): MefInvoiceCode => MefInvoiceCode::query()->create([
            'mef_code' => sprintf('SELECT-%03d', $index), 'invoice_code' => sprintf('000--%04d', 3000 + $index),
        ]));
        $selected = [$codes[11]->id, $codes[2]->id, $codes[0]->id, $codes[9]->id];
        $expected = $codes->filter(fn (MefInvoiceCode $code): bool => in_array($code->id, $selected, true))
            ->map(fn (MefInvoiceCode $code): array => [$code->mef_code, $code->invoice_code])->values()->all();
        $before = DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->set('tableRecordsPerPage', 10)->sortTable('mef_code', 'asc')
            ->call('gotoPage', 2)->set('selectedTableRecords', $selected)->assertActionHidden('clear_data');
        $this->assertTrue($component->instance()->getTable()->isSelectionEnabled());
        $component->mountAction('download_template', arguments: ['selectedRecordKeys' => $selected])->assertSet('mountedActions', []);
        $this->assertSame([['mef_code', 'invoice_code'], ...$expected], $this->workbookRows($this->downloadEffectBytes($component)));
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
        $component->call('clearDataFromTableSelection', $selected)->assertForbidden();
        $this->assertSame($before, DB::table('mef_invoice_codes')->get()->map(fn ($row): array => (array) $row)->all());
    }

    public function test_select_all_with_exclusions_exports_only_filtered_rows_in_current_sort_across_pages(): void
    {
        $codes = collect(range(1, 12))->map(fn (int $index): MefInvoiceCode => MefInvoiceCode::query()->create([
            'mef_code' => sprintf('TRACK-%03d', $index), 'invoice_code' => sprintf('000--%04d', 4000 + $index),
        ]));
        $outside = MefInvoiceCode::query()->create(['mef_code' => 'OTHER-OUTSIDE', 'invoice_code' => '000--4999']);
        $excluded = [$codes[4]->id, $outside->id];
        $expected = $codes->reverse()->filter(fn (MefInvoiceCode $code): bool => ! in_array($code->id, $excluded, true))
            ->map(fn (MefInvoiceCode $code): array => [$code->mef_code, $code->invoice_code])->values()->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->set('tableRecordsPerPage', 10)->searchTable('TRACK')
            ->sortTable('mef_code', 'desc')->set('isTrackingDeselectedTableRecords', true)->set('deselectedTableRecords', $excluded);
        $bytes = $this->downloadedBytes($component, [
            'selectedRecordKeys' => [], 'isTrackingDeselectedRecords' => true, 'deselectedRecordKeys' => $excluded,
        ]);
        $this->assertSame([['mef_code', 'invoice_code'], ...$expected], $this->workbookRows($bytes));
        $this->assertDatabaseCount('mef_invoice_codes', 13);
    }

    public function test_stale_or_outside_filter_selection_exports_zero_rows_without_falling_back_to_unselected_rows(): void
    {
        MefInvoiceCode::query()->create(['mef_code' => 'INSIDE-MATCH', 'invoice_code' => '000--5001']);
        $outside = MefInvoiceCode::query()->create(['mef_code' => 'OUTSIDE-OTHER', 'invoice_code' => '000--5002']);
        $selected = [$outside->id, 999999];
        $component = Livewire::test(ListMefInvoiceCodes::class)->searchTable('INSIDE')->set('selectedTableRecords', $selected);
        $this->assertSame([['mef_code', 'invoice_code']], $this->workbookRows($this->downloadedBytes($component, ['selectedRecordKeys' => $selected])));
        $this->assertDatabaseCount('mef_invoice_codes', 2);
    }

    public function test_missing_or_revoked_import_and_read_permissions_cannot_download_even_with_a_forged_handler(): void
    {
        $component = Livewire::test(ListMefInvoiceCodes::class)->assertActionVisible('download_template');
        $handler = $component->instance()->getAction('download_template')->getActionFunction();
        $this->actor('template-reader', ['ViewAny']);
        Livewire::test(ListMefInvoiceCodes::class)->assertActionHidden('download_template')
            ->set('mountedActions', [['name' => 'download_template', 'arguments' => [], 'context' => []]])
            ->callMountedAction()->assertNoFileDownloaded();
        $this->assertDenied($handler);
        $this->actor('template-no-read', ['ImportExcel']);
        $this->assertDenied($handler);
        $actor = $this->actor('template-revoked', ['ViewAny', 'ImportExcel']);
        $component = Livewire::test(ListMefInvoiceCodes::class)->assertActionVisible('download_template');
        $actor->roles->sole()->revokePermissionTo('ImportExcel:MefInvoiceCode');
        $actor->unsetRelation('roles')->unsetRelation('permissions');
        $component->set('mountedActions', [['name' => 'download_template', 'arguments' => [], 'context' => []]])
            ->callMountedAction()->assertNoFileDownloaded();
        $this->assertDenied($handler);
        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    private function downloadedBytes($component, array $arguments = []): string
    {
        $component->callAction('download_template', arguments: $arguments);

        return $this->downloadEffectBytes($component);
    }

    private function downloadEffectBytes($component): string
    {
        $component->assertFileDownloaded(self::FILENAME, contentType: self::MIME);
        $bytes = base64_decode(data_get($component->effects, 'download.content'), true);
        $this->assertIsString($bytes);

        return $bytes;
    }

    private function workbookRows(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'mef-export-rows-');
        $this->assertNotFalse($path);
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $sheet = $this->xml($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertContains($sheet->evaluate('string(//s:sheetViews/s:sheetView/@showGridLines)'), ['1', 'true']);
            $styles = $this->xml($zip->getFromName('xl/styles.xml'));
            $shared = $zip->getFromName('xl/sharedStrings.xml');
            $strings = [];
            if ($shared !== false) {
                foreach ($this->xml($shared)->query('//s:si') as $item) {
                    $strings[] = $item->textContent;
                }
            }
            $rows = [];
            foreach ($sheet->query('//s:sheetData/s:row') as $row) {
                $values = [];
                foreach ($sheet->query('s:c', $row) as $cell) {
                    $this->assertBoxedCell($cell, $styles, requireText: $row->getAttribute('r') !== '1');
                    $values[] = $cell->getAttribute('t') === 's'
                        ? ($strings[(int) $sheet->evaluate('string(s:v)', $cell)] ?? '')
                        : $sheet->evaluate('string(s:is/s:t | s:v)', $cell);
                }
                if (array_filter($values, fn (string $value): bool => trim($value) !== '')) {
                    $rows[] = $values;
                }
            }

            return $rows;
        } finally {
            $zip->close();
            @unlink($path);
        }
    }

    private function templatePath(): string
    {
        return resource_path('templates/'.self::FILENAME);
    }

    private function openTemplate(): ZipArchive
    {
        $this->assertFileExists($this->templatePath());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->templatePath()));

        return $zip;
    }

    private function xml(string|false $content): DOMXPath
    {
        $this->assertIsString($content);
        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($content, LIBXML_NONET));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', self::NS);

        return $xpath;
    }

    private function columnStyle(DOMXPath $sheet, int $number): int
    {
        foreach ($sheet->query('//s:cols/s:col') as $column) {
            if ((int) $column->getAttribute('min') <= $number && (int) $column->getAttribute('max') >= $number) {
                $this->assertTrue($column->hasAttribute('style'));

                return (int) $column->getAttribute('style');
            }
        }
        $this->fail('Both template entry columns must have a text style.');
    }

    private function assertBoxedCell(DOMElement $cell, DOMXPath $styles, bool $requireText = true): void
    {
        $reference = $cell->getAttribute('r');
        $this->assertTrue($cell->hasAttribute('s'), $reference.' needs an explicit boxed cell style.');
        $format = $styles->query('//s:cellXfs/s:xf')->item((int) $cell->getAttribute('s'));
        $this->assertInstanceOf(DOMElement::class, $format);
        if ($requireText) {
            $formatId = $format->getAttribute('numFmtId');
            $this->assertTrue($formatId === '49' || $styles->evaluate('string(//s:numFmt[@numFmtId="'.$formatId.'"]/@formatCode)') === '@', $reference.' must preserve text codes.');
        }
        $border = $styles->query('//s:borders/s:border')->item((int) $format->getAttribute('borderId'));
        $this->assertInstanceOf(DOMElement::class, $border);
        foreach (['left', 'right', 'top', 'bottom'] as $side) {
            $this->assertSame('thin', $styles->evaluate('string(s:'.$side.'/@style)', $border), $reference.' '.$side.' border');
        }
    }

    private function assertDenied(callable $handler): void
    {
        try {
            $handler();
            $this->fail('Downloading without menu and import permission must be forbidden.');
        } catch (Throwable $error) {
            $this->assertInstanceOf(HttpExceptionInterface::class, $error);
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    private function actor(string $username, array $actions): User
    {
        $role = Role::findOrCreate($username.'-role', 'web');
        foreach ($actions as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':MefInvoiceCode', 'web'));
        }
        $user = User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }
}
