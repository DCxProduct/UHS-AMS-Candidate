<?php

namespace Chanthoeun\FilamentDocumentBuilder\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class PdfTableLayout
{
    /** mPDF reads cell heights, while the editor also sizes tables and rows. */
    public static function preserveCellHeights(string $html): string
    {
        if (! preg_match('/<(?:table|tr)\b[^>]*\bheight\s*[:=]/i', $html)) {
            return $html;
        }

        // Keep the original HTML intact outside the cell opening tags we change.
        preg_match_all('/<!--.*?-->|<(?:td|th)(?=[\s\/>])(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/is', $html, $matches, PREG_OFFSET_CAPTURE);
        $tags = array_values(array_filter($matches[0], fn (array $match): bool => ! str_starts_with($match[0], '<!--')));
        if ($tags === []) {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET)) {
                return $html;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $cells = $xpath->query('//td | //th');
        if ($cells->length !== count($tags)) {
            return $html;
        }

        $tables = [];
        $replacements = [];
        foreach ($cells as $index => $cell) {
            $row = $cell->parentNode;
            if (! $row instanceof DOMElement || $row->tagName !== 'tr'
                || (int) $cell->getAttribute('rowspan') > 1
                || $cell->hasAttribute('height')
                || self::cssValue($cell, 'height') !== null) {
                continue;
            }

            $table = $row->parentNode;
            while ($table instanceof DOMElement && $table->tagName !== 'table') {
                $table = $table->parentNode;
            }
            if (! $table instanceof DOMElement) {
                continue;
            }

            $path = $table->getNodePath();
            if (! isset($tables[$path])) {
                $ownRows = $xpath->query('./tr | ./thead/tr | ./tbody/tr | ./tfoot/tr', $table);
                $singleRow = $ownRows->length === 1;
                $tables[$path] = [
                    'single_row' => $singleRow,
                    'box' => $singleRow && $xpath->query('./td | ./th', $ownRows->item(0))->length === 1 && self::hasBorder($table),
                    'height' => self::height($table),
                ];
            }
            $layout = $tables[$path];
            $height = self::height($row) ?? ($layout['single_row'] ? $layout['height'] : null);
            if ($height === null) {
                continue;
            }

            $declarations = 'height: '.$height.';';
            if ($layout['box'] && ! $cell->hasAttribute('valign') && self::cssValue($cell, 'vertical-align') === null) {
                $declarations .= ' vertical-align: middle;';
            }
            [$tag, $offset] = $tags[$index];
            $replacements[] = [$offset, strlen($tag), self::appendStyle($tag, $declarations)];
        }

        foreach (array_reverse($replacements) as [$offset, $length, $replacement]) {
            $html = substr_replace($html, $replacement, $offset, $length);
        }

        return $html;
    }

    private static function cssValue(DOMElement $element, string $property): ?string
    {
        preg_match_all('/(?:^|;)\s*'.preg_quote($property, '/').'\s*:\s*([^;]*)/i', $element->getAttribute('style'), $matches);
        $value = null;
        $important = false;
        foreach ($matches[1] as $candidate) {
            $candidateIsImportant = preg_match('/!important\s*$/i', $candidate) === 1;
            if (! $important || $candidateIsImportant) {
                $value = trim($candidate);
                $important = $candidateIsImportant;
            }
        }

        return $value;
    }

    private static function height(DOMElement $element): ?string
    {
        $value = self::cssValue($element, 'height');
        if ($value !== null && preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|pt|pc|mm|cm|in)(?:\s*!important)?$/i', $value, $matches)
            && (float) $matches[1] > 0) {
            return $matches[1].strtolower($matches[2]);
        }

        $value = trim($element->getAttribute('height'));
        if (preg_match('/^(\d+(?:\.\d+)?|\.\d+)(px|pt|pc|mm|cm|in)?$/i', $value, $matches) && (float) $matches[1] > 0) {
            return $matches[1].strtolower($matches[2] ?? 'px');
        }

        return null;
    }

    private static function hasBorder(DOMElement $table): bool
    {
        if ((float) $table->getAttribute('border') > 0) {
            return true;
        }

        foreach (['border', 'border-width'] as $property) {
            $value = self::cssValue($table, $property);
            if ($value !== null && preg_match('/^(?:(\d+(?:\.\d+)?|\.\d+)(?:[a-z]+)?|thin|medium|thick)(?:\s|$)/i', $value, $matches)
                && (! isset($matches[1]) || $matches[1] === '' || (float) $matches[1] > 0)) {
                return true;
            }
        }

        return false;
    }

    private static function appendStyle(string $tag, string $declarations): string
    {
        // Ignore quoted values of other attributes, which may themselves mention "style=".
        $pattern = '/(?:"[^"]*"|\'[^\']*\')(*SKIP)(*F)|(\sstyle\s*=\s*)(?:(["\'])(.*?)\2|([^\s>]+))/is';
        if (preg_match($pattern, $tag)) {
            return preg_replace_callback($pattern, function (array $matches) use ($declarations): string {
                $quote = $matches[2] !== '' ? $matches[2] : '"';
                $style = rtrim($matches[2] !== '' ? $matches[3] : $matches[4]);
                $separator = $style === '' ? '' : (str_ends_with($style, ';') ? ' ' : '; ');

                return $matches[1].$quote.$style.$separator.$declarations.$quote;
            }, $tag, 1);
        }

        $offset = strrpos($tag, '>');
        if ($offset > 0 && $tag[$offset - 1] === '/') {
            $offset--;
        }

        return substr_replace($tag, ' style="'.$declarations.'"', $offset, 0);
    }
}
