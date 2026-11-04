<?php

namespace Tests\Feature;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class DocumentRendererProfileRepeaterTest extends TestCase
{
    use RefreshDatabase;

    private CustomForm $profile;

    private CustomForm $application;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        $this->profile = $this->form('profile');
        $this->application = $this->form('profile-fallback-application');
        $this->repeater($this->profile, 'siblings');
        $this->repeater($this->application, 'siblings');
    }

    public function test_document_uses_the_entry_owners_profile_without_changing_the_entry_or_adding_profile_attachments(): void
    {
        $owner = $this->user('owner');
        $admin = $this->user('admin');
        Storage::disk('private')->put('custom-form-uploads/profile-photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jF9kAAAAASUVORK5CYII='));
        $this->entry($this->profile, $owner, ['siblings' => [
            ['sibling_name' => 'Owner sibling one', 'sibling_photo' => 'custom-form-uploads/profile-photo.png'],
            ['data' => ['sibling_name' => 'Owner sibling two']],
        ]]);
        $this->entry($this->profile, $admin, ['siblings' => [['sibling_name' => 'Admin private sibling']], 'phone_number' => 'Admin private phone']);
        $entry = $this->entry($this->application, $owner, ['full_name' => 'Entry candidate name']);
        $before = $entry->getAttributes();
        $stored = (array) DB::table('custom_form_entries')->where('id', $entry->getKey())->first();
        $this->actingAs($admin);

        $html = $this->render($entry, '<p>{{data.full_name}} {{data.phone_number}}</p>'.$this->loop());

        $this->assertStringContainsString('Owner sibling one', $html);
        $this->assertStringContainsString('Owner sibling two', $html);
        $this->assertStringContainsString('Entry candidate name', $html);
        $this->assertStringNotContainsString('Admin private', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertSame($before, $entry->getAttributes());
        $this->assertSame([], $entry->getDirty());
        $this->assertSame($stored, (array) DB::table('custom_form_entries')->where('id', $entry->getKey())->first());
    }

    public static function existingRepeaterValues(): array
    {
        return [
            'explicit empty list' => [[]],
            'explicit null' => [null],
            'explicit blank string' => [''],
            'saved rows' => [[['sibling_name' => 'Entry sibling']]],
        ];
    }

    #[DataProvider('existingRepeaterValues')]
    public function test_existing_repeater_keys_are_authoritative(mixed $value): void
    {
        $owner = $this->user('authoritative-owner');
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Profile sibling']]]);
        $entry = $this->entry($this->application, $owner, ['siblings' => $value]);
        $before = $entry->getAttributes();
        $stored = $entry->fresh()->getAttributes();

        $html = $this->render($entry);

        $this->assertStringNotContainsString('Profile sibling', $html);
        if (is_array($value) && $value !== []) {
            $this->assertStringContainsString('Entry sibling', $html);
        }
        $this->assertSame($before, $entry->getAttributes());
        $this->assertSame($stored, $entry->fresh()->getAttributes());
    }

    public function test_latest_saved_profile_ignores_drafts_and_never_resurrects_older_cleared_rows(): void
    {
        $owner = $this->user('saved-owner');
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Saved sibling']]]);
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Draft review sibling']]], ['review_status' => 'draft']);
        $this->entry($this->profile, $owner, ['registration_status' => 'draft', 'siblings' => [['sibling_name' => 'Draft registration sibling']]]);
        $entry = $this->entry($this->application, $owner, []);

        $html = $this->render($entry);
        $this->assertStringContainsString('Saved sibling', $html);
        $this->assertStringNotContainsString('Draft', $html);

        foreach ([['siblings' => []], ['siblings' => null], []] as $clearedData) {
            $this->entry($this->profile, $owner, $clearedData);
            $this->assertStringNotContainsString('Saved sibling', $this->render($entry));
        }
    }

    public function test_fallback_requires_a_declared_repeater_and_a_real_entry_owner(): void
    {
        $owner = $this->user('scope-owner');
        $this->repeater($this->profile, 'private_contacts');
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Private sibling']], 'private_contacts' => [['sibling_name' => 'Private contact']], 'phone_number' => 'Private scalar']);
        $this->actingAs($owner);

        $unowned = $this->entry($this->application, null, []);
        $this->assertStringNotContainsString('Private', $this->render($unowned));

        $entry = $this->entry($this->application, $owner, []);
        $this->assertStringNotContainsString('Private', $this->render($entry, $this->loop('data.private_contacts').'<p>{{data.phone_number}}</p>'));
        $this->assertStringNotContainsString('Private', $this->render(['created_by' => $owner->getKey(), 'custom_form_id' => $this->application->getKey(), 'data' => []]));
        $this->assertStringNotContainsString('Private', $this->render($owner));

        $profileWithoutSiblings = $this->entry($this->profile, $owner, []);
        $this->assertStringNotContainsString('Private', $this->render($profileWithoutSiblings));
    }

    public function test_canonical_ownership_prevents_conflicting_legacy_owner_columns_from_leaking_profiles(): void
    {
        Schema::table('custom_form_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
        });
        $owner = $this->user('canonical-owner');
        $other = $this->user('canonical-other');
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Canonical owner sibling']]]);
        $this->entry($this->profile, $other, ['siblings' => [['sibling_name' => 'Conflicting private sibling']]], ['user_id' => $owner->getKey(), 'created_by_id' => $owner->getKey()]);
        $entry = $this->entry($this->application, $owner, [], ['user_id' => $other->getKey()]);

        $html = $this->render($entry);
        $this->assertStringContainsString('Canonical owner sibling', $html);
        $this->assertStringNotContainsString('Conflicting private sibling', $html);

        $this->entry($this->profile, null, ['siblings' => [['sibling_name' => 'Legacy owner sibling']]], ['user_id' => $owner->getKey()]);
        $legacyEntry = $this->entry($this->application, null, [], ['created_by_id' => $owner->getKey()]);
        $this->assertStringContainsString('Legacy owner sibling', $this->render($legacyEntry));
    }

    public function test_explicit_mapping_in_a_selected_active_subform_is_supported_without_enabling_inactive_schemas(): void
    {
        $owner = $this->user('mapped-owner');
        $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => 'Mapped sibling']]]);
        $active = $this->form('active-subform');
        $active->forceFill(['custom_form_id' => $this->application->getKey(), 'menu_placement' => 'sub_item', 'sub_item_type' => 'bachelor'])->saveQuietly();
        $this->repeater($active, 'family_members', ['profile_keyword' => 'siblings']);
        $inactive = $this->form('inactive-subform');
        $inactive->forceFill(['custom_form_id' => $this->application->getKey(), 'menu_placement' => 'sub_item', 'sub_item_type' => 'bachelor', 'is_active' => false])->saveQuietly();
        $this->repeater($inactive, 'hidden_members', ['profile_field' => 'siblings']);
        $entry = $this->entry($this->application, $owner, ['form_selection' => 'bachelor']);

        $this->assertStringContainsString('Mapped sibling', $this->render($entry, $this->loop('data.family_members')));
        $this->assertStringNotContainsString('Mapped sibling', $this->render($entry, $this->loop('data.hidden_members')));
    }

    public function test_one_renderer_does_not_reuse_profile_data_across_owners(): void
    {
        $renderer = new DocumentRenderer;
        foreach (['First owner sibling', 'Second owner sibling'] as $index => $name) {
            $owner = $this->user('batch-owner-'.$index);
            $this->entry($this->profile, $owner, ['siblings' => [['sibling_name' => $name]]]);
            $entry = $this->entry($this->application, $owner, []);
            $html = $this->render($entry, $this->loop('siblings'), $renderer);
            $this->assertStringContainsString($name, $html);
            $this->assertStringNotContainsString($index === 0 ? 'Second owner sibling' : 'First owner sibling', $html);
        }
    }

    private function form(string $slug): CustomForm
    {
        return CustomForm::query()->create(['name' => $slug, 'slug' => $slug, 'schema' => [], 'is_active' => true]);
    }

    private function repeater(CustomForm $form, string $name, array $options = []): void
    {
        $layout = $form->fields()->create(['name' => $name.'_layout', 'type' => 'section']);
        $repeater = $form->fields()->create(['name' => $name, 'type' => 'repeater', 'parent_id' => $layout->getKey(), 'options' => $options]);
        $form->fields()->create(['name' => 'sibling_name', 'type' => 'text_input', 'parent_id' => $repeater->getKey()]);
    }

    private function user(string $name): User
    {
        return User::query()->create(['registration_type' => 'student', 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }

    private function entry(CustomForm $form, ?User $owner, array $data, array $attributes = []): CustomFormEntry
    {
        return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->getKey(),
            'created_by' => $owner?->getKey(),
            'review_status' => 'pending',
            'data' => $data,
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => false, 'stages' => []],
            ...$attributes,
        ]));
    }

    private function loop(string $path = 'data.siblings'): string
    {
        return '<table><tbody><!-- {{#foreach '.$path.' as sibling}} --><tr><td>{{sibling.sibling_name}}</td></tr><!-- {{/foreach}} --></tbody></table>';
    }

    private function render(array|object $entry, ?string $content = null, ?DocumentRenderer $renderer = null): string
    {
        $template = new DocumentTemplate(['content' => $content ?? $this->loop(), 'extra_data_sources' => []]);

        return (new ReflectionMethod(DocumentRenderer::class, 'processHtmlContent'))->invoke($renderer ?? new DocumentRenderer, $template, $entry);
    }
}
