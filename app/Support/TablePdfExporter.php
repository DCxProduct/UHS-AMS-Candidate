<?php

namespace App\Support;

use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use Symfony\Component\HttpFoundation\HeaderUtils;

class TablePdfExporter
{
    public static function download(
        string $filenamePrefix,
        array $headings,
        iterable $rows,
        ?string $title = null,
        array $summaryRows = [],
        array $options = [],
    ) {
        $html = self::html($headings, $rows, $title, $summaryRows, $options);
        $fontDirectory = realpath(base_path('packages/chanthoeun/filament-document-builder/resources/fonts'));

        $pdf = Pdf::loadHTML($html, [
            'format' => 'A4',
            'orientation' => 'L',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'default_font' => 'khmerbattambang',
            'custom_font_dir' => $fontDirectory ? $fontDirectory . DIRECTORY_SEPARATOR : null,
            'custom_font_data' => [
                'khmerbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmermuol' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'calibri' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
            ],
        ]);

        $filename = $options['filename'] ?? $filenamePrefix . now()->format('Y-m-d-His') . '.pdf';

        $response = response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);

        if (filled($options['filename_fallback'] ?? null)) {
            $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition('attachment', $filename, $options['filename_fallback']));
        }

        return $response;
    }

    protected static function html(
        array $headings,
        iterable $rows,
        ?string $title,
        array $summaryRows = [],
        array $options = [],
    ): string
    {
        if (in_array($options['layout'] ?? null, ['payment-records', 'official-report'], true)) {
            return self::paymentRecordsHtml($headings, $rows, $title, $options);
        }

        $headingHtml = collect($headings)
            ->map(fn ($heading): string => '<th>' . e((string) $heading) . '</th>')
            ->implode('');

        $rowHtml = collect($rows)
            ->map(function (array $row): string {
                $cells = collect($row)
                    ->map(fn ($value): string => '<td>' . e((string) $value) . '</td>')
                    ->implode('');

                return '<tr>' . $cells . '</tr>';
            })
            ->implode('');

        $summaryHtml = collect($summaryRows)
            ->map(function (array $row): string {
                $cells = collect($row)
                    ->map(fn ($value): string => '<td>' . e((string) $value) . '</td>')
                    ->implode('');

                return '<tr>' . $cells . '</tr>';
            })
            ->implode('');

        $documentTitle = e(filled($title) ? (string) $title : 'PDF Document');
        $titleHtml = filled($title) ? '<h1>' . $documentTitle . '</h1>' : '';

        return '<!doctype html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>' . $documentTitle . '</title>
    <style>
        body { font-family: khmerbattambang, calibri, sans-serif; font-size: 9pt; }
        h1 { font-size: 15pt; text-align: center; margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 0.5pt solid #777; padding: 4px 5px; vertical-align: middle; }
        th { background-color: #e8eef5; font-weight: bold; text-align: center; }
        td { word-wrap: break-word; }
        tfoot td { background-color: #e8eef5; color: #1f2937; font-weight: bold; border-top: 1.5pt solid #94a3b8; }
    </style>
</head>
<body>
    ' . $titleHtml . '
    <table>
        <thead><tr>' . $headingHtml . '</tr></thead>
        <tbody>' . $rowHtml . '</tbody>
        <tfoot>' . $summaryHtml . '</tfoot>
    </table>
</body>
</html>';
    }

    protected static function paymentRecordsHtml(array $headings, iterable $rows, ?string $title, array $options): string
    {
        $headingHtml = collect($headings)
            ->map(fn ($heading): string => '<th>' . e((string) $heading) . '</th>')
            ->implode('');

        $rowHtml = collect($rows)
            ->map(function (array $row): string {
                $cells = collect($row)
                    ->map(fn ($value): string => '<td>' . e((string) $value) . '</td>')
                    ->implode('');

                return '<tr>' . $cells . '</tr>';
            })
            ->implode('');

        $summary = $options['summary'] ?? [
            'count' => '0',
            'usd' => '0.00',
            'khr' => '0.00',
        ];
        $summaryCells = $options['summary_cells'] ?? [
            [__('payments.export.total_records'), (string) $summary['count']],
            [__('payments.export.total_usd'), $summary['usd'] . ' $'],
            [__('payments.export.total_khr'), $summary['khr'] . ' KHR'],
        ];
        $summaryHeadHtml = collect($summaryCells)->map(fn (array $cell): string => '<th>' . e((string) $cell[0]) . '</th>')->implode('');
        $summaryBodyHtml = collect($summaryCells)->map(fn (array $cell): string => '<td>' . e((string) $cell[1]) . '</td>')->implode('');
        $summaryWidth = min(58, 20 * max(1, count($summaryCells)));
        $footerColumnCount = max(1, count($headings));
        $footerColumn = min(max(1, (int) ($options['footer_column'] ?? $footerColumnCount)), $footerColumnCount);
        $footerColumnWidth = 100 / $footerColumnCount;
        $footerOffset = ($footerColumn - 1) * $footerColumnWidth;
        $footerText = filled($options['footer_text'] ?? null)
            ? '<div class="report-signature" style="width: ' . $footerColumnWidth . '%; margin-left: ' . $footerOffset . '%;">' . e((string) $options['footer_text']) . '</div>'
            : '';
        $documentTitle = e(filled($title) ? (string) $title : 'Payment Records');
        $subtitle = e((string) ($options['subtitle'] ?? ''));
        $logo = self::logoDataUri();
        $logoHtml = $logo ? '<img src="' . $logo . '" class="official-logo" alt="UHS">' : '';

        return '<!doctype html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>' . $documentTitle . '</title>
    <style>
        @page { margin: 10mm 8mm 10mm 8mm; }
        body { font-family: khmerbattambang, calibri, sans-serif; font-size: 8pt; color: #111827; }
        .official-header { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .official-header td { border: 0; padding: 0; vertical-align: top; }
        .official-side { width: 30%; text-align: center; }
        .official-center { width: 40%; text-align: center; }
        .official-kingdom, .official-motto, .official-university { font-family: khmermuol, khmerbattambang, sans-serif; color: #111827; }
        .official-kingdom, .official-motto { font-size: 10pt; }
        .official-header td.official-motto-cell { padding-top: 7px; }
        .official-logo { height: 18mm; }
        .official-university { font-size: 8pt; margin-top: 2px; }
        .report-header { text-align: center; margin-bottom: 10px; }
        .report-title { color: #003b95; font-family: khmermuol, khmerbattambang, calibri, sans-serif; font-size: 11pt; font-weight: bold; margin: 0; }
        .report-title-latin { font-family: calibri, sans-serif; }
        .report-subtitle { color: #374151; font-size: 10pt; margin: 2px 0 0; }
        .data-table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .data-table th, .data-table td { border: 0.5pt solid #7f8ea3; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
        .data-table th { background-color: #003b95; color: #ffffff; font-weight: bold; text-align: center; }
        .data-table td { text-align: center; }
        .data-table th:nth-child(2), .data-table td:nth-child(2), .data-table th:nth-child(3), .data-table td:nth-child(3) { text-align: left; }
        .report-footer { margin-top: 12px; width: 100%; }
        .summary-table { border-collapse: collapse; width: ' . $summaryWidth . '%; }
        .summary-table th, .summary-table td { border: 0.5pt solid #7f8ea3; padding: 4px 6px; }
        .summary-table th { background-color: #003b95; color: #ffffff; font-weight: bold; text-align: center; }
        .summary-table td { background-color: #f2f5fa; font-weight: bold; text-align: center; }
        .report-signature { font-family: khmermuol, khmerbattambang, sans-serif; font-size: 10pt; font-weight: bold; margin-top: 32mm; text-align: center; }
    </style>
</head>
<body>
    <table class="official-header">
        <tr>
            <td class="official-side"></td>
            <td class="official-center"><div class="official-kingdom">' . e(__('payments.export.kingdom')) . '</div></td>
            <td class="official-side"></td>
        </tr>
        <tr>
            <td class="official-side"></td>
            <td class="official-center official-motto-cell"><div class="official-motto">' . e(__('payments.export.motto')) . '</div></td>
            <td class="official-side"></td>
        </tr>
        <tr>
            <td class="official-side">
                ' . $logoHtml . '
                <div class="official-university">' . e(__('payments.export.university')) . '</div>
            </td>
            <td class="official-center"></td>
            <td class="official-side"></td>
        </tr>
    </table>
    <div class="report-header">
        <div class="report-title' . (preg_match('/\p{Khmer}/u', (string) $title) ? '' : ' report-title-latin') . '">' . $documentTitle . '</div>
        <div class="report-subtitle">' . $subtitle . '</div>
    </div>
    <table class="data-table">
        <thead><tr>' . $headingHtml . '</tr></thead>
        <tbody>' . $rowHtml . '</tbody>
    </table>
    <div class="report-footer">
        <table class="summary-table">
            <thead><tr>' . $summaryHeadHtml . '</tr></thead>
            <tbody><tr>' . $summaryBodyHtml . '</tr></tbody>
        </table>
    </div>
    ' . $footerText . '
</body>
</html>';
    }

    protected static function logoDataUri(): ?string
    {
        $path = public_path('images/UHS_logo.png');

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : 'data:image/png;base64,' . base64_encode($contents);
    }

}
