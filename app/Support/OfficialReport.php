<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * The official UHS report used by the Cashier downloads (PDF and Excel):
 * Kingdom of Cambodia header, UHS logo, title and date, the table and the totals,
 * always in Khmer.
 */
class OfficialReport
{
    public static function inKhmer(Closure $callback): mixed
    {
        $locale = app()->getLocale();
        app()->setLocale('km');

        try {
            return $callback();
        } finally {
            app()->setLocale($locale);
        }
    }

    public static function subtitle(): string
    {
        return __('payments.export.generated_at', [
            'date' => LocalizedDate::dayMonthYear(now()),
        ]);
    }

    /**
     * "<title> 19-10-2026.<extension>", or "<asciiName> 19-10-2026.<extension>" for old browsers.
     */
    public static function filename(string $title, string $extension, ?string $asciiName = null): string
    {
        return ($asciiName ?? $title) . ' ' . now()->format('d-m-Y') . '.' . $extension;
    }

    public static function excelDownload(string $path, string $title, string $asciiName): BinaryFileResponse
    {
        $filename = self::filename($title, 'xlsx');
        $response = Response::download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition('attachment', $filename, self::filename($title, 'xlsx', $asciiName)));

        return $response;
    }

    /**
     * The report sheet: header rows 1-10, the table from row 11, then the totals.
     *
     * @param  array<int, array{0: string, 1: string}>  $summary  [label, value] pairs
     * @param  array<int, int|float>  $columnWidths  column number => width
     * @param  array<int, int>  $leftAlignedColumns  column numbers aligned left (names)
     * @param  array{headings: array<int, string>, rows: array<int, array<int, string>>}|null  $summaryTable
     */
    public static function sheet(
        string $name,
        string $title,
        array $headings,
        array $dataRows,
        array $summary,
        array $columnWidths,
        array $leftAlignedColumns = [2, 3],
        ?string $footerText = null,
        ?int $footerColumn = null,
        ?string $footerDateText = null,
        ?string $footerName = null,
        ?array $footerDateParts = null,
        ?string $footerReceiverText = null,
        ?array $summaryTable = null,
    ): array
    {
        $lastColumn = self::columnName(count($headings));
        $headingRow = 11;
        $summaryTitleRow = $headingRow + count($dataRows) + 2;
        $summaryRow = $summaryTitleRow + 1;
        $footerRow = $summaryTable
            ? $summaryTitleRow + count($summaryTable['rows'] ?? []) + 3
            : $summaryRow + 2;
        $footerColumn = min(max(1, $footerColumn ?? count($headings)), count($headings));
        $footerCellColumn = self::columnName($footerColumn);
        $footerLastColumn = self::columnName(count($headings));
        $hasSignatureBlock = filled($footerDateText) || filled($footerName);
        $cellStyles = [];
        $dataRowHeights = [];

        foreach (array_keys($dataRows) as $index) {
            $excelRow = $headingRow + 1 + $index;
            $dataRowHeights[$excelRow] = 20;

            foreach (array_keys($headings) as $columnIndex) {
                $column = $columnIndex + 1;
                $cellStyles[self::columnName($column) . $excelRow] = in_array($column, $leftAlignedColumns, true) ? 12 : 11;
            }
        }

        if ($summaryTable) {
            foreach (array_keys($summaryTable['headings'] ?? []) as $index) {
                $cellStyles[self::columnName($index + 1) . $summaryTitleRow] = 4;
            }

            foreach (array_keys($summaryTable['rows'] ?? []) as $rowIndex) {
                foreach (array_keys($summaryTable['headings'] ?? []) as $columnIndex) {
                    $column = $columnIndex + 1;
                    $cellStyles[self::columnName($column) . ($summaryTitleRow + 1 + $rowIndex)] = $column === 1 ? 12 : 11;
                }
            }
        } else {
            foreach (array_keys($summary) as $index) {
                $column = self::columnName($index + 1);
                $cellStyles[$column . $summaryTitleRow] = 7;
                $cellStyles[$column . $summaryRow] = 13;
            }
        }

        if (filled($footerText)) {
            if ($hasSignatureBlock) {
                $cellStyles[$footerCellColumn . $footerRow] = 3;
                $cellStyles[$footerCellColumn . ($footerRow + 1)] = 14;
                $cellStyles[$footerCellColumn . ($footerRow + 2)] = 14;

                if (filled($footerReceiverText)) {
                    $cellStyles['B' . ($footerRow + 1)] = 14;
                }
            } else {
                $cellStyles[$footerCellColumn . $footerRow] = 14;
            }
        }

        $footerMerges = $hasSignatureBlock
            ? [
                $footerCellColumn . $footerRow . ':' . $footerLastColumn . $footerRow,
                $footerCellColumn . ($footerRow + 1) . ':' . $footerLastColumn . ($footerRow + 1),
                $footerCellColumn . ($footerRow + 2) . ':' . $footerLastColumn . ($footerRow + 2),
            ]
            : [];

        $rows = [
            [__('payments.export.kingdom')],
            [__('payments.export.motto')],
            [],
            [],
            [],
            [],
            [__('payments.export.university')],
            [$title],
            [self::subtitle()],
            [],
            $headings,
            ...$dataRows,
            [],
            ...($summaryTable
                ? [$summaryTable['headings'], ...$summaryTable['rows'], []]
                : [array_column($summary, 0), array_column($summary, 1)]),
        ];

        if (filled($footerText)) {
            $rows[] = [];

            if ($hasSignatureBlock) {
                $rows[] = [...array_fill(0, $footerColumn - 1, ''), $footerDateText ?? ''];
                $signatureRow = array_fill(0, $footerColumn, '');
                $signatureRow[$footerColumn - 1] = $footerText;

                if (filled($footerReceiverText)) {
                    $signatureRow[1] = $footerReceiverText;
                }

                $rows[] = $signatureRow;
                $rows[] = [...array_fill(0, $footerColumn - 1, ''), $footerName ?? ''];
            } else {
                $rows[] = [...array_fill(0, $footerColumn - 1, ''), $footerText];
            }
        }

        return [
            'name' => $name,
            'rows' => $rows,
            'options' => [
                'logo' => true,
                'ignoreNumbersAsText' => true,
                'printLandscape' => true,
                'mergedCells' => [
                    'A1:' . $lastColumn . '1',
                    'A2:' . $lastColumn . '2',
                    'A7:B7',
                    'A8:' . $lastColumn . '8',
                    'A9:' . $lastColumn . '9',
                    ...$footerMerges,
                ],
                'rowStyles' => [
                    1 => 9,
                    2 => 9,
                    7 => 10,
                    8 => 2,
                    9 => 3,
                    $headingRow => 4,
                ],
                'cellStyles' => $cellStyles,
                    'richTextCells' => filled($footerDateParts) && filled($footerText)
                        ? [$footerCellColumn . $footerRow => $footerDateParts]
                        : [],
                'rowHeights' => [
                    1 => 26,
                    2 => 26,
                    3 => 16,
                    4 => 16,
                    5 => 16,
                    6 => 16,
                    7 => 20,
                    8 => 24,
                    9 => 20,
                    10 => 12,
                    $headingRow => 24,
                    ...$dataRowHeights,
                    $summaryTitleRow => 24,
                    ...($summaryTable
                        ? array_fill_keys(range($summaryTitleRow + 1, $summaryTitleRow + count($summaryTable['rows'])), 22)
                        : [$summaryRow => 22]),
                    ...(filled($footerText) ? ($hasSignatureBlock
                        ? [$footerRow => 22, $footerRow + 1 => 24, $footerRow + 2 => 24]
                        : [$footerRow => 24]) : []),
                ],
                'columnWidths' => $columnWidths,
            ],
        ];
    }

    public static function writeXlsx(string $path, array $sheets): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $hasLogo = collect($sheets)->contains(fn (array $sheet): bool => (bool) ($sheet['options']['logo'] ?? false));

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets, $hasLogo));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        if ($hasLogo) {
            $logoPath = public_path('images/UHS_logo.png');

            if (! is_file($logoPath)) {
                throw new \RuntimeException('The UHS logo could not be found.');
            }

            $zip->addFile($logoPath, 'xl/media/image1.png');
            $zip->addFromString('xl/drawings/drawing1.xml', self::drawingXml());
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', self::drawingRelationshipsXml());
        }

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                self::worksheetXml($sheet['rows'], $sheet['styledRows'] ?? [], $sheet['options'] ?? [])
            );

            if (($sheet['options']['logo'] ?? false) === true) {
                $zip->addFromString(
                    'xl/worksheets/_rels/sheet' . ($index + 1) . '.xml.rels',
                    self::worksheetRelationshipsXml(),
                );
            }
        }

        $zip->close();
    }

    public static function worksheetXml(array $rows, array $styledRows = [], array $options = []): string
    {
        $rowStyles = $options['rowStyles'] ?? [];
        $cellStyles = $options['cellStyles'] ?? [];
        $mergedCells = $options['mergedCells'] ?? [];
        $columnWidths = $options['columnWidths'] ?? [];
        $rowHeights = $options['rowHeights'] ?? [];
        $richTextCells = $options['richTextCells'] ?? [];
        $hasLogo = ($options['logo'] ?? false) === true;
        $printLandscape = ($options['printLandscape'] ?? false) === true;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' . ($hasLogo ? ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"' : '') . '>'
            . ($printLandscape ? '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>' : '')
            . (filled($columnWidths) ? '<cols>' . collect($columnWidths)->map(fn (float|int $width, int $column): string => '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>')->implode('') . '</cols>' : '')
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $height = $rowHeights[$excelRow] ?? null;
            $rowAttributes = filled($height) ? ' ht="' . $height . '" customHeight="1"' : '';
            $xml .= '<row r="' . $excelRow . '"' . $rowAttributes . '>';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $excelRow;
                $styleId = $cellStyles[$cell] ?? $rowStyles[$excelRow] ?? (in_array($excelRow, $styledRows, true) ? 1 : 0);
                $style = $styleId > 0 ? ' s="' . $styleId . '"' : '';

                if (isset($richTextCells[$cell]) && is_array($richTextCells[$cell])) {
                    $runs = collect($richTextCells[$cell])
                        ->map(function (array $run): string {
                            $properties = ($run['bold'] ?? false) ? '<rPr><b/></rPr>' : '';

                            return '<r>' . $properties . '<t xml:space="preserve">' . self::xmlValue($run['text'] ?? '') . '</t></r>';
                        })
                        ->implode('');

                    $xml .= '<c r="' . $cell . '"' . $style . ' t="inlineStr"><is>' . $runs . '</is></c>';

                    continue;
                }

                $xml .= '<c r="' . $cell . '"' . $style . ' t="inlineStr"><is><t>' . self::xmlValue($value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if (filled($mergedCells)) {
            $xml .= '<mergeCells count="' . count($mergedCells) . '">' . collect($mergedCells)->map(fn (string $range): string => '<mergeCell ref="' . $range . '"/>')->implode('') . '</mergeCells>';
        }

        if ($printLandscape) {
            $xml .= '<printOptions horizontalCentered="1"/>'
                . '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
                . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        }

        if (($options['ignoreNumbersAsText'] ?? false) === true && $rows !== []) {
            $lastColumn = self::columnName(max(1, ...array_map(fn (array $row): int => count($row), $rows)));
            $xml .= '<ignoredErrors><ignoredError sqref="A1:' . $lastColumn . count($rows) . '" numberStoredAsText="1"/></ignoredErrors>';
        }

        if ($hasLogo) {
            $xml .= '<drawing r:id="rId1"/>';
        }

        return $xml . '</worksheet>';
    }

    private static function contentTypesXml(array $sheets, bool $hasLogo = false): string
    {
        $overrides = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . ($hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($hasLogo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . $overrides
            . '</Types>';
    }

    private static function drawingXml(): string
    {
        $extent = 760000;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>990000</xdr:colOff><xdr:row>2</xdr:row><xdr:rowOff>30000</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $extent . '" cy="' . $extent . '"/>'
            . '<xdr:pic>'
            . '<xdr:nvPicPr><xdr:cNvPr id="1" name="UHS Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $extent . '" cy="' . $extent . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic>'
            . '<xdr:clientData/>'
            . '</xdr:oneCellAnchor>'
            . '</xdr:wsDr>';
    }

    private static function drawingRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>'
            . '</Relationships>';
    }

    private static function worksheetRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
    }

    private static function workbookXml(array $sheets): string
    {
        $sheetXml = collect(array_values($sheets))
            ->map(fn (array $sheet, int $index): string => '<sheet name="' . self::xmlValue(self::sheetName($sheet['name'])) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetXml . '</sheets>'
            . '</workbook>';
    }

    private static function workbookRelsXml(array $sheets): string
    {
        $relationships = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>')
            ->implode('');

        $styleRelationId = count($sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '<Relationship Id="rId' . $styleRelationId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    public static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="8">'
            . '<font><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FF1F2937"/><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FF003B95"/><sz val="11"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF1F2937"/><sz val="10"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><color rgb="FF111827"/><sz val="10"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF111827"/><sz val="9"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF374151"/><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF5"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF003B95"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F5FA"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"><color rgb="FF94A3B8"/></top><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FF7F8EA3"/></left><right style="thin"><color rgb="FF7F8EA3"/></right><top style="thin"><color rgb="FF7F8EA3"/></top><bottom style="thin"><color rgb="FF7F8EA3"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="15">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>'
            . '</styleSheet>';
    }

    private static function sheetName(string $name): string
    {
        return substr($name, 0, 31);
    }

    public static function columnName(int $index): string
    {
        $column = '';

        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)) . $column;
            $index = intdiv($index, 26);
        }

        return $column;
    }

    public static function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
