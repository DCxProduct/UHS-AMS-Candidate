<?php

namespace Tests\Feature;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Chanthoeun\FilamentDocumentBuilder\Support\PdfTableLayout;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class DocumentRendererTableLayoutTest extends TestCase
{
    use RefreshDatabase;

    public static function absoluteHeights(): array
    {
        return [['143.2px'], ['60mm'], ['6cm'], ['2.5in'], ['107.4pt'], ['9pc']];
    }

    #[DataProvider('absoluteHeights')]
    public function test_row_height_is_preserved_on_its_direct_data_and_header_cells_without_unit_conversion(string $height): void
    {
        $source = '<table style="width:16.5071%"><tbody><tr style="height:'.$height.'"><th id="caption">Caption</th><td id="photo" style="width:100%">&nbsp;</td></tr></tbody></table>';
        $actual = $this->xpath(PdfTableLayout::preserveCellHeights($source));

        $this->assertSame($height, $this->css($actual, 'caption', 'height'));
        $this->assertSame($height, $this->css($actual, 'photo', 'height'));
        $this->assertSame('100%', $this->css($actual, 'photo', 'width'));
        $this->assertNull($this->css($actual, 'photo', 'vertical-align'));
    }

    public function test_any_explicit_cell_height_is_preserved_instead_of_being_replaced_by_a_row_height(): void
    {
        foreach (['12px', '6cm', '60mm', '42pt', 'auto', '0', '50%'] as $height) {
            $source = '<table><tr style="height:143.2px"><td style="height:'.$height.';width:20%">Keep</td></tr></table>';
            $this->assertSame($source, PdfTableLayout::preserveCellHeights($source), $height);
        }
        $source = '<table><tr style="height:143.2px"><td height="44" style="width:20%">Keep attribute height</td></tr></table>';
        $this->assertSame($source, PdfTableLayout::preserveCellHeights($source));
    }

    public function test_single_row_table_fallback_and_legacy_row_height_do_not_override_row_priority(): void
    {
        $source = '<table style="height:6cm"><thead><tr><th id="fallback">Caption</th><td id="fallback-data">Box</td></tr></thead></table>'
            .'<table style="height:3in"><tr height="144"><td id="legacy">Legacy</td></tr></table>';
        $actual = $this->xpath(PdfTableLayout::preserveCellHeights($source));

        $this->assertSame('6cm', $this->css($actual, 'fallback', 'height'));
        $this->assertSame('6cm', $this->css($actual, 'fallback-data', 'height'));
        $this->assertSame('144px', $this->css($actual, 'legacy', 'height'));
    }

    public function test_nested_tables_multiple_rows_and_spanning_cells_keep_their_own_layout_boundaries(): void
    {
        $source = '<table style="height:6cm"><tr style="height:30mm"><td id="outer">'
            .'<table style="height:9mm"><tr style="height:5mm"><td id="inner">Inner</td></tr></table>'
            .'<table><tr><td id="inner-free">Unspecified inner height</td></tr></table></td></tr></table>'
            .'<table style="height:120pt"><tbody><tr><td id="multi-free">Do not divide table height</td></tr><tr style="height:24pt"><td id="multi-own">Own row</td></tr></tbody></table>'
            .'<table><tr style="height:10mm"><td id="spanning" rowspan="2">Spans both</td><td id="row-one">One</td></tr><tr style="height:20mm"><td id="row-two">Two</td></tr></table>';
        $actual = $this->xpath(PdfTableLayout::preserveCellHeights($source));

        $this->assertSame('30mm', $this->css($actual, 'outer', 'height'));
        $this->assertSame('5mm', $this->css($actual, 'inner', 'height'));
        $this->assertNull($this->css($actual, 'inner-free', 'height'));
        $this->assertNull($this->css($actual, 'multi-free', 'height'));
        $this->assertSame('24pt', $this->css($actual, 'multi-own', 'height'));
        $this->assertNull($this->css($actual, 'spanning', 'height'));
        $this->assertSame('10mm', $this->css($actual, 'row-one', 'height'));
        $this->assertSame('20mm', $this->css($actual, 'row-two', 'height'));
    }

