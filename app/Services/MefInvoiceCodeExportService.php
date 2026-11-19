<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

final class MefInvoiceCodeExportService
{
    private const WORKSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function download(iterable $records): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'mef-invoice-codes-');
        if ($path === false) {
            throw new RuntimeException('Could not create the MEF invoice codes export.');
        }

        $opened = false;

        try {
            $zip = new ZipArchive;
            $template = resource_path('templates/mef-invoice-codes-template.xlsx');
            if (! is_readable($template) || ! copy($template, $path)) {
                throw new RuntimeException('Could not read the MEF invoice codes template.');
            }
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Could not open the MEF invoice codes export.');
            }
            $opened = true;

            $worksheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($worksheet === false || ! $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($worksheet, $records))) {
                throw new RuntimeException('Could not populate the MEF invoice codes export.');
            }
            if (! $zip->close()) {
                throw new RuntimeException('Could not save the MEF invoice codes export.');
            }
            $opened = false;
            clearstatcache(true, $path);

            return response()->download($path, 'mef-invoice-codes-template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if ($opened) {
                try {
                    $zip->close();
                } catch (Throwable) {
                    // Preserve the original export failure while still removing its temporary file.
                }
            }
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }
    }

    private function worksheet(string $xml, iterable $records): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('The MEF invoice codes worksheet is invalid.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', self::WORKSHEET_NAMESPACE);
        $sheetData = $xpath->query('/x:worksheet/x:sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            throw new RuntimeException('The MEF invoice codes worksheet has no data section.');
        }

        $styles = [];
        foreach (['A' => 1, 'B' => 2] as $column => $index) {
            $definition = $xpath->query('/x:worksheet/x:cols/x:col[@min <= '.$index.' and @max >= '.$index.']')->item(0);
            $dataCell = $xpath->query('/x:worksheet/x:sheetData/x:row[@r="2"]/x:c[@r="'.$column.'2"]')->item(0);
            $styles[$column] = $dataCell instanceof DOMElement && $dataCell->hasAttribute('s')
                ? $dataCell->getAttribute('s')
                : ($definition instanceof DOMElement ? $definition->getAttribute('style') : '');
        }
        foreach (iterator_to_array($sheetData->childNodes) as $row) {
            if ($row instanceof DOMElement && $row->localName === 'row' && $row->getAttribute('r') !== '1') {
                $sheetData->removeChild($row);
            }
        }

        $number = 2;
        foreach ($records as $record) {
            $row = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'row');
            $row->setAttribute('r', (string) $number);
            foreach (['A' => 'mef_code', 'B' => 'invoice_code'] as $column => $field) {
                $cell = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'c');
                $cell->setAttribute('r', $column.$number);
                $cell->setAttribute('t', 'inlineStr');
                if ($styles[$column] !== '') {
                    $cell->setAttribute('s', $styles[$column]);
                }
                $inlineString = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'is');
                $text = $document->createElementNS(self::WORKSHEET_NAMESPACE, 't');
                $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                $text->appendChild($document->createTextNode((string) data_get($record, $field, '')));
                $inlineString->appendChild($text);
                $cell->appendChild($inlineString);
                $row->appendChild($cell);
            }
            $sheetData->appendChild($row);
            $number++;
        }

        $dimension = $xpath->query('/x:worksheet/x:dimension')->item(0);
        if (! $dimension instanceof DOMElement) {
            $dimension = $document->createElementNS(self::WORKSHEET_NAMESPACE, 'dimension');
            $root = $document->documentElement;
            $sheetProperties = $xpath->query('/x:worksheet/x:sheetPr')->item(0);
            $root->insertBefore($dimension, $sheetProperties !== null ? $sheetProperties->nextSibling : $root->firstChild);
        }
        $dimension->setAttribute('ref', 'A1:B'.($number - 1));

        $result = $document->saveXML();
        if ($result === false) {
            throw new RuntimeException('Could not serialize the MEF invoice codes worksheet.');
        }

        return $result;
    }
}
