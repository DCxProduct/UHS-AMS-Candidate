<?php

namespace App\Services;

use App\Models\MefInvoiceCode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class MefInvoiceCodeImportService
{
    /** @return array{created: int, existing: int} */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = match ($extension) {
            'xlsx' => $this->xlsxRows($file->getRealPath()),
            'csv' => $this->csvRows($file->getRealPath()),
            default => throw ValidationException::withMessages([
                'file' => __('mef_invoice_codes.import.errors.file_type'),
            ]),
        };

        $records = $this->recordsFromRows($rows);

        if ($records === []) {
            throw ValidationException::withMessages([
                'file' => __('mef_invoice_codes.import.errors.no_rows'),
            ]);
        }

        return DB::transaction(function () use ($records): array {
            $created = 0;
            $existing = 0;

            foreach ($records as $record) {
                $model = MefInvoiceCode::query()->firstOrCreate($record);
                $model->wasRecentlyCreated ? $created++ : $existing++;
            }

            return compact('created', 'existing');
        });
    }

    /** @return list<array<int, string>> */
    private function csvRows(string|false $path): array
    {
        if (! $path || ! is_readable($path)) {
            throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.cannot_read')]);
        }

        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.cannot_read')]);
        }

        $rows = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]) ?? (string) $row[0];
                }

                $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /** @return list<array<int, string>> */
    private function xlsxRows(string|false $path): array
    {
        if (! $path || ! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.cannot_read')]);
        }

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.invalid_excel')]);
        }

        try {
            $workbook = $zip->getFromName('xl/workbook.xml');
            $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');

            if ($workbook === false || $relationships === false) {
                throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.invalid_excel')]);
            }

            $worksheetPath = $this->firstWorksheetPath($workbook, $relationships);
            $worksheet = $zip->getFromName($worksheetPath);

            if ($worksheet === false) {
                throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.invalid_excel')]);
            }

            $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
            $sharedStrings = $sharedStringsXml === false ? [] : $this->sharedStrings($sharedStringsXml);

            return $this->worksheetRows($worksheet, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    private function firstWorksheetPath(string $workbook, string $relationships): string
    {
        $relationshipId = null;

        foreach ($this->xmlStructs($workbook) as $node) {
            if (strtolower($node['tag']) !== 'sheet' || ! in_array($node['type'], ['open', 'complete'], true)) {
                continue;
            }

            foreach ($node['attributes'] ?? [] as $key => $value) {
                if (strtolower((string) $key) === 'id') {
                    $relationshipId = (string) $value;
                    break 2;
                }
            }
        }

        if ($relationshipId === null) {
            throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.invalid_excel')]);
        }

        foreach ($this->xmlStructs($relationships) as $node) {
            if (strtolower($node['tag']) !== 'relationship') {
                continue;
            }

            $attributes = array_change_key_case($node['attributes'] ?? [], CASE_LOWER);

            if (($attributes['id'] ?? null) !== $relationshipId || ! isset($attributes['target'])) {
                continue;
            }

            $target = str_replace('\\', '/', (string) $attributes['target']);

            if (str_starts_with($target, '/')) {
                return ltrim($target, '/');
            }

            return 'xl/'.ltrim($target, '/');
        }

        throw ValidationException::withMessages(['file' => __('mef_invoice_codes.import.errors.invalid_excel')]);
    }

    /** @return list<string> */
    private function sharedStrings(string $xml): array
    {
        $strings = [];
        $insideItem = false;
        $insideText = false;
        $current = '';

        foreach ($this->xmlStructs($xml) as $node) {
            $tag = strtolower($node['tag']);

            if ($tag === 'si' && $node['type'] === 'open') {
                $insideItem = true;
                $current = '';
            } elseif ($tag === 'si' && $node['type'] === 'close') {
                $strings[] = $current;
                $insideItem = false;
            } elseif ($insideItem && $tag === 't' && $node['type'] === 'open') {
                $insideText = true;
            } elseif ($insideItem && $tag === 't' && $node['type'] === 'close') {
                $insideText = false;
            } elseif ($insideItem && $tag === 't' && $node['type'] === 'complete') {
                // XML_PARSE_INTO_STRUCT emits plain <t>value</t> elements as
                // complete nodes, so they have no separate open/text/close events.
                $current .= $node['value'] ?? '';
            } elseif ($insideItem && $insideText && $node['type'] === 'cdata') {
                $current .= $node['value'] ?? '';
            } elseif ($insideItem && $insideText && $node['type'] === 'complete') {
                $current .= $node['value'] ?? '';
            } elseif ($insideItem && $insideText && $node['type'] === 'text') {
                $current .= $node['value'] ?? '';
            }
        }

        return $strings;
    }

    /** @param list<string> $sharedStrings
     * @return list<array<int, string>>
     */
    private function worksheetRows(string $xml, array $sharedStrings): array
    {
        $rows = [];
        $rowNumber = 0;
        $cellReference = null;
        $cellType = null;
        $cellValue = '';
        $captureValue = false;
        $currentRow = [];

        foreach ($this->xmlStructs($xml) as $node) {
            $tag = strtolower($node['tag']);

            if ($tag === 'row' && in_array($node['type'], ['open', 'complete'], true)) {
                $attributes = array_change_key_case($node['attributes'] ?? [], CASE_LOWER);
                $rowNumber = (int) ($attributes['r'] ?? ($rowNumber + 1));
                $currentRow = [];
            } elseif ($tag === 'c' && $node['type'] === 'open') {
                $attributes = array_change_key_case($node['attributes'] ?? [], CASE_LOWER);
                $cellReference = (string) ($attributes['r'] ?? '');
                $cellType = $attributes['t'] ?? null;
                $cellValue = '';
            } elseif (in_array($tag, ['v', 't'], true) && in_array($node['type'], ['open', 'complete'], true)) {
                $captureValue = true;
                if ($node['type'] === 'complete') {
                    $cellValue .= $node['value'] ?? '';
                    $captureValue = false;
                }
            } elseif ($captureValue && in_array($node['type'], ['text', 'cdata'], true)) {
                $cellValue .= $node['value'] ?? '';
            } elseif (in_array($tag, ['v', 't'], true) && $node['type'] === 'close') {
                $captureValue = false;
            } elseif ($tag === 'c' && $node['type'] === 'close') {
                if ($cellReference !== null && preg_match('/^([A-Z]+)/i', $cellReference, $matches)) {
                    $column = $this->columnIndex($matches[1]);
                    $currentRow[$column] = $cellType === 's'
                        ? (string) ($sharedStrings[(int) $cellValue] ?? '')
                        : trim($cellValue);
                }

                $cellReference = null;
                $cellType = null;
                $cellValue = '';
            } elseif ($tag === 'row' && $node['type'] === 'close') {
                if ($currentRow !== []) {
                    ksort($currentRow);
                    $rows[$rowNumber] = $currentRow;
                }
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;

        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + ord($letter) - ord('A') + 1;
        }

        return $index - 1;
    }

    /** @return list<array<string, mixed>> */
    private function recordsFromRows(array $rows): array
    {
        $headerRowIndex = null;
        $headerMap = [];

        foreach ($rows as $index => $row) {
            if (array_filter($row, static fn ($value): bool => trim((string) $value) !== '') === []) {
                continue;
            }

            $headerRowIndex = $index;
            foreach ($row as $column => $value) {
                $headerMap[$this->normalizeHeader((string) $value)] = $column;
            }
            break;
        }

        foreach (['mefcode', 'invoicecode'] as $requiredHeader) {
            if (! array_key_exists($requiredHeader, $headerMap)) {
                throw ValidationException::withMessages([
                    'file' => __('mef_invoice_codes.import.errors.headers'),
                ]);
            }
        }

        $records = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue;
            }

            $mefCode = trim((string) ($row[$headerMap['mefcode']] ?? ''));
            $invoiceCode = trim((string) ($row[$headerMap['invoicecode']] ?? ''));

            if ($mefCode === '' && $invoiceCode === '') {
                continue;
            }

            if ($mefCode === '' || $invoiceCode === '') {
                throw ValidationException::withMessages([
                    'file' => __('mef_invoice_codes.import.errors.incomplete_row', ['row' => $index + 1]),
                ]);
            }

            $sanitizedInvoiceCode = MefInvoiceCode::sanitizeInvoiceCode($invoiceCode);

            if ($sanitizedInvoiceCode !== $invoiceCode) {
                throw ValidationException::withMessages([
                    'file' => __('mef_invoice_codes.import.errors.invalid_invoice_code', ['row' => $index + 1]),
                ]);
            }

            $records[] = [
                'mef_code' => $mefCode,
                'invoice_code' => $sanitizedInvoiceCode,
            ];
        }

        return $records;
    }

    private function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', trim($header)) ?? '');
    }

    /** @return list<array{tag: string, type: string, level?: int, value?: string, attributes?: array<string, string>}> */
    private function xmlStructs(string $xml): array
    {
        $parser = xml_parser_create_ns('UTF-8', '|');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, true);

        try {
            if (xml_parse_into_struct($parser, $xml, $values) !== 1) {
                throw ValidationException::withMessages([
                    'file' => __('mef_invoice_codes.import.errors.invalid_excel'),
                ]);
            }

            foreach ($values as &$node) {
                $node['tag'] = $this->xmlLocalName($node['tag']);

                if (isset($node['attributes'])) {
                    $attributes = [];

                    foreach ($node['attributes'] as $name => $value) {
                        $attributes[$this->xmlLocalName($name)] = $value;
                    }

                    $node['attributes'] = $attributes;
                }
            }

            return $values;
        } finally {
            xml_parser_free($parser);
        }
    }

    private function xmlLocalName(string $name): string
    {
        $separator = strrpos($name, '|');

        return $separator === false ? $name : substr($name, $separator + 1);
    }
}
