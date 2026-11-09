<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowConfigurationUsage;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowConfigurationUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $admin = $this->user('usage-admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public function test_button_usage_lists_distinct_localized_stage_types_including_inactive_types(): void
    {
        $button = $this->button('Usage check');
        $first = $this->type('First usage stage', $button, 'ដំណាក់កាលប្រើប្រាស់ទីមួយ');
        $second = $this->type('Inactive usage stage', $button, 'ដំណាក់កាលប្រើប្រាស់អសកម្ម');
        $second->update(['is_active' => false]);
        DB::table('workflow_stage_types')->where('id', $first->getKey())->update(['workflow_button_ids' => json_encode([$button->getKey(), $button->getKey()])]);
        $button->update(['is_active' => false]);
        $this->resetUsage();

        $usage = WorkflowConfigurationUsage::stageTypesForButton($button);
        $this->assertEqualsCanonicalizing([
            ['id' => $first->getKey(), 'name' => 'First usage stage'],
            ['id' => $second->getKey(), 'name' => 'Inactive usage stage'],
        ], $usage);

        app()->setLocale('km');
        $this->assertEqualsCanonicalizing([
            ['id' => $first->getKey(), 'name' => 'ដំណាក់កាលប្រើប្រាស់ទីមួយ'],
            ['id' => $second->getKey(), 'name' => 'ដំណាក់កាលប្រើប្រាស់អសកម្ម'],
        ], WorkflowConfigurationUsage::stageTypesForButton($button));
    }

    public function test_stage_usage_matches_explicit_ids_and_maps_legacy_behaviors_only_to_the_builtin_type(): void
    {
        $button = $this->button('Alias usage check');
        $alias = $this->type('Alias usage stage', $button);
        $builtin = WorkflowStageType::query()->where('is_system', true)->where('behavior', 'review')->firstOrFail();
        $explicit = $this->rawWorkflow('Explicit alias workflow', [['stage_type_id' => $alias->getKey(), 'stage_type' => 'review'], ['stage_type_id' => $alias->getKey(), 'stage_type' => 'review']]);
        $legacy = $this->rawWorkflow('Legacy review workflow', [['stage_type' => 'review']]);
        $approval = $this->rawWorkflow('Legacy approval workflow', [['stage_type' => 'approval']]);
        $builtinExplicit = $this->rawWorkflow('Explicit builtin workflow', [['stage_type_id' => $builtin->getKey(), 'stage_type' => 'review']]);
        $this->resetUsage();

        $this->assertSame([['id' => $explicit->getKey(), 'name' => $explicit->name]], WorkflowConfigurationUsage::workflowsForStage($alias));
        $this->assertEqualsCanonicalizing(array_map(fn (WorkflowNotification $workflow): array => ['id' => $workflow->getKey(), 'name' => $workflow->name], [$legacy, $approval, $builtinExplicit]), WorkflowConfigurationUsage::workflowsForStage($builtin));
    }

    public function test_workflow_button_usage_uses_saved_template_button_definitions_without_inferring_current_catalog_defaults(): void
    {
        $button = $this->button('Frozen template check');
        $type = $this->type('Current catalog button stage', $button);
        $direct = $this->rawWorkflow('Saved button workflow', [['stage_type' => 'review', 'buttons' => [$button->toDefinition(), $button->toDefinition()]]]);
        $this->rawWorkflow('Catalog defaults only workflow', [['stage_type' => 'review', 'stage_type_id' => $type->getKey()]]);
        $this->resetUsage();

        $this->assertSame([['id' => $direct->getKey(), 'name' => $direct->name]], WorkflowConfigurationUsage::workflowsForButton($button));
    }

    public function test_historical_application_snapshots_are_not_counted_as_current_configuration_usage(): void
    {
        $button = $this->button('Historical usage check');
        $type = $this->type('Historical usage stage', $button);
        $workflow = $this->rawWorkflow('Historical usage workflow', [['stage_type' => 'review', 'stage_type_id' => $type->getKey(), 'buttons' => [$button->toDefinition()], 'responsible_role' => ['admin']]]);
        $form = CustomForm::query()->create(['name' => 'Historical usage form', 'slug' => 'historical-usage-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']])->fresh();
        $snapshot = $entry->workflow_snapshot;
        $this->assertSame($button->getKey(), data_get($snapshot, 'stages.0.data.buttons.0.id'));

        $workflow->update(['stages' => []]);
        $type->update(['workflow_button_ids' => null]);
        $this->resetUsage();

        $this->assertSame([], WorkflowConfigurationUsage::stageTypesForButton($button));
        $this->assertSame([], WorkflowConfigurationUsage::workflowsForStage($type));
        $this->assertSame([], WorkflowConfigurationUsage::workflowsForButton($button));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
    }

    public function test_authorized_lists_render_related_configuration_names_with_links(): void
    {
        [$button, $type, $workflow] = $this->linkedConfiguration();
        $buttons = Livewire::test(ListWorkflowButtons::class)
            ->assertTableColumnVisible('stage_types_usage')
            ->assertTableColumnVisible('workflow_templates_usage')
            ->assertSee($type->label_en)
            ->assertSee($workflow->name);
        $buttonLinks = $this->links($buttons->html());
        $this->assertContains(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]), $buttonLinks);
        $this->assertWorkflowLink($workflow, $buttonLinks);

        $types = Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableColumnVisible('workflow_templates_usage')
            ->assertSee($workflow->name);
        $this->assertWorkflowLink($workflow, $this->links($types->html()));

        app()->setLocale('km');
        Livewire::test(ListWorkflowButtons::class)->assertSee($type->label_km)->assertSee($workflow->name);
    }

    public function test_read_only_viewers_see_usage_names_without_links_to_unauthorized_related_resources(): void
    {
        [$button, $type, $workflow] = $this->linkedConfiguration();
        $viewer = $this->user('usage-read-only');
        $viewer->assignRole(Role::findOrCreate('usage-read-only-role', 'web'));
        foreach (['ViewAny:WorkflowButton', 'ViewAny:WorkflowStageType'] as $permission) {
            $viewer->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->flushSession();
        $this->actingAs($viewer);
        $this->resetUsage();

        $buttons = Livewire::test(ListWorkflowButtons::class)->assertSee($type->label_en)->assertSee($workflow->name);
        $types = Livewire::test(ListWorkflowStageTypes::class)->assertSee($workflow->name);
        $links = array_merge($this->links($buttons->html()), $this->links($types->html()));
        $this->assertNotContains(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]), $links);
        foreach (['view', 'edit'] as $page) {
            $this->assertNotContains(WorkflowNotificationResource::getUrl($page, ['record' => $workflow]), $links);
        }
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]))->assertForbidden();
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $workflow]))->assertForbidden();
    }

    public function test_unused_configuration_renders_a_visible_placeholder_in_each_usage_column(): void
    {
        $button = $this->button('Unused usage button');
        $type = WorkflowStageType::query()->create(['label_en' => 'Unused usage stage', 'label_km' => 'ដំណាក់កាលមិនបានប្រើ', 'behavior' => 'review', 'enabled_actions' => ['accept']]);
        $this->resetUsage();
        $buttons = Livewire::test(ListWorkflowButtons::class);
        $types = Livewire::test(ListWorkflowStageTypes::class);

        foreach ([[$buttons, $button, 'stage_types_usage'], [$buttons, $button, 'workflow_templates_usage'], [$types, $type, 'workflow_templates_usage']] as [$component, $record, $name]) {
            $column = $component->instance()->getTable()->getColumn($name);
            $this->assertNotNull($column);
            $html = $column->record($record)->toHtml();
            $this->assertNotSame('', trim(html_entity_decode(strip_tags($html))));
            $this->assertSame([], $this->links($html));
        }
    }

    private function linkedConfiguration(): array
    {
        $button = $this->button('Linked usage button');
        $type = $this->type('Linked usage stage', $button, 'ដំណាក់កាលប្រើប្រាស់ភ្ជាប់');
        $workflow = $this->rawWorkflow('Linked usage workflow', [['stage_type' => 'review', 'stage_type_id' => $type->getKey(), 'buttons' => [$button->toDefinition()]]]);
        $this->resetUsage();

        return [$button, $type, $workflow];
    }

    private function rawWorkflow(string $name, array $stages): WorkflowNotification
    {
        $id = DB::table('workflow_notifications')->insertGetId([
            'name' => $name, 'stages' => json_encode(array_map(fn (array $stage): array => ['type' => 'stage', 'data' => $stage], $stages)),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return WorkflowNotification::query()->findOrFail($id);
    }

    private function type(string $label, WorkflowButton $button, ?string $khmer = null): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => $khmer ?? 'ខ្មែរ '.$label, 'behavior' => 'review', 'workflow_button_ids' => [$button->getKey()]]);
    }

    private function button(string $label): WorkflowButton
    {
        return WorkflowButton::query()->create(['label_en' => $label, 'action_key' => 'accept']);
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'password' => 'password',
            'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
    }

    private function resetUsage(): void
    {
        app()->forgetInstance(WorkflowConfigurationUsage::class);
    }

    private function links(string $html): array
    {
        $document = new DOMDocument;
        $document->loadHTML('<meta charset="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return array_map(fn ($link): string => $link->getAttribute('href'), iterator_to_array((new DOMXPath($document))->query('//a[@href]')));
    }

    private function assertWorkflowLink(WorkflowNotification $workflow, array $links): void
    {
        $allowed = array_map(fn (string $page): string => WorkflowNotificationResource::getUrl($page, ['record' => $workflow]), ['view', 'edit']);
        $this->assertNotEmpty(array_intersect($allowed, $links));
    }
}
