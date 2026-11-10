<?php

namespace App\Support;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class SpreadsheetRowReader
{
    public const AUTO_WORKSHEET = '__auto__';

    private const MAX_ROWS = 10001;

    private const MAX_XML_BYTES = 16777216;

    /**
     * Read the explicitly chosen visible worksheet, or the first visible sheet
     * by default. AUTO_WORKSHEET prefers the visible Database Export sheet.
     *
     * @return array<int, array<int, string>> Original 1-based rows, sparse 0-based columns.
     */
    public function read(UploadedFile $file, ?string $worksheetName = null): array
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            $this->fail('cannot_read');
        }

        return match (strtolower($file->getClientOriginalExtension())) {
            'xlsx' => $this->xlsxRows($path, $worksheetName),
            'csv' => $this->csvRows($path),
            default => $this->fail('file_type'),
        };
    }

    /** @return array<int, array<int, string>> */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            $this->fail('cannot_read');
        }

        $rows = [];
        $rowNumber = 0;
        try {
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                $rowNumber++;
                $values = array_map(static fn ($value): string => trim((string) $value), $row);
                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]) ?? $values[0];
                }
                foreach ($values as $value) {
                    if (str_starts_with($value, '=')) {
                        $this->fail('formula', ['row' => $rowNumber]);
                    }
                }
                $this->appendRow($rows, $rowNumber, $values);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function xlsxRows(string $path, ?string $worksheetName): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->fail('invalid_excel');
        }

        try {
            $workbook = $this->xml($this->zipXml($zip, 'xl/workbook.xml'));
            $relationships = $this->xml($this->zipXml($zip, 'xl/_rels/workbook.xml.rels'));
            $worksheetPath = $this->firstVisibleWorksheetPath($workbook, $relationships, $worksheetName);
            $worksheet = $this->xml($this->zipXml($zip, $worksheetPath));
            $sharedStrings = $this->sharedStrings($this->zipXml($zip, 'xl/sharedStrings.xml', required: false));
            $dateStyles = $this->dateStyles($this->zipXml($zip, 'xl/styles.xml', required: false));
            $properties = (new DOMXPath($workbook))->query('//*[local-name()="workbookPr"]')->item(0);
            $date1904 = $properties instanceof DOMElement
                && in_array(strtolower($properties->getAttribute('date1904')), ['1', 'true'], true);

            return $this->worksheetRows($worksheet, $sharedStrings, $dateStyles, $date1904);
        } finally {
            $zip->close();
        }
    }

    private function firstVisibleWorksheetPath(DOMDocument $workbook, DOMDocument $relationships, ?string $worksheetName): string
    {
        if ($worksheetName === self::AUTO_WORKSHEET) {
            $worksheetName = null;
            foreach ((new DOMXPath($workbook))->query('//*[local-name()="sheet"]') as $sheet) {
                if ($sheet instanceof DOMElement && $sheet->getAttribute('name') === 'Database Export'
                    && ! in_array($sheet->getAttribute('state'), ['hidden', 'veryHidden'], true)) {
                    $worksheetName = 'Database Export';
                    break;
                }
            }
        }

        $relationshipId = null;
        foreach ((new DOMXPath($workbook))->query('//*[local-name()="sheet"]') as $sheet) {
            if (! $sheet instanceof DOMElement || in_array($sheet->getAttribute('state'), ['hidden', 'veryHidden'], true)) {
                continue;
            }
            if ($worksheetName !== null && $sheet->getAttribute('name') !== $worksheetName) {
                continue;
            }
            foreach ($sheet->attributes as $attribute) {
                if ($attribute->localName === 'id') {
                    $relationshipId = $attribute->value;
                    break 2;
                }
            }
            $this->fail('invalid_excel');
        }
        if ($relationshipId === null) {
            if ($worksheetName !== null) {
                $this->fail('worksheet_missing', ['sheet' => $worksheetName]);
            }
            $this->fail('invalid_excel');
        }

        foreach ((new DOMXPath($relationships))->query('//*[local-name()="Relationship"]') as $relationship) {
            if (! $relationship instanceof DOMElement || $relationship->getAttribute('Id') !== $relationshipId) {
                continue;
            }
            if (strtolower($relationship->getAttribute('TargetMode')) === 'external') {
                $this->fail('invalid_excel');
            }

            $target = str_replace('\\', '/', $relationship->getAttribute('Target'));
            $segments = [];
            foreach (explode('/', str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target) as $segment) {
                if ($segment === '..') {
                    if ($segments === []) {
                        $this->fail('invalid_excel');
                    }
                    array_pop($segments);
                } elseif ($segment !== '' && $segment !== '.') {
                    $segments[] = $segment;
                }
            }

            return implode('/', $segments);
        }

        $this->fail('invalid_excel');
    }

    private function zipXml(ZipArchive $zip, string $name, bool $required = true): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            if ($required) {
                $this->fail('invalid_excel');
            }

            return null;
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            $this->fail('invalid_excel');
        }
        $value = $zip->getFromName($name);
        if ($value === false) {
            $this->fail('invalid_excel');
        }

        return $value;
    }

    private function xml(string $value): DOMDocument
    {
        if (stripos($value, '<!DOCTYPE') !== false || stripos($value, '<!ENTITY') !== false) {
            $this->fail('invalid_excel');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($value, LIBXML_NONET | LIBXML_COMPACT)) {
                $this->fail('invalid_excel');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    /** @return list<string> */
    private function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $document = $this->xml($xml);
        $xpath = new DOMXPath($document);
        $strings = [];
        foreach ($xpath->query('//*[local-name()="si"]') as $item) {
            $text = '';
            foreach ($xpath->query('.//*[local-name()="t" and not(ancestor::*[local-name()="rPh"])]', $item) as $part) {
                $text .= $part->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /** @return array<int, bool> */
    private function dateStyles(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }
        $xpath = new DOMXPath($this->xml($xml));
        $formats = [];
        foreach ($xpath->query('//*[local-name()="numFmt"]') as $format) {
            $formats[(int) $format->getAttribute('numFmtId')] = $format->getAttribute('formatCode');
        }
        $styles = [];
        foreach ($xpath->query('//*[local-name()="cellXfs"]/*[local-name()="xf"]') as $index => $style) {
            $id = (int) $style->getAttribute('numFmtId');
            $code = preg_replace('/"[^"]*"|\\\\.|\[[^\]]*\]/', '', $formats[$id] ?? '') ?? '';
            $styles[$index] = in_array($id, [14, 15, 16, 17, 22, ...range(27, 36), ...range(50, 58)], true)
                || preg_match('/[yd]/i', $code) === 1
                || (preg_match('/m/i', $code) === 1 && preg_match('/[hs]/i', $code) !== 1);
        }

        return $styles;
    }

    /** @return array<int, array<int, string>> */
    private function worksheetRows(DOMDocument $document, array $strings, array $dateStyles, bool $date1904): array
    {
        $xpath = new DOMXPath($document);
        $rows = [];
        $rowNumber = 0;
        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
            $rowNumber = $row->hasAttribute('r') ? (int) $row->getAttribute('r') : $rowNumber + 1;
            if ($rowNumber < 1 || $rowNumber > 1048576 || isset($rows[$rowNumber])) {
                $this->fail('invalid_excel');
            }
            $values = [];
            $column = -1;
            foreach ($xpath->query('./*[local-name()="c"]', $row) as $cell) {
                $reference = $cell->getAttribute('r');
                if ($reference !== '') {
                    if (preg_match('/^([A-Z]+)([1-9][0-9]*)$/i', $reference, $matches) !== 1 || (int) $matches[2] !== $rowNumber) {
                        $this->fail('invalid_excel');
                    }
                    $column = $this->columnIndex($matches[1]);
                } else {
                    $column++;
                }
                if ($column > 255 || isset($values[$column])) {
                    $this->fail('invalid_excel');
                }
                if ($xpath->query('./*[local-name()="f"]', $cell)->length > 0) {
                    $this->fail('formula', ['row' => $rowNumber]);
                }
                $type = $cell->getAttribute('t');
                $value = '';
                if ($type === 'inlineStr') {
                    foreach ($xpath->query('.//*[local-name()="t" and not(ancestor::*[local-name()="rPh"])]', $cell) as $text) {
                        $value .= $text->textContent;
                    }
                } else {
                    $value = $xpath->query('./*[local-name()="v"]', $cell)->item(0)?->textContent ?? '';
                    if ($type === 's') {
                        if (! ctype_digit($value) || ! array_key_exists((int) $value, $strings)) {
                            $this->fail('invalid_excel');
                        }
                        $value = $strings[(int) $value];
                    } elseif ($type === 'e') {
                        $this->fail('invalid_excel');
                    } elseif (($type === '' || $type === 'n') && ($dateStyles[(int) $cell->getAttribute('s')] ?? false) && is_numeric($value)) {
                        $value = $this->excelDate((float) $value, $date1904);
                    }
                }
                $values[$column] = trim($value);
            }
            ksort($values);
            $this->appendRow($rows, $rowNumber, $values);
        }
        ksort($rows);

        return $rows;
    }

    private function excelDate(float $serial, bool $date1904): string
    {
        if (! is_finite($serial) || $serial < 0 || $serial > 2958465) {
            $this->fail('invalid_excel');
        }
        $seconds = (int) round($serial * 86400);
        if (! $date1904 && $serial >= 60) {
            $seconds -= 86400;
        }
        $date = (new DateTimeImmutable($date1904 ? '1904-01-01 00:00:00 UTC' : '1899-12-31 00:00:00 UTC'))
            ->modify('+'.$seconds.' seconds');

        return $date->format($seconds % 86400 === 0 ? 'Y-m-d' : 'Y-m-d H:i:s');
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $letter) {
            $index = ($index * 26) + ord($letter) - ord('A') + 1;
        }

        return $index - 1;
    }

    private function appendRow(array &$rows, int $number, array $values): void
    {
        if (array_filter($values, static fn (string $value): bool => $value !== '') === []) {
            return;
        }
        if (count($rows) >= self::MAX_ROWS) {
            $this->fail('too_many_rows');
        }
        $rows[$number] = $values;
    }

    private function fail(string $reason, array $parameters = []): never
    {
        throw ValidationException::withMessages([
            'file' => __('candidate_statistics_import.errors.'.$reason, $parameters),
        ]);
    }
}
