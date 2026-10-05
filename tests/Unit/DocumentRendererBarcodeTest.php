<?php

namespace Tests\Unit;

use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use ReflectionMethod;
use Tests\TestCase;

class DocumentRendererBarcodeTest extends TestCase
{
    public function test_multiple_plain_fields_are_encoded_as_one_combined_value(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode first_name|last_name|phone type=C128 width=2 height=30}}',
            [
                'first_name' => 'jyrgefd',
                'last_name' => ' hgrtfrd ',
                'phone' => '012 345',
            ]
        );

        $generateBarcode = new ReflectionMethod($renderer, 'generateBarcodeTag');
        $generateBarcode->setAccessible(true);
        $expected = $generateBarcode->invoke($renderer, 'jyrgefd hgrtfrd 012 345', 'C128', 2, 30);

        $this->assertSame($expected, $actual);
    }

    public function test_multiple_nested_fields_are_encoded_as_one_combined_value(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode data.first_name|data.last_name|data.phone type=C128 width=2 height=30}}',
            ['data' => [
                'first_name' => 'jyrgefd',
                'last_name' => 'hgrtfrd',
                'phone' => '012345',
            ]]
        );

        $generateBarcode = new ReflectionMethod($renderer, 'generateBarcodeTag');
        $generateBarcode->setAccessible(true);
        $expected = $generateBarcode->invoke($renderer, 'jyrgefd hgrtfrd 012345', 'C128', 2, 30);

        $this->assertSame($expected, $actual);
    }

    public function test_adjacent_barcode_tags_are_combined_without_field_specific_code(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode data.father_name type=C128 width=2 height=30}}{{#barcode data.mother_name type=C128 width=2 height=30}}',
            ['data' => [
                'father_name' => 'gfdseg12',
                'mother_name' => 'srey mom',
            ]]
        );

        $generateBarcode = new ReflectionMethod($renderer, 'generateBarcodeTag');
        $generateBarcode->setAccessible(true);
        $expected = $generateBarcode->invoke($renderer, 'gfdseg12 srey mom', 'C128', 2, 30);

        $this->assertSame($expected, $actual);
    }

    public function test_adjacent_barcodes_with_different_settings_remain_separate(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode data.father_name type=C128 width=2 height=30}}{{#barcode data.mother_name type=C128 width=2 height=40}}',
            ['data' => [
                'father_name' => 'gfdseg12',
                'mother_name' => 'srey mom',
            ]]
        );

        $this->assertSame(2, substr_count($actual, '<img '));
    }

    public function test_empty_combined_fields_do_not_render_an_image_or_extra_spaces(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode first_name|last_name|phone type=C128 width=2 height=30}}',
            [
                'first_name' => 'jyrgefd',
                'last_name' => ' ',
                'phone' => null,
            ]
        );

        $generateBarcode = new ReflectionMethod($renderer, 'generateBarcodeTag');
        $generateBarcode->setAccessible(true);
        $expected = $generateBarcode->invoke($renderer, 'jyrgefd', 'C128', 2, 30);

        $this->assertSame($expected, $actual);
    }

    public function test_single_field_barcode_syntax_still_renders(): void
    {
        $renderer = new DocumentRenderer;
        $replaceBarcodes = new ReflectionMethod($renderer, 'replaceBarcodes');
        $replaceBarcodes->setAccessible(true);

        $actual = $replaceBarcodes->invoke(
            $renderer,
            '{{#barcode data.student_id type=C128 width=2 height=30}}',
            ['data' => ['student_id' => 'student-001']]
        );

        $this->assertStringStartsWith('<img src="data:image/png;base64,', $actual);
    }
}
