<?php

namespace App\Support;

use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class TablePdfExporter
{
    public static function download(
        string $filenamePrefix,
        array $headings,
        iterable $rows,
        ?string $title = null,
    ) {
        $html = self::html($headings, $rows, $title);
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
                'calibri' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
            ],
        ]);

        $filename = $filenamePrefix . now()->format('Y-m-d-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected static function html(array $headings, iterable $rows, ?string $title): string
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
    </style>
</head>
<body>
    ' . $titleHtml . '
    <table>
        <thead><tr>' . $headingHtml . '</tr></thead>
        <tbody>' . $rowHtml . '</tbody>
    </table>
</body>
</html>';
    }
}
