<?php

namespace Tests\Feature;

use App\Support\SpreadsheetRowReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class SpreadsheetRowReaderTest extends TestCase
{
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_csv_preserves_identifiers_quoted_values_and_original_row_numbers(): void
    {
        $file = UploadedFile::fake()->createWithContent('results.csv', "\xEF\xBB\xBFcandidate_id,name_latin\n\nMED-0001,\"Surname, Name\"\nMED-0002,ខ្មែរ\n");

        $this->assertSame([
            1 => ['candidate_id', 'name_latin'],
            3 => ['MED-0001', 'Surname, Name'],
            4 => ['MED-0002', 'ខ្មែរ'],
        ], app(SpreadsheetRowReader::class)->read($file));
    }

    public function test_only_the_first_visible_sheet_is_read_and_stale_export_sheet_rows_are_ignored(): void
    {
        $file = $this->workbook([
            ['name' => 'Hidden', 'state' => 'hidden', 'xml' => $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>hidden</t></is></c></row>')],
            ['name' => 'Clean Data', 'xml' => $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>candidate_id</t></is></c></row><row r="3"><c r="A3" t="inlineStr"><is><t>MED-0001</t></is></c></row>')],
            ['name' => 'Database Export', 'xml' => $this->sheet('<row r="1"><c r="A1"><f>1+1</f><v>2</v></c></row>')],
        ]);

        $this->assertSame([1 => ['candidate_id'], 3 => ['MED-0001']], app(SpreadsheetRowReader::class)->read($file));
    }

    public function test_xlsx_reads_namespaced_rich_strings_sparse_cells_and_numeric_excel_dates(): void
    {
        $xml = '<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData>'
            .'<x:row r="1"><x:c r="A1" t="s"><x:v>0</x:v></x:c><x:c r="C1" t="inlineStr"><x:is><x:t>date_of_birth</x:t></x:is></x:c></x:row>'
            .'<x:row r="4"><x:c r="A4" t="s"><x:v>1</x:v></x:c><x:c r="C4" s="1"><x:v>43831</x:v></x:c><x:c r="D4" s="2"><x:v>43831.5</x:v></x:c><x:c r="E4" t="inlineStr"><x:is><x:r><x:t>First </x:t></x:r><x:r><x:t>Last</x:t></x:r></x:is></x:c></x:row>'
            .'</x:sheetData></x:worksheet>';
        $strings = '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>candidate_id</t></si><si><r><t>MED-</t></r><r><t>0001</t></r></si></sst>';
        $styles = '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm:ss"/></numFmts><cellXfs><xf numFmtId="0"/><xf numFmtId="14"/><xf numFmtId="164"/></cellXfs></styleSheet>';
        $file = $this->workbook([['name' => 'Candidates', 'xml' => $xml]], strings: $strings, styles: $styles);

        $this->assertSame([
            1 => [0 => 'candidate_id', 2 => 'date_of_birth'],
            4 => [0 => 'MED-0001', 2 => '2020-01-01', 3 => '2020-01-01 12:00:00', 4 => 'First Last'],
        ], app(SpreadsheetRowReader::class)->read($file));
    }

    public function test_an_explicit_worksheet_name_selects_that_visible_sheet_and_rejects_a_missing_name(): void
    {
        $file = $this->workbook([
            ['name' => 'Clean Data', 'xml' => $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>display label</t></is></c></row>')],
            ['name' => 'Database Export', 'xml' => $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>candidate_id</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>MED-0001</t></is></c></row>')],
        ]);
        $reader = app(SpreadsheetRowReader::class);

        $this->assertSame([1 => ['display label']], $reader->read($file));
        $this->assertSame([1 => ['candidate_id'], 2 => ['MED-0001']], $reader->read($file, 'Database Export'));

        try {
            $reader->read($file, 'Missing sheet');
            $this->fail('A missing named worksheet must not fall back to another sheet.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                __('candidate_statistics_import.errors.worksheet_missing', ['sheet' => 'Missing sheet']),
            ], $exception->errors()['file']);
        }
    }

    public function test_workbook_date1904_setting_is_respected(): void
    {
        $file = $this->workbook([
            ['name' => 'Candidates', 'xml' => $this->sheet('<row r="1"><c r="A1" s="1"><v>0</v></c></row>')],
        ], styles: '<styleSheet><cellXfs><xf numFmtId="0"/><xf numFmtId="14"/></cellXfs></styleSheet>', date1904: true);

        $this->assertSame([1 => ['1904-01-01']], app(SpreadsheetRowReader::class)->read($file));
    }

    public function test_automatic_selection_prefers_database_export_and_falls_back_to_the_first_visible_sheet(): void
    {
        $display = $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>display label</t></is></c></row>');
        $raw = $this->sheet('<row r="1"><c r="A1" t="inlineStr"><is><t>candidate_id</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>MED-0001</t></is></c></row>');
        $reader = app(SpreadsheetRowReader::class);

        $this->assertSame([1 => ['candidate_id'], 2 => ['MED-0001']], $reader->read($this->workbook([
            ['name' => 'Clean Data', 'xml' => $display],
            ['name' => 'Database Export', 'xml' => $raw],
        ]), SpreadsheetRowReader::AUTO_WORKSHEET));
        $this->assertSame([1 => ['candidate_id'], 2 => ['MED-0001']], $reader->read($this->workbook([
            ['name' => 'Candidates', 'xml' => $raw],
        ]), SpreadsheetRowReader::AUTO_WORKSHEET));
        $this->assertSame([1 => ['display label']], $reader->read($this->workbook([
            ['name' => 'Clean Data', 'xml' => $display],
            ['name' => 'Database Export', 'state' => 'hidden', 'xml' => $raw],
        ]), SpreadsheetRowReader::AUTO_WORKSHEET));
    }

    public function test_formulas_are_rejected_in_both_xlsx_and_csv(): void
    {
        $files = [
            $this->workbook([['name' => 'Candidates', 'xml' => $this->sheet('<row r="7"><c r="A7"><f>CONCAT("MED-","0001")</f><v>MED-0001</v></c></row>')]]),
            UploadedFile::fake()->createWithContent('results.csv', "candidate_id\n=CONCAT(\"MED-\";\"0001\")\n"),
        ];
        foreach ($files as $file) {
            try {
                app(SpreadsheetRowReader::class)->read($file);
                $this->fail('Formula cells must be rejected instead of trusting cached results.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }
    }

    public function test_invalid_workbooks_and_unsupported_formats_are_rejected(): void
    {
        $files = [
            UploadedFile::fake()->createWithContent('results.xls', 'old binary format'),
            UploadedFile::fake()->createWithContent('results.xlsx', 'not a zip file'),
            $this->workbook([['name' => 'Candidates', 'xml' => '<worksheet><broken>']]),
            $this->workbook([['name' => 'Hidden', 'state' => 'hidden', 'xml' => $this->sheet('')]]),
            $this->workbook([['name' => 'Candidates', 'xml' => '<!DOCTYPE worksheet [<!ENTITY unsafe "external">]><worksheet/>']]),
        ];
        foreach ($files as $file) {
            try {
                app(SpreadsheetRowReader::class)->read($file);
                $this->fail('The invalid import must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('file', $exception->errors());
            }
        }
    }

    public function test_more_than_ten_thousand_candidate_rows_are_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('results.csv', "candidate_id\n".str_repeat("MED-0001\n", 10001));

        $this->expectException(ValidationException::class);

        app(SpreadsheetRowReader::class)->read($file);
    }

    private function sheet(string $rows): string
    {
        return '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>';
    }

    private function workbook(array $sheets, ?string $strings = null, ?string $styles = null, bool $date1904 = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'statistics-reader-');
        $this->temporaryPaths[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $sheetDefinitions = '';
        $relationships = '';
        foreach ($sheets as $index => $sheet) {
            $id = $index + 1;
            $state = isset($sheet['state']) ? ' state="'.$sheet['state'].'"' : '';
            $sheetDefinitions .= '<sheet name="'.$sheet['name'].'" sheetId="'.$id.'" r:id="rId'.$id.'"'.$state.'/>';
            $relationships .= '<Relationship Id="rId'.$id.'" Target="worksheets/sheet'.$id.'.xml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"/>';
            $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $sheet['xml']);
        }
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr date1904="'.($date1904 ? '1' : '0').'"/><sheets>'.$sheetDefinitions.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>');
        if ($strings !== null) {
            $zip->addFromString('xl/sharedStrings.xml', $strings);
        }
        if ($styles !== null) {
            $zip->addFromString('xl/styles.xml', $styles);
        }
        $zip->close();

        return new UploadedFile($path, 'results.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
