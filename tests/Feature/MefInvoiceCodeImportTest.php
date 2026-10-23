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
        $this->assertSame('UNKNOWN-RECEIPT', MefInvoiceCode::resolveReceiptNumber(' UNKNOWN-RECEIPT '));
        $this->assertNull(MefInvoiceCode::resolveReceiptNumber('  '));
    }

    public function test_export_excel_permission_is_registered_for_the_resource(): void
    {
        $this->assertContains('DownloadExcel:MefInvoiceCode', FilamentActionPermissions::all());
    }
}