    public function test_only_single_cell_bordered_layout_boxes_gain_middle_alignment_and_explicit_alignment_wins(): void
    {
        $source = '<table border="1" style="height:6cm"><tr><td id="box">Photo</td></tr></table>'
            .'<table border="1" style="height:6cm"><tr><td id="inline" style="vertical-align:bottom">Bottom</td></tr></table>'
            .'<table border="1" style="height:6cm"><tr><td id="attribute" valign="top">Top</td></tr></table>'
            .'<table style="height:6cm"><tr><td id="plain">Plain</td></tr></table>'
            .'<table border="1"><tr style="height:20px"><td id="many">One</td><td>Two</td></tr></table>';
        $actual = $this->xpath(PdfTableLayout::preserveCellHeights($source));

        $this->assertSame('middle', $this->css($actual, 'box', 'vertical-align'));
        $this->assertSame('bottom', $this->css($actual, 'inline', 'vertical-align'));
        $this->assertNull($this->css($actual, 'attribute', 'vertical-align'));
        $this->assertSame('top', $actual->query('//*[@id="attribute"]')->item(0)->getAttribute('valign'));
        $this->assertNull($this->css($actual, 'plain', 'vertical-align'));
        $this->assertNull($this->css($actual, 'many', 'vertical-align'));
    }

    public function test_unrelated_fragments_and_unsupported_heights_are_unchanged(): void
    {
        $source = '<p class="keep">{{ data.full_name }} &nbsp; <img src="/photo.png" height="60"></p><!-- pagebreak -->';
        $this->assertSame($source, PdfTableLayout::preserveCellHeights($source));
        foreach (['50%', 'auto', '-2cm', '0px', 'calc(20px + 1cm)'] as $height) {
            $source = '<table><tr style="height:'.$height.'"><td style="border:1px solid">Keep &nbsp; {{data.name}}</td></tr></table>';
            $this->assertSame($source, PdfTableLayout::preserveCellHeights($source), $height);
        }
    }

    public function test_photo_box_normalization_is_idempotent_and_preserves_every_byte_outside_cell_opening_tags(): void
    {
        $comment = '<!-- Keep <td style="height:99px"> inside comment unchanged. -->';
        $source = '<!DOCTYPE html>'.$comment.'<p>ខ្មែរ &nbsp; {{ data.full_name }}</p><!-- pagebreak -->'
            .'<TABLE border="1" style="width:16.5071%;height:143.2px"><TBODY><TR style="height:143.2px">'
            .'<TD id="photo" data-note="width > height" style=\'border:1px solid #000;width:100%\'><p>៤x៦</p></TD>'
            .'</TR></TBODY></TABLE><footer>Keep this footer &amp; its spacing. </footer>';
        $actual = PdfTableLayout::preserveCellHeights($source);
        $mask = '~<(?:td|th)\\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>~is';

        $this->assertSame(preg_replace($mask, '<CELL>', $source), preg_replace($mask, '<CELL>', $actual));
        $this->assertStringContainsString($comment, $actual);
        $xpath = $this->xpath($actual);
        $this->assertSame('143.2px', $this->css($xpath, 'photo', 'height'));
        $this->assertSame('middle', $this->css($xpath, 'photo', 'vertical-align'));
        $this->assertSame('width > height', $xpath->query('//*[@id="photo"]')->item(0)->getAttribute('data-note'));
        $this->assertSame($actual, PdfTableLayout::preserveCellHeights($actual));
    }

    public function test_style_like_text_in_a_quoted_title_is_preserved_and_the_real_style_attribute_is_augmented(): void
    {
        $title = "use style='color:red' here";
        foreach (['', ' style="width:100%;color:blue"'] as $style) {
            $source = '<table><tr style="height:143.2px"><td id="title-photo" title="'.$title.'"'.$style.'>Photo</td></tr></table>';
            $actual = PdfTableLayout::preserveCellHeights($source);
            $xpath = $this->xpath($actual);
            $cell = $xpath->query('//*[@id="title-photo"]')->item(0);

            $this->assertSame($title, $cell->getAttribute('title'));
            $this->assertStringContainsString('title="'.$title.'"', $actual);
            $this->assertSame('143.2px', $this->css($xpath, 'title-photo', 'height'));
            $this->assertSame($style === '' ? null : 'blue', $this->css($xpath, 'title-photo', 'color'));
            $this->assertSame($style === '' ? null : '100%', $this->css($xpath, 'title-photo', 'width'));
            $this->assertSame($actual, PdfTableLayout::preserveCellHeights($actual));
        }
    }

