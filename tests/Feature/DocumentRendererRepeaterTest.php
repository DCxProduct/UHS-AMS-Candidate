<?php

namespace Tests\Feature;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas\DocumentTemplateForm;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class DocumentRendererRepeaterTest extends TestCase
{
    use RefreshDatabase;

    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('en');
        Storage::fake('private');
        Storage::fake('public');
        $this->form = CustomForm::query()->create([
            'name' => 'Repeater application',
            'slug' => 'repeater-application',
            'schema' => [],
            'is_active' => true,
        ]);
        $education = $this->field('educations', 'repeater');
        $this->field('educational_institution', 'text_input', $education);
        $this->field('country', 'select_dropdown', $education, ['choices' => [
            'cambodia' => ['en' => 'Cambodia', 'km' => 'កម្ពុជា'],
            'thailand' => ['en' => 'Thailand', 'km' => 'ថៃ'],
        ]]);
        $this->field('from_year', 'text_input', $education);
        $work = $this->field('cv_work_history', 'repeater');
        $this->field('cv_organization', 'text_input', $work);
        $this->field('cv_position', 'text_input', $work);
        $this->field('cv_start_year', 'text_input', $work);
    }

    public static function rowShapesAndTemplatePaths(): array
    {
        return [
            'short paths with plain list rows' => [false, false, false],
            'explicit paths with plain list rows' => [true, false, false],
            'short paths with UUID nested data rows' => [false, true, false],
            'explicit paths with UUID nested data rows' => [true, true, false],
            'legacy data aliases with UUID nested rows' => [true, true, true],
        ];
    }

    #[DataProvider('rowShapesAndTemplatePaths')]
    public function test_education_and_work_tables_repeat_each_saved_item_with_parent_values_and_option_labels(
        bool $explicitPaths,
        bool $wrappedRows,
        bool $dataAliases,
    ): void {
        $entry = $this->entry($wrappedRows);
        $before = $entry->fresh()->getRawOriginal();
        $pathPrefix = $explicitPaths ? 'data.' : '';
        $rowData = $dataAliases ? '.data' : '';
        $template = '<h1>{{full_name}}</h1>'
            .'<table id="educations"><thead><tr><th>Institution</th><th>Country</th><th>Year</th><th>Candidate</th></tr></thead><tbody>'
            .'<!-- {{#foreach '.$pathPrefix.'educations as education}} -->'
            .'<tr><td>{{education'.$rowData.'.educational_institution}}</td><td>{{education'.$rowData.'.country}}</td><td>{{education'.$rowData.'.from_year}}</td><td>{{full_name}}</td></tr>'
            .'<!-- {{/foreach}} -->'
            .'</tbody></table>'
            .'<table id="work"><thead><tr><th>Organization</th><th>Position</th><th>Year</th><th>Candidate</th></tr></thead><tbody>'
            .'<!-- {{#foreach '.$pathPrefix.'cv_work_history as work}} -->'
            .'<tr><td>{{work'.$rowData.'.cv_organization}}</td><td>{{work'.$rowData.'.cv_position}}</td><td>{{work'.$rowData.'.cv_start_year}}</td><td>{{data.full_name}}</td></tr>'
            .'<!-- {{/foreach}} -->'
            .'</tbody></table><p>{{data.phone_number}}</p>';

        $html = $this->render($template, $entry);
        $xpath = $this->xpath($html);

        $this->assertSame([
            ['University One', 'Cambodia', '2020', 'Dara Sok'],
            ['University Two', 'Thailand', '2022', 'Dara Sok'],
        ], $this->tableRows($xpath, 'educations'));
        $this->assertSame([
            ['Hospital One', 'Nurse', '2023', 'Dara Sok'],
            ['Hospital Two', 'Supervisor', '2025', 'Dara Sok'],
        ], $this->tableRows($xpath, 'work'));
        $this->assertSame(1, $xpath->query('//table[@id="educations"]/thead/tr')->length);
        $this->assertSame(1, $xpath->query('//table[@id="work"]/thead/tr')->length);
        $this->assertStringContainsString('<h1>Dara Sok</h1>', $html);
        $this->assertStringContainsString('<p>012345678</p>', $html);
        $this->assertStringNotContainsString('{{', $html);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
    }

    public function test_loop_option_labels_keep_the_original_model_context_and_template_language(): void
    {
        $entry = $this->entry(true);
        $html = $this->render(
            '<table id="countries"><thead><tr><th>ប្រទេស</th></tr></thead><tbody>'
                .'<!-- {{#foreach educations as education}} --><tr><td>{{education.country}}</td></tr><!-- {{/foreach}} -->'
                .'</tbody></table>',
            $entry,
        );

        $this->assertSame([['កម្ពុជា'], ['ថៃ']], $this->tableRows($this->xpath($html), 'countries'));
        $this->assertStringNotContainsString('cambodia', $html);
        $this->assertStringNotContainsString('thailand', $html);
    }

    public function test_tiny_mce_page_break_markers_are_converted_for_mpdf(): void
    {
        $html = $this->render(
            '<p>First page</p><!-- pagebreak --><p>Second page</p>'
                . '<img class="mce-pagebreak" data-mce-placeholder="1" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==">',
            $this->entry(false),
        );

        $this->assertSame(2, substr_count($html, '<pagebreak />'));
        $this->assertStringNotContainsString('mce-pagebreak', $html);
        $this->assertStringNotContainsString('<!-- pagebreak -->', $html);
    }

    public static function degreeChoiceLocales(): array
    {
        return [
            'English' => ['en', 'Degree level', ['Associate degree', 'Bachelor degree']],
            'Khmer' => ['km', 'កម្រិតសញ្ញាបត្រ', ['បរិញ្ញាបត្ររង', 'បរិញ្ញាបត្រ']],
        ];
    }

    #[DataProvider('degreeChoiceLocales')]
    public function test_repeater_degree_codes_use_their_field_choice_labels_in_the_document_language(string $locale, string $heading, array $expected): void
    {
        app()->setLocale($locale);
        $education = $this->form->fields()->where('name', 'educations')->firstOrFail();
        $this->field('degree_level_major', 'select_dropdown', $education, ['choices' => [
            'associate' => ['en' => 'Associate degree', 'km' => 'បរិញ្ញាបត្ររង'],
            'bachelor' => ['en' => 'Bachelor degree', 'km' => 'បរិញ្ញាបត្រ'],
        ]]);
        $entry = $this->entry(true);
        $data = $entry->data;
        $data['educations']['education-uuid-one']['data']['degree_level_major'] = 'associate';
        $data['educations']['education-uuid-two']['data']['degree_level_major'] = 'bachelor';
        $entry->forceFill(['data' => $data])->saveQuietly();
        $before = $entry->fresh()->getRawOriginal();

        $html = $this->render(
            '<table id="degrees"><thead><tr><th>'.$heading.'</th></tr></thead><tbody>'
                .'<!-- {{#foreach educations as education}} --><tr><td>{{education.degree_level_major}}</td></tr><!-- {{/foreach}} -->'
                .'</tbody></table>',
            $entry,
        );

        $this->assertSame(array_map(fn (string $label): array => [$label], $expected), $this->tableRows($this->xpath($html), 'degrees'));
        $this->assertStringNotContainsString('<td>associate</td>', $html);
        $this->assertStringNotContainsString('<td>bachelor</td>', $html);
        $this->assertSame(['associate', 'bachelor'], data_get($entry->fresh()->data, 'educations.*.data.degree_level_major'));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
    }

    public static function emptyRepeaters(): array
    {
        return ['missing repeater' => [[]], 'empty repeater' => [['educations' => []]]];
    }

    #[DataProvider('emptyRepeaters')]
    public function test_missing_or_empty_repeaters_do_not_leave_blank_prototype_rows_or_loop_markers(array $rows): void
    {
        $entry = CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->create([
            'custom_form_id' => $this->form->getKey(),
            'data' => ['full_name' => 'Dara Sok', ...$rows],
        ]));
        $html = $this->render(
            '<h1>{{full_name}}</h1><table id="empty"><thead><tr><th>Institution</th></tr></thead><tbody>'
                .'<!-- {{#foreach educations as education}} --><tr><td>{{education.educational_institution}}</td></tr><!-- {{/foreach}} -->'
                .'</tbody></table>',
            $entry,
        );
        $xpath = $this->xpath($html);

        $this->assertSame(0, $xpath->query('//table[@id="empty"]/tbody/tr')->length);
        $this->assertSame(1, $xpath->query('//table[@id="empty"]/thead/tr')->length);
        $this->assertStringContainsString('<h1>Dara Sok</h1>', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_qr_codes_and_barcodes_are_resolved_independently_for_each_repeater_item(): void
    {
        $renderer = new class extends DocumentRenderer
        {
            public array $qrValues = [];

            public array $barcodeValues = [];

            protected function generateQrCodeTag(string $value, int $size): string
            {
                $this->qrValues[] = $value;

                return '[QR:'.$value.']';
            }

            protected function generateBarcodeTag(string $value, string $type, int $widthFactor, int $height): string
            {
                $this->barcodeValues[] = $value;

                return '[BAR:'.$value.']';
            }
        };
        $html = $this->render(
            '{{#foreach educations as education}}<p>{{#qrcode education.educational_institution}} {{#barcode education.from_year}}</p>{{/foreach}}',
            $this->entry(true),
            $renderer,
        );

        $this->assertSame(['University One', 'University Two'], $renderer->qrValues);
        $this->assertSame(['2020', '2022'], $renderer->barcodeValues);
        $this->assertSame('<p>[QR:University One] [BAR:2020]</p><p>[QR:University Two] [BAR:2022]</p>', $html);
    }

    public function test_nested_repeaters_keep_outer_item_and_root_placeholders_available(): void
    {
        $entry = new CustomFormEntry([
            'custom_form_id' => $this->form->getKey(),
            'data' => [
                'full_name' => 'Dara Sok',
                'educations' => [
                    'education-one' => ['data' => [
                        'educational_institution' => 'University One',
                        'qualifications' => [
                            'qualification-one' => ['data' => ['title' => 'Nursing']],
                            'qualification-two' => ['data' => ['title' => 'Midwifery']],
                        ],
                    ]],
                    'education-two' => ['data' => [
                        'educational_institution' => 'University Two',
                        'qualifications' => [['title' => 'Public Health']],
                    ]],
                ],
            ],
        ]);
        $html = $this->render(
            '{{#foreach educations as education}}<section><h2>{{education.educational_institution}}</h2><ul>'
                .'<!-- {{#foreach education.qualifications as qualification}} -->'
                .'<li>{{qualification.title}} at {{education.educational_institution}} for {{full_name}}</li>'
                .'<!-- {{/foreach}} --></ul></section>{{/foreach}}',
            $entry,
        );
        $xpath = $this->xpath($html);
        $actual = [];
        foreach ($xpath->query('//section/li | //section/ul/li') as $item) {
            $actual[] = trim($item->textContent);
        }

        $this->assertSame([
            'Nursing at University One for Dara Sok',
            'Midwifery at University One for Dara Sok',
            'Public Health at University Two for Dara Sok',
        ], $actual);
        $this->assertSame(2, $xpath->query('//section')->length);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_document_editor_metadata_lists_repeaters_with_their_own_child_fields_instead_of_invalid_scalar_paths(): void
    {
        $layout = $this->field('education_section', 'section');
        $this->form->fields()->where('name', 'educations')->update([
            'parent_id' => $layout->getKey(),
            'label' => json_encode(['en' => 'Education history', 'km' => 'ប្រវត្តិការសិក្សា']),
        ]);
        $this->field('full_name', 'text_input');
        $this->field('phone_number', 'text_input', $layout);

        $metadata = DocumentTemplateForm::documentFieldMetadata($this->form);
        $repeaters = collect($metadata['repeaters'])->keyBy('path');

        $this->assertContains('data.full_name', $metadata['variables']);
        $this->assertContains('data.phone_number', $metadata['variables']);
        $this->assertNotContains('data.educational_institution', $metadata['variables']);
        $this->assertNotContains('data.cv_organization', $metadata['variables']);
        $this->assertNotContains('data.educations', $metadata['variables']);
        $this->assertSame('Education history', $repeaters['data.educations']['label']);
        $this->assertSame('item', $repeaters['data.educations']['alias']);
        $this->assertEqualsCanonicalizing(['educational_institution', 'country', 'from_year'], array_column($repeaters['data.educations']['fields'], 'name'));
        $this->assertEqualsCanonicalizing(['cv_organization', 'cv_position', 'cv_start_year'], array_column($repeaters['data.cv_work_history']['fields'], 'name'));
    }

    private function render(string $content, CustomFormEntry $entry, ?DocumentRenderer $renderer = null): string
    {
        $template = new DocumentTemplate([
            'name' => 'Repeater document',
            'custom_form_id' => $this->form->getKey(),
            'content' => $content,
            'extra_data_sources' => [],
        ]);

        return (new ReflectionMethod(DocumentRenderer::class, 'processHtmlContent'))->invoke($renderer ?? new DocumentRenderer, $template, $entry);
    }

    private function field(string $name, string $type, ?CustomFormField $parent = null, array $options = []): CustomFormField
    {
        return $this->form->fields()->create([
            'name' => $name,
            'label' => $name,
            'type' => $type,
            'parent_id' => $parent?->getKey(),
            'options' => $options,
            'sort' => 1,
        ]);
    }

    private function entry(bool $wrappedRows): CustomFormEntry
    {
        $educationRows = [
            ['educational_institution' => 'University One', 'country' => 'cambodia', 'from_year' => '2020'],
            ['educational_institution' => 'University Two', 'country' => 'thailand', 'from_year' => '2022'],
        ];
        $workRows = [
            ['cv_organization' => 'Hospital One', 'cv_position' => 'Nurse', 'cv_start_year' => '2023'],
            ['cv_organization' => 'Hospital Two', 'cv_position' => 'Supervisor', 'cv_start_year' => '2025'],
        ];
        if ($wrappedRows) {
            $educationRows = ['education-uuid-one' => ['data' => $educationRows[0]], 'education-uuid-two' => ['data' => $educationRows[1]]];
            $workRows = ['work-uuid-one' => ['data' => $workRows[0]], 'work-uuid-two' => ['data' => $workRows[1]]];
        }

        return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(),
            'review_status' => 'pending',
            'data' => [
                'full_name' => 'Dara Sok',
                'phone_number' => '012345678',
                'educations' => $educationRows,
                'cv_work_history' => $workRows,
            ],
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => false, 'stages' => []],
        ]));
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        return new \DOMXPath($document);
    }

    private function tableRows(\DOMXPath $xpath, string $tableId): array
    {
        $rows = [];
        foreach ($xpath->query('//table[@id="'.$tableId.'"]/tbody/tr') as $row) {
            $cells = [];
            foreach ($xpath->query('./td', $row) as $cell) {
                $cells[] = trim($cell->textContent);
            }
            $rows[] = $cells;
        }

        return $rows;
    }
}
