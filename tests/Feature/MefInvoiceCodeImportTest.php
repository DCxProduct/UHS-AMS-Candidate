<?php

namespace Tests\Feature;

use App\Models\MefInvoiceCode;
use App\Services\MefInvoiceCodeImportService;
use App\Support\FilamentActionPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class MefInvoiceCodeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_import_creates_code_pairs_and_skips_repeated_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'codes.csv',
            "no,MEF Code,Invoice Code\n1,MEF-100,120-100\n2,MEF-200,120-200\n3,MEF-100,120-100\n",
        );

        $result = app(MefInvoiceCodeImportService::class)->import($file);

        $this->assertSame(['created' => 2, 'existing' => 1], $result);
        $this->assertDatabaseCount('mef_invoice_codes', 2);
        $this->assertDatabaseHas('mef_invoice_codes', [
            'mef_code' => 'MEF-100',
            'invoice_code' => '120-100',
        ]);
    }

    public function test_xlsx_import_reads_codes_from_the_first_worksheet(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mef-codes-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Codes" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="worksheet"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>no</t></si><si><t>MEF Code</t></si><si><t>Invoice Code</t></si><si><t>1</t></si><si><t>MEF-300</t></si><si><t>120--300</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row><row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2" t="s"><v>5</v></c></row></sheetData></worksheet>');
        $zip->close();

        try {
            $file = new UploadedFile(
                $path,
                'codes.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );

            $result = app(MefInvoiceCodeImportService::class)->import($file);

            $this->assertSame(['created' => 1, 'existing' => 0], $result);
            $this->assertDatabaseHas('mef_invoice_codes', [
                'mef_code' => 'MEF-300',
                'invoice_code' => '120--300',
            ]);
        } finally {
            @unlink($path);
        }
    }

    public function test_namespaced_xlsx_import_accepts_two_headers_and_preserves_all_fifty_code_pairs(): void
    {
        $pairs = [];
        $rows = '<x:row r="1"><x:c r="A1" t="str"><x:v>mef_code</x:v></x:c><x:c r="B1" t="str"><x:v>invoice_code</x:v></x:c></x:row>';

        for ($index = 1; $index <= 50; $index++) {
            $mefCode = sprintf('MEF%06d', $index);
            $invoiceCode = sprintf('120--11979482%02d', $index);
            $pairs[] = ['mef_code' => $mefCode, 'invoice_code' => $invoiceCode];
            $rowNumber = $index + 1;
            $rows .= '<x:row r="'.$rowNumber.'"><x:c r="A'.$rowNumber.'" t="str"><x:v>'.$mefCode.'</x:v></x:c><x:c r="B'.$rowNumber.'" t="str"><x:v>'.$invoiceCode.'</x:v></x:c></x:row>';
        }

        $path = tempnam(sys_get_temp_dir(), 'mef-namespaced-codes-');
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><x:Types xmlns:x="http://schemas.openxmlformats.org/package/2006/content-types"><x:Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><x:Default Extension="xml" ContentType="application/xml"/><x:Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><x:Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></x:Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><x:Relationships xmlns:x="http://schemas.openxmlformats.org/package/2006/relationships"><x:Relationship Id="rId1" Target="xl/workbook.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"/></x:Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><x:sheets><x:sheet name="Codes" sheetId="1" r:id="rId1"/></x:sheets></x:workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><x:Relationships xmlns:x="http://schemas.openxmlformats.org/package/2006/relationships"><x:Relationship Id="rId1" Target="worksheets/sheet1.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/></x:Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:dimension ref="A1:B51"/><x:sheetData>'.$rows.'</x:sheetData></x:worksheet>');
        $zip->close();

        try {
            $file = new UploadedFile(
                $path,
                '50_unique_MEF_invoice_codes.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true,
            );
            $service = app(MefInvoiceCodeImportService::class);

            $this->assertSame(['created' => 50, 'existing' => 0], $service->import($file));
            $this->assertSame($pairs, MefInvoiceCode::query()->orderBy('id')->get(['mef_code', 'invoice_code'])->toArray());

            foreach ($pairs as $pair) {
                $this->assertSame($pair['invoice_code'], MefInvoiceCode::resolveReceiptNumber($pair['mef_code']));
            }

            $this->assertSame(['created' => 0, 'existing' => 50], $service->import($file));
            $this->assertDatabaseCount('mef_invoice_codes', 50);
        } finally {
            @unlink($path);
        }
    }

    public function test_csv_import_accepts_mef_and_invoice_headers_without_a_number_column(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'codes.csv',
            "mef_code,invoice_code\nMEF000123,120--00123\nMEF000124,120--00124\n",
        );

        $result = app(MefInvoiceCodeImportService::class)->import($file);

        $this->assertSame(['created' => 2, 'existing' => 0], $result);
        $this->assertDatabaseCount('mef_invoice_codes', 2);
        $this->assertSame('120--00123', MefInvoiceCode::resolveReceiptNumber('MEF000123'));
        $this->assertSame('120--00124', MefInvoiceCode::resolveReceiptNumber('MEF000124'));
    }

    public function test_import_still_requires_each_of_the_two_code_headers(): void
    {
        foreach (["mef_code\nMEF-1\n", "invoice_code\n120--1\n"] as $content) {
            $file = UploadedFile::fake()->createWithContent('codes.csv', $content);

            try {
                app(MefInvoiceCodeImportService::class)->import($file);
                $this->fail('An import missing a required code header should be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }

        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_import_rejects_files_without_the_required_headers(): void
    {
        $file = UploadedFile::fake()->createWithContent('codes.csv', "code,invoice\nMEF-1,INV-1\n");

        $this->expectException(ValidationException::class);

        app(MefInvoiceCodeImportService::class)->import($file);
    }

    public function test_resource_model_saves_a_pair(): void
    {
        $record = MefInvoiceCode::query()->create([
            'mef_code' => 'MEF-400',
            'invoice_code' => '120--400',
        ]);

        $this->assertDatabaseHas('mef_invoice_codes', [
            'id' => $record->getKey(),
            'mef_code' => 'MEF-400',
            'invoice_code' => '120--400',
        ]);
    }

    public function test_invoice_code_keeps_numbers_and_symbols_and_removes_english_and_khmer_letters(): void
    {
        MefInvoiceCode::query()->create([
            'mef_code' => 'MEF-500',
            'invoice_code' => 'AB120-ខ្មែរ119',
        ]);

        $this->assertDatabaseHas('mef_invoice_codes', [
            'mef_code' => 'MEF-500',
            'invoice_code' => '120-119',
        ]);
    }

    public function test_import_rejects_invoice_codes_containing_letters(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'codes.csv',
            "no,MEF Code,Invoice Code\n1,MEF-600,120-ABC\n",
        );

        $this->expectException(ValidationException::class);

        app(MefInvoiceCodeImportService::class)->import($file);
    }

    public function test_receipt_number_resolves_mef_code_to_invoice_code(): void
    {
        MefInvoiceCode::query()->create([
            'mef_code' => 'MEF19011963910',
            'invoice_code' => '120--11979482',
        ]);

        $this->assertSame('120--11979482', MefInvoiceCode::resolveReceiptNumber('MEF19011963910'));
        $this->assertNull(MefInvoiceCode::resolveReceiptNumber(' UNKNOWN-RECEIPT '));
        $this->assertNull(MefInvoiceCode::resolveReceiptNumber('  '));
    }

    public function test_import_excel_permission_is_registered_and_export_permission_is_removed(): void
    {
        $this->assertContains('ImportExcel:MefInvoiceCode', FilamentActionPermissions::all());
        $this->assertNotContains('DownloadExcel:MefInvoiceCode', FilamentActionPermissions::all());
        $this->assertDatabaseHas('permissions', [
            'name' => 'ImportExcel:MefInvoiceCode',
            'guard_name' => 'web',
        ]);
        $this->assertDatabaseMissing('permissions', [
            'name' => 'DownloadExcel:MefInvoiceCode',
            'guard_name' => 'web',
        ]);
    }
}