    public static function cascadingRowHeights(): array
    {
        return [
            'last normal declaration wins' => ['height:10px;height:80px', '80px'],
            'earlier important beats later normal' => ['height:10px!important;height:80px', '10px'],
            'later important wins and survives later normal' => ['height:10px!important;height:80px!important;height:20px', '80px'],
        ];
    }

    #[DataProvider('cascadingRowHeights')]
    public function test_row_height_transfer_respects_css_declaration_order_and_important_precedence(string $style, string $expected): void
    {
        $source = '<table><tr style="'.$style.'"><td id="cascading-photo">Photo</td></tr></table>';
        $actual = PdfTableLayout::preserveCellHeights($source);

        $this->assertSame($expected, $this->css($this->xpath($actual), 'cascading-photo', 'height'));
        $this->assertStringContainsString('<tr style="'.$style.'">', $actual);
        $this->assertSame($actual, PdfTableLayout::preserveCellHeights($actual));
    }

    public function test_document_html_processing_preserves_photo_height_variables_attachments_and_original_database_content(): void
    {
        app()->setLocale('en');
        Storage::fake('private');
        Storage::fake('public');
        config(['filament-custom-forms.uploads.disk' => 'private', 'filament-custom-forms.uploads.legacy_disk' => 'public']);
        $photo = 'custom-form-uploads/layout-photo.png';
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF9kAAAAASUVORK5CYII=');
        Storage::disk('private')->put($photo, $image);
        $form = CustomForm::query()->create(['name' => 'Layout application', 'slug' => 'layout-application', 'schema' => [], 'is_active' => true]);
        $form->fields()->create(['name' => 'photo', 'label' => 'Attachment photo', 'type' => 'file_upload']);
        $entry = CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'review_status' => 'pending', 'data' => ['full_name' => 'Dara Sok', 'photo' => $photo],
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => false, 'stages' => []],
        ]));
        $source = '<p>{{data.full_name}}</p><!-- pagebreak --><table border="1" style="width:16.5071%;height:143.2px"><tr style="height:143.2px"><td id="saved-photo" style="width:100%">៤x៦</td></tr></table>';
        $template = DocumentTemplate::query()->create(['name' => 'Stored photo box', 'custom_form_id' => $form->id, 'content' => $source, 'extra_data_sources' => []]);
        $templateBefore = $template->fresh()->getRawOriginal();
        $entryBefore = $entry->fresh()->getRawOriginal();

        $html = (new ReflectionMethod(DocumentRenderer::class, 'processHtmlContent'))->invoke(new DocumentRenderer, $template, $entry);

        $this->assertSame('143.2px', $this->css($this->xpath($html), 'saved-photo', 'height'));
        $this->assertStringContainsString('<p>Dara Sok</p>', $html);
        $this->assertStringContainsString('<pagebreak', $html);
        $this->assertStringContainsString(Storage::disk('private')->path($photo), $html);
        $this->assertStringContainsString('max-height: 258mm', $html);
        $this->assertSame($source, $template->fresh()->content);
        $this->assertSame($templateBefore, $template->fresh()->getRawOriginal());
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame([], $template->getDirty());
        $this->assertSame([], $entry->getDirty());
        $this->assertSame($image, Storage::disk('private')->get($photo));
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $this->assertTrue($document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    private function css(DOMXPath $xpath, string $id, string $property): ?string
    {
        $cell = $xpath->query('//*[@id="'.$id.'"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $cell);
        preg_match('/(?:^|;)\\s*'.preg_quote($property, '/').'\\s*:\\s*([^;]*)/i', $cell->getAttribute('style'), $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }
}
