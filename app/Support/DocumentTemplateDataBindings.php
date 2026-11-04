<?php

namespace App\Support;

final class DocumentTemplateDataBindings
{
    private const EDUCATION_HEADERS = [
        'គ្រឹះស្ថានសិក្សានិងសាលា',
        'កម្រិតសញ្ញាបត្រ និង ជំនាញ',
        'ឆ្នាំសិក្សា ដល់ឆ្នាំណា',
        'ប្រទេស',
        'ថ្នាក់បញ្ចប់ការសិក្សា ឬ ទទួលបានសញ្ញាបត្រ',
    ];

    private const WORK_HEADERS = [
        'ឆ្នាំចូលបំពេញការងារ',
        'ថ្នាក់ប្រគល់ការងារ',
        'អង្គភាព/ស្ថាប័ន',
        'ក្រសួង',
        'តួនាទី',
    ];

    /** @param array<string> $fieldNames */
    public static function upgrade(string $content, array $fieldNames): string
    {
        $fields = array_fill_keys(array_values(array_filter($fieldNames, 'is_string')), true);
        $content = self::upgradeScalarPlaceholders($content, $fields);
        $content = self::upgradeSpouseParagraph($content, $fields);
        $content = self::upgradeSiblingParagraph($content, $fields);

        return preg_replace_callback('~<table\b[^>]*>.*?</table\s*>~is', function (array $match) use ($fields): string {
            return self::upgradeBlankTable($match[0], $fields);
        }, $content) ?? $content;
    }

