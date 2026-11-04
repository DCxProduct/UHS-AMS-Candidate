<?php

namespace Tests\Feature;

use App\Support\DocumentTemplateDataBindings;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentTemplateDataBindingsTest extends TestCase
{
    use RefreshDatabase;

    private const BACKUPS = 'document_template_data_binding_backups';

    public function test_recognized_blank_sections_gain_schema_bindings_without_changing_surrounding_content_and_are_idempotent(): void
    {
        $source = $this->legacyContent();
        $patched = DocumentTemplateDataBindings::upgrade($source, $this->fieldNames());

        $this->assertNotSame($source, $patched);
        foreach (['educations', 'cv_work_history', 'siblings'] as $path) {
            $this->assertMatchesRegularExpression('/{{\s*#foreach\s+data\.'.$path.'\s+as\s+[a-zA-Z_][a-zA-Z0-9_]*\s*}}/', $patched);
        }
        foreach (['educational_institution', 'degree_level_major', 'from_year', 'to_year', 'country', 'graduation_year', 'cv_start_year', 'cv_end_year', 'cv_organization', 'cv_ministry', 'cv_position', 'sibling_name', 'sibling_gender', 'sibling_year_of_birth', 'sibling_occupation'] as $field) {
            $this->assertStringContainsString('.'.$field, $patched);
        }
        $this->assertStringContainsString('<h1 style="text-align:center">Application heading</h1>', $patched);
        $this->assertStringContainsString('<footer>Keep this footer unchanged.</footer>', $patched);
        $this->assertStringContainsString('style="border-collapse:collapse;width:100%"', $patched);
        $this->assertStringContainsString('ខ- អំពីបងប្អូនបង្កើត', $patched);
        $this->assertSame($patched, DocumentTemplateDataBindings::upgrade($patched, $this->fieldNames()));
    }

    public static function guardedTableCases(): array
    {
        return [
            'custom cell text' => ['<strong>Existing custom content</strong>', false],
            'existing scalar binding' => ['{{data.existing_custom_field}}', false],
            'existing image' => ['<img src="/logo.png">', false],
            'unrecognized header' => ['&nbsp;', true],
        ];
    }

    #[DataProvider('guardedTableCases')]
    public function test_custom_nonblank_or_unrecognized_tables_are_preserved(string $firstCell, bool $differentHeader): void
    {
        $source = $this->blankTable('education', $firstCell);
        if ($differentHeader) {
            $source = str_replace('គ្រឹះស្ថានសិក្សានិងសាលា', 'Custom institution heading', $source);
        }

        $this->assertSame($source, DocumentTemplateDataBindings::upgrade($source, $this->fieldNames()));
    }

    public function test_existing_loops_and_templates_without_the_required_schema_fields_are_preserved(): void
    {
        $loop = '<!-- {{#foreach data.educations as education}} -->'
            .$this->blankTable('education', '{{education.educational_institution}}')
            .'<!-- {{/foreach}} -->';
        $this->assertSame($loop, DocumentTemplateDataBindings::upgrade($loop, $this->fieldNames()));
        $this->assertSame($this->legacyContent(), DocumentTemplateDataBindings::upgrade($this->legacyContent(), []));
        $incompleteFields = array_values(array_diff($this->fieldNames(), ['country']));
        $table = $this->blankTable('education');
        $this->assertSame($table, DocumentTemplateDataBindings::upgrade($table, $incompleteFields));
    }

    public function test_children_and_spouse_aliases_follow_available_current_schema_and_keep_the_original_prefix_style(): void
    {
        $source = '<p>{{data.Number of children}} / {{data.Number_of_sons}} / {{Number_of_daughters}}; '
            .'{{data.spouse_children_heading}} {{data.spouse_date_of_birth}}</p>';
        $patched = $this->compactPlaceholders(DocumentTemplateDataBindings::upgrade($source, $this->fieldNames()));

        foreach (['{{data.number_of_children}}', '{{data.number_of_sons}}', '{{number_of_daughters}}', '{{data.spouse_name}}', '{{data.spouse_year_of_birth}}'] as $placeholder) {
            $this->assertStringContainsString($placeholder, $patched);
        }
        $this->assertSame(
            '{{data.spouse_children_heading}} {{data.spouse_date_of_birth}}',
            $this->compactPlaceholders(DocumentTemplateDataBindings::upgrade('{{data.spouse_name}} {{data.spouse_year_of_birth}}', ['spouse_children_heading', 'spouse_date_of_birth'])),
        );
    }

    public function test_only_the_recognized_missing_nationality_clause_is_replaced_with_available_spouse_occupation(): void
    {
        $source = '<p>ប្តី/ប្រពន្ធ {{data.spouse_children_heading}} ជនជាតិ{{data.Husband_or_wife_nationality}}សញ្ជាតិ{{data.Nationality_of_husband_or_wife}}</p>';
        $patched = $this->compactPlaceholders(DocumentTemplateDataBindings::upgrade($source, $this->fieldNames()));

        $this->assertStringContainsString('មុខរបរ {{data.spouse_occupation}}', $patched);
        $this->assertStringNotContainsString('Husband_or_wife_nationality', $patched);
        $this->assertStringNotContainsString('Nationality_of_husband_or_wife', $patched);

        $withExistingFields = $this->compactPlaceholders(DocumentTemplateDataBindings::upgrade($source, [...$this->fieldNames(), 'Husband_or_wife_nationality', 'Nationality_of_husband_or_wife']));
        $this->assertStringContainsString('{{data.Husband_or_wife_nationality}}', $withExistingFields);
        $this->assertStringContainsString('{{data.Nationality_of_husband_or_wife}}', $withExistingFields);
    }

    public function test_migration_repairs_all_matching_templates_by_schema_independent_of_ids_and_backs_up_each_once(): void
    {
        $source = $this->legacyContent();
        $ids = [];
        foreach ([701, 802, 903] as $index => $templateId) {
            $formId = $this->formWithFields('application-'.$index, childFields: $index === 2);
            $ids[] = $this->template($templateId, $formId, $source);
        }
        $noFieldsForm = $this->formWithFields('no-fields', fields: []);
        $unrelated = $this->template(1004, $noFieldsForm, $source, type: 'invoice');
        $missingSchema = $this->template(1005, $noFieldsForm, $source);
        $malformedType = $this->template(1006, $this->formWithFields('malformed'), $source, type: 'custom_form_12_extra');
        $customContent = $this->template(1007, $this->formWithFields('custom-text'), '<p>Preserve custom document.</p>');
        $inactiveChildForm = $this->formWithFields('inactive-child', childFields: true);
        DB::table('custom_forms')->where('custom_form_id', $inactiveChildForm)->update(['is_active' => false]);
        $inactiveSchema = $this->template(1008, $inactiveChildForm, $source);
        $unknownForm = $this->template(1009, $noFieldsForm, $source, type: 'custom_form_999999');
        $this->migration()->up();

        $this->assertTrue(Schema::hasTable(self::BACKUPS));
        $this->assertSame(3, DB::table(self::BACKUPS)->count());
        $patchedContents = [];
        foreach ($ids as $id) {
            $patched = DB::table('document_templates')->where('id', $id)->value('content');
            $patchedContents[$id] = $patched;
            $this->assertNotSame($source, $patched);
            $backup = DB::table(self::BACKUPS)->where('document_template_id', $id)->first();
            $this->assertNotNull($backup);
            $this->assertSame($source, $backup->original_content);
            $this->assertSame(hash('sha256', $patched), $backup->patched_content_hash);
            $this->assertSame('2026-10-01 12:34:56', $backup->original_updated_at);
        }
        foreach ([$unrelated, $missingSchema, $malformedType, $inactiveSchema, $unknownForm] as $id) {
            $this->assertSame($source, DB::table('document_templates')->where('id', $id)->value('content'));
        }
        $this->assertSame('<p>Preserve custom document.</p>', DB::table('document_templates')->where('id', $customContent)->value('content'));

        $this->migration()->up();
        $this->assertSame(3, DB::table(self::BACKUPS)->count());
        foreach ($patchedContents as $id => $patched) {
            $this->assertSame($patched, DB::table('document_templates')->where('id', $id)->value('content'));
        }
    }

    public function test_rollback_restores_only_unchanged_patched_templates_and_preserves_later_edits_and_deletions(): void
    {
        $source = $this->legacyContent();
        $formId = $this->formWithFields('rollback');
        $unchanged = $this->template(1801, $formId, $source);
        $edited = $this->template(1802, $formId, $source);
        $deleted = $this->template(1803, $formId, $source);
        $this->migration()->up();
        $laterContent = DB::table('document_templates')->where('id', $edited)->value('content').'<p>Later administrator edit.</p>';
        DB::table('document_templates')->where('id', $edited)->update(['content' => $laterContent, 'updated_at' => '2026-11-04 18:00:00']);
        DB::table('document_templates')->where('id', $deleted)->delete();

        $this->migration()->up();
        $this->assertSame($laterContent, DB::table('document_templates')->where('id', $edited)->value('content'));
        $this->assertSame($source, DB::table(self::BACKUPS)->where('document_template_id', $edited)->value('original_content'));

        $this->migration()->down();

        $this->assertSame($source, DB::table('document_templates')->where('id', $unchanged)->value('content'));
        $this->assertSame('2026-10-01 12:34:56', DB::table('document_templates')->where('id', $unchanged)->value('updated_at'));
        $this->assertSame($laterContent, DB::table('document_templates')->where('id', $edited)->value('content'));
        $this->assertSame('2026-11-04 18:00:00', DB::table('document_templates')->where('id', $edited)->value('updated_at'));
        $this->assertFalse(DB::table('document_templates')->where('id', $deleted)->exists());
        $this->assertFalse(Schema::hasTable(self::BACKUPS));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_04_000001_bind_application_document_template_data.php');
    }

    private function compactPlaceholders(string $content): string
    {
        return preg_replace_callback('/{{\s*(.*?)\s*}}/s', fn (array $match): string => '{{'.trim($match[1]).'}}', $content);
    }

    private function formWithFields(string $slug, ?array $fields = null, bool $childFields = false): int
    {
        $formId = DB::table('custom_forms')->insertGetId(['name' => $slug, 'slug' => $slug, 'schema' => '[]', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $fieldFormId = $formId;
        if ($childFields) {
            $fieldFormId = DB::table('custom_forms')->insertGetId(['name' => $slug.' child', 'slug' => $slug.'-child', 'schema' => '[]', 'custom_form_id' => $formId, 'menu_placement' => 'sub_item', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($fields ?? $this->fieldNames() as $name) {
            DB::table('custom_form_fields')->insert(['custom_form_id' => $fieldFormId, 'name' => $name, 'label' => $name, 'type' => in_array($name, ['educations', 'cv_work_history', 'siblings'], true) ? 'repeater' : 'text_input', 'required' => false, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $formId;
    }

    private function template(int $id, int $formId, string $content, ?string $type = null): int
    {
        DB::table('document_templates')->insert(['id' => $id, 'name' => 'Binding repair '.$id, 'type' => $type ?? 'custom_form_'.$formId, 'model_class' => CustomFormEntry::class, 'custom_form_id' => $formId, 'content' => $content, 'created_at' => '2026-10-01 12:34:56', 'updated_at' => '2026-10-01 12:34:56']);

        return $id;
    }

    private function fieldNames(): array
    {
        return ['educations', 'educational_institution', 'degree_level_major', 'from_year', 'to_year', 'country', 'graduation_year', 'cv_work_history', 'cv_start_year', 'cv_end_year', 'cv_organization', 'cv_ministry', 'cv_position', 'siblings', 'sibling_name', 'sibling_gender', 'sibling_year_of_birth', 'sibling_occupation', 'number_of_children', 'number_of_sons', 'number_of_daughters', 'spouse_name', 'spouse_year_of_birth', 'spouse_occupation'];
    }

    private function legacyContent(): string
    {
        return '<h1 style="text-align:center">Application heading</h1>'
            .$this->blankTable('education').$this->blankTable('work')
            .'<p dir="ltr"><span style="font-size:10pt"><strong>ខ- អំពីបងប្អូនបង្កើត&nbsp;</strong><br>១- ឈ្មោះ៖&nbsp; &nbsp;ភេទ............ ឆ្នាំកំណើត........................ មុខរបរ........................................................</span></p>'
            .'<p>{{data.Number of children}} / {{data.Number_of_sons}} / {{data.Number_of_daughters}}; {{data.spouse_children_heading}} {{data.spouse_date_of_birth}}</p>'
            .'<footer>Keep this footer unchanged.</footer>';
    }

    private function blankTable(string $type, string $firstCell = '&nbsp;'): string
    {
        $headers = $type === 'education'
            ? ['គ្រឹះស្ថានសិក្សានិងសាលា', 'កម្រិតសញ្ញាបត្រ និង ជំនាញ', 'ឆ្នាំសិក្សា ដល់ឆ្នាំណា', 'ប្រទេស', 'ថ្នាក់បញ្ចប់ការសិក្សា ឬ ទទួលបានសញ្ញាបត្រ']
            : ['ឆ្នាំចូលបំពេញការងារ', 'ថ្នាក់ប្រគល់ការងារ', 'អង្គភាព/ស្ថាប័ន', 'ក្រសួង', 'តួនាទី'];
        $headerCells = implode('', array_map(fn (string $label): string => '<td><strong>'.$label.'</strong></td>', $headers));

        return '<table style="border-collapse:collapse;width:100%" border="1"><tbody><tr>'.$headerCells.'</tr>'
            .'<tr><td>'.$firstCell.'</td>'.str_repeat('<td><span>&nbsp;</span><br></td>', 4).'</tr></tbody></table>';
    }
}