    private static function upgradeScalarPlaceholders(string $content, array $fields): string
    {
        $spouseName = isset($fields['spouse_name']) ? 'spouse_name' : 'spouse_children_heading';
        $spouseBirth = isset($fields['spouse_year_of_birth']) ? 'spouse_year_of_birth' : 'spouse_date_of_birth';
        $aliases = [
            'husbandorwifename' => $spouseName,
            'husbandorwifedateofbirth' => $spouseBirth,
            'spousename' => $spouseName,
            'spousechildrenheading' => $spouseName,
            'spouseyearofbirth' => $spouseBirth,
            'spousedateofbirth' => $spouseBirth,
            'numberofchildren' => 'number_of_children',
            'numberofsons' => 'number_of_sons',
            'numberofdaughters' => 'number_of_daughters',
        ];

        return preg_replace_callback('/{{(.*?)}}/s', function (array $match) use ($fields, $aliases): string {
            $key = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $prefix = str_starts_with($key, 'data.') ? 'data.' : '';
            $name = $prefix !== '' ? substr($key, 5) : $key;
            $alias = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '');
            $target = $aliases[$alias] ?? null;

            return $target !== null && isset($fields[$target]) && $key !== $prefix.$target
                ? '{{ '.$prefix.$target.' }}'
                : $match[0];
        }, $content) ?? $content;
    }

    private static function upgradeSpouseParagraph(string $content, array $fields): string
    {
        if (! isset($fields['spouse_occupation'])
            || isset($fields['Husband_or_wife_nationality'])
            || isset($fields['Nationality_of_husband_or_wife'])) {
            return $content;
        }

        return preg_replace_callback('~<p\b[^>]*>.*?</p\s*>~is', function (array $match): string {
            $paragraph = $match[0];

            if (! str_contains(self::text($paragraph), 'ប្រពន្ធ')
                || preg_match('/{{\s*(?:data\.)?spouse_(?:name|children_heading)\s*}}/i', $paragraph) !== 1) {
                return $paragraph;
            }

            $space = '(?:\s|&nbsp;|&#160;|\x{00A0})*';
            $nationality = 'ជនជាតិ'.$space.'{{\s*(?:data\.)?Husband_or_wife_nationality\s*}}'
                .$space.'សញ្ជាតិ'.$space.'{{\s*(?:data\.)?Nationality_of_husband_or_wife\s*}}';

            return preg_replace('/'.$nationality.'/iu', 'មុខរបរ {{ data.spouse_occupation }}', $paragraph) ?? $paragraph;
        }, $content) ?? $content;
    }

    private static function upgradeSiblingParagraph(string $content, array $fields): string
    {
        if (! self::hasFields($fields, ['siblings', 'sibling_name', 'sibling_gender', 'sibling_year_of_birth', 'sibling_occupation'])) {
            return $content;
        }

        return preg_replace_callback('~<p\b[^>]*>.*?</p\s*>~is', function (array $match): string {
            $paragraph = $match[0];

            if (! str_contains(self::text($paragraph), 'អំពីបងប្អូនបង្កើត')
                || str_contains($paragraph, '{{')
                || preg_match('~<br\b[^>]*>~i', $paragraph, $break, PREG_OFFSET_CAPTURE) !== 1) {
                return $paragraph;
            }

            $afterBreak = substr($paragraph, $break[0][1] + strlen($break[0][0]));
            if (preg_match('~((?:\s*</(?:span|strong|em|b|i|u)>)*\s*</p>\s*)$~i', $afterBreak, $ending, PREG_OFFSET_CAPTURE) !== 1) {
                return $paragraph;
            }

            $blankLine = substr($afterBreak, 0, $ending[0][1]);
            if (str_contains($blankLine, '<')
                || preg_match('/^[១1]\s*[-.]?\s*ឈ្មោះ\s*[:៖]?\s*[.\s\x{2026}]*ភេទ[.\s\x{2026}]*ឆ្នាំកំណើត[.\s\x{2026}]*មុខរបរ[.\s\x{2026}]*$/u', self::text($blankLine)) !== 1) {
                return $paragraph;
            }

            $heading = substr($paragraph, 0, $break[0][1]);
            $row = $break[0][0].'- ឈ្មោះ៖ {{ sibling.sibling_name }} ភេទ {{ sibling.sibling_gender }}'
                .' ឆ្នាំកំណើត {{ sibling.sibling_year_of_birth }} មុខរបរ {{ sibling.sibling_occupation }}';

            return $heading."\n<!-- {{#foreach data.siblings as sibling}} -->\n"
                .$row."\n<!-- {{/foreach}} -->\n".$ending[0][0];
        }, $content) ?? $content;
    }

    private static function upgradeBlankTable(string $table, array $fields): string
    {
        if (preg_match_all('~<table\b~i', $table) !== 1 || str_contains($table, '{{')) {
            return $table;
        }

        preg_match_all('~<tr\b[^>]*>.*?</tr\s*>~is', $table, $rows, PREG_OFFSET_CAPTURE);
        if (count($rows[0]) < 2) {
            return $table;
        }

        $headerCells = self::cells($rows[0][0][0]);
        $headers = array_map(fn (array $cell): string => self::text($cell[2]), $headerCells);

        if ($headers === self::EDUCATION_HEADERS
            && self::hasFields($fields, ['educations', 'educational_institution', 'degree_level_major', 'from_year', 'to_year', 'country', 'graduation_year'])) {
            $repeater = 'educations';
            $alias = 'education';
            $values = [
                '{{ education.educational_institution }}', '{{ education.degree_level_major }}',
                '{{ education.from_year }} - {{ education.to_year }}', '{{ education.country }}', '{{ education.graduation_year }}',
            ];
        } elseif ($headers === self::WORK_HEADERS
            && self::hasFields($fields, ['cv_work_history', 'cv_start_year', 'cv_end_year', 'cv_organization', 'cv_ministry', 'cv_position'])) {
            $repeater = 'cv_work_history';
            $alias = 'work';
            $values = [
                '{{ work.cv_start_year }}', '{{ work.cv_end_year }}', '{{ work.cv_organization }}',
                '{{ work.cv_ministry }}', '{{ work.cv_position }}',
            ];
        } else {
            return $table;
        }

        foreach (array_slice($rows[0], 1) as [$row]) {
            $cells = self::cells($row);
            if (count($cells) !== 5) {
                return $table;
            }

            foreach ($cells as $cell) {
                if (strtolower($cell[1]) !== 'td' || self::text($cell[2]) !== ''
                    || preg_match('~<(?:img|svg|input|textarea|select|iframe|object|embed|audio|video|canvas|table|hr|script)\b~i', $cell[2]) === 1) {
                    return $table;
                }
            }
        }

        $firstBodyRow = $rows[0][1];
        $lastBodyRow = $rows[0][count($rows[0]) - 1];
        $bodyLength = $lastBodyRow[1] + strlen($lastBodyRow[0]) - $firstBodyRow[1];
        if (preg_match('~</?(?:tbody|thead|tfoot)\b~i', substr($table, $firstBodyRow[1], $bodyLength)) === 1) {
            return $table;
        }

        $cellIndex = 0;
        $row = preg_replace_callback('~(<td\b[^>]*>)(.*?)(</td\s*>)~is', function (array $cell) use (&$cellIndex, $values): string {
            return $cell[1].self::fillBlankCell($cell[2], $values[$cellIndex++]).$cell[3];
        }, $firstBodyRow[0]);
        $loop = '<!-- {{#foreach data.'.$repeater.' as '.$alias."}} -->\n".$row."\n<!-- {{/foreach}} -->";

        return substr_replace($table, $loop, $firstBodyRow[1], $bodyLength);
    }

    /** @return array<array{string, string, string}> */
    private static function cells(string $row): array
    {
        preg_match_all('~<(td|th)\b[^>]*>(.*?)</\1\s*>~is', $row, $cells, PREG_SET_ORDER);

        return $cells;
    }

    private static function fillBlankCell(string $content, string $placeholder): string
    {
        $content = preg_replace('~<br\b[^>]*>~i', '', $content) ?? $content;
        $parts = preg_split('~(<[^>]*>)~s', $content, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $markup = implode('', array_filter($parts, fn (string $part): bool => str_starts_with($part, '<')));
        $fontSize = preg_match('/font-size\s*:/i', $markup) === 1 ? '' : ' font-size: 8pt;';
        $placeholder = '<span style="font-family: Battambang, sans-serif;'.$fontSize.'">'.$placeholder.'</span>';
        $closing = strpos($markup, '</');

        return $closing === false ? $markup.$placeholder : substr_replace($markup, $placeholder, $closing, 0);
    }

    private static function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? $text;

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    private static function hasFields(array $fields, array $required): bool
    {
        foreach ($required as $name) {
            if (! isset($fields[$name])) {
                return false;
            }
        }

        return true;
    }
}
