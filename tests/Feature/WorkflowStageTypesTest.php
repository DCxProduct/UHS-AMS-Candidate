<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Policies\WorkflowStageTypePolicy;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $this->admin = $this->user('stage-catalog-admin');
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web')
            ->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        $this->actingAs($this->admin);
    }

    public function test_admin_can_create_and_edit_localized_options_in_id_order_and_disable_them_without_losing_existing_selection(): void
    {
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Clinical review', 'label_km' => 'ការពិនិត្យគ្លីនិក', 'behavior' => 'review', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'Clinical review')->firstOrFail();
        $this->assertSame(WorkflowStageType::query()->where('is_active', true)->orderBy('id')->pluck('id')->all(), array_keys(WorkflowStageType::selectOptions()));
        $this->assertSame('Clinical review', WorkflowStageType::selectOptions()[$type->getKey()]);
        app()->setLocale('km');
        $this->assertSame('ការពិនិត្យគ្លីនិក', WorkflowStageType::selectOptions()[$type->getKey()]);

        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->assertFormFieldDisabled('behavior')
            ->fillForm(['label_en' => 'Renamed clinical review', 'label_km' => 'ការពិនិត្យថ្មី', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('review', $type->fresh()->behavior);
        $this->assertFalse($type->fresh()->is_active);
        $this->assertArrayNotHasKey($type->getKey(), WorkflowStageType::selectOptions());
        $this->assertStringContainsString('ការពិនិត្យថ្មី', WorkflowStageType::selectOptions($type->getKey())[$type->getKey()]);
    }

    public function test_catalog_uses_its_own_four_permissions_for_navigation_and_crud(): void
    {
        $operator = $this->user('stage-catalog-operator');
        $operator->assignRole(Role::findOrCreate('catalog_operator', 'web'));
        $operator->givePermissionTo(Permission::findOrCreate('ViewAny:WorkflowNotification', 'web'));
        $type = $this->type('Permission example');
        $policy = new WorkflowStageTypePolicy;
        $this->actingAs($operator);
        $this->assertFalse(WorkflowStageTypeResource::canAccess());
        $this->get(WorkflowStageTypeResource::getUrl('index'))->assertForbidden();
        $this->assertFalse($policy->create($operator));
        $this->assertFalse($policy->update($operator, $type));
        $this->assertFalse($policy->delete($operator, $type));

        $operator->givePermissionTo(Permission::findOrCreate('ViewAny:WorkflowStageType', 'web'));
        $this->assertTrue(WorkflowStageTypeResource::canAccess());
        $this->get(WorkflowStageTypeResource::getUrl('index'))->assertOk();
        $this->get(WorkflowStageTypeResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]))->assertForbidden();

        $operator->givePermissionTo(Permission::findOrCreate('Create:WorkflowStageType', 'web'));
        $this->assertTrue($policy->create($operator));
        $this->get(WorkflowStageTypeResource::getUrl('create'))->assertOk();
        $operator->givePermissionTo(Permission::findOrCreate('Update:WorkflowStageType', 'web'));
        $this->assertTrue($policy->update($operator, $type));
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]))->assertOk();
        $operator->givePermissionTo(Permission::findOrCreate('Delete:WorkflowStageType', 'web'));
        $this->assertTrue($policy->delete($operator, $type));
    }

    public function test_behavior_is_immutable_and_invalid_or_duplicate_catalog_definitions_are_rejected(): void
    {
        $type = $this->type('Immutable review');
        $this->assertValidation(fn () => $type->update(['behavior' => 'payment']), 'behavior');
        $this->assertSame('review', $type->fresh()->behavior);
        $this->assertValidation(fn () => $this->type('Unsupported behavior', 'unsupported'), 'behavior');

        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => $type->label_en, 'label_km' => $type->label_km, 'behavior' => 'review', 'is_active' => true])
            ->call('create')
            ->assertHasFormErrors(['label_en' => 'unique', 'label_km' => 'unique']);
    }

    public function test_builtin_and_referenced_types_cannot_be_deleted_but_unused_custom_types_can(): void
    {
        $builtin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        $used = $this->type('Referenced review');
        WorkflowNotification::query()->create(['name' => 'Referenced type workflow', 'stages' => [$this->stage($used)]]);
        foreach ([$builtin, $used] as $protected) {
            $this->assertFalse($protected->canDelete());
            $this->assertFalse(WorkflowStageTypeResource::canDelete($protected));
            $this->assertValidation(fn () => $protected->delete(), 'stage_type');
            Livewire::test(ListWorkflowStageTypes::class)
                ->assertTableActionHidden('delete', $protected)
                ->mountTableAction('delete', $protected)->callMountedTableAction();
            $this->assertModelExists($protected);
            Livewire::test(EditWorkflowStageType::class, ['record' => $protected->getRouteKey()])->assertActionHidden('delete');
        }
        $unused = $this->type('Unused review');
        Livewire::test(EditWorkflowStageType::class, ['record' => $unused->getRouteKey()])->callAction('delete');
        $this->assertModelMissing($unused);
    }

    public function test_selected_catalog_type_controls_canonical_behavior_and_copied_labels_even_when_hidden_values_are_spoofed(): void
    {
        $type = $this->type('Document verification');
        $stage = $this->stage($type);
        $stage['data']['stage_type'] = 'payment';
        $stage['data']['stage_type_labels'] = ['en' => 'Forged label', 'km' => 'ក្លែងក្លាយ'];
        $workflow = WorkflowNotification::query()->create(['name' => 'Canonical binding', 'stages' => [$stage]]);
        $saved = $workflow->fresh()->stages[0]['data'];

        $this->assertSame('review', $saved['stage_type']);
        $this->assertSame($type->getKey(), $saved['stage_type_id']);
        $this->assertSame($type->labels(), $saved['stage_type_labels']);
        $this->assertSame('Keep this custom stage name', $saved['stage_name']);

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm(['name' => 'Catalog selection from workflow form', 'stages' => ['review-stage' => $this->stage($type)]])
            ->call('create')
            ->assertHasNoFormErrors();
        $fromForm = WorkflowNotification::query()->where('name', 'Catalog selection from workflow form')->firstOrFail();
        $this->assertSame('review', data_get($fromForm->stages, '0.data.stage_type'));
        $this->assertSame($type->getKey(), data_get($fromForm->stages, '0.data.stage_type_id'));
    }

    public function test_two_aliases_of_the_same_engine_gate_and_unknown_catalog_ids_are_rejected(): void
    {
        $first = $this->type('First review alias');
        $second = $this->type('Second review alias');
        $this->assertValidation(fn () => WorkflowNotification::query()->create([
            'name' => 'Duplicate review behavior', 'stages' => [$this->stage($first), $this->stage($second)],
        ]), 'stages');
        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm(['name' => 'Duplicate alias workflow form', 'stages' => ['first' => $this->stage($first), 'second' => $this->stage($second)]])
            ->call('create')
            ->assertHasFormErrors(['stages']);
        $unknown = $this->stage($first);
        $unknown['data']['stage_type_id'] = 999999;
        $this->assertValidation(fn () => WorkflowNotification::query()->create(['name' => 'Unknown catalog selection', 'stages' => [$unknown]]), 'stages');
        $this->assertDatabaseCount('workflow_notifications', 0);
    }

    public function test_selecting_another_label_for_the_same_behavior_preserves_custom_name_and_roles_but_behavior_changes_keep_existing_resets(): void
    {
        $first = $this->type('Initial review label');
        $second = $this->type('Alternative review label');
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', $first->getKey())
            ->assertSet($path.'.stage_type', 'review')
            ->assertSet($path.'.stage_name', 'Initial review label')
            ->set($path.'.stage_name', 'My custom review stage')
            ->set($path.'.responsible_role', ['registrar_officer'])
            ->set($path.'.review_actions.accept.status_message', 'Keep this acceptance message')
            ->set($path.'.stage_type_id', $second->getKey())
            ->assertSet($path.'.stage_type', 'review')
            ->assertSet($path.'.stage_name', 'My custom review stage')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->assertSet($path.'.review_actions.accept.status_message', 'Keep this acceptance message')
            ->set($path.'.stage_type_id', WorkflowStageType::selectionForBehavior('payment'))
            ->assertSet($path.'.stage_type', 'payment')
            ->assertSet($path.'.stage_name', 'My custom review stage')
            ->assertSet($path.'.responsible_role', [])
            ->assertSet($path.'.payment_source', 'system');
    }

    public function test_legacy_workflow_edit_keeps_canonical_values_and_settings_without_adding_catalog_metadata(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Legacy workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'First legacy review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer']]],
            ['type' => 'stage', 'data' => ['stage_name' => 'Second legacy review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer']]],
        ]]);
        $original = $workflow->stages;

        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])
            ->fillForm(['name' => 'Renamed legacy workflow'])
            ->call('save')
            ->assertHasNoFormErrors();

        foreach ($workflow->fresh()->stages as $index => $stage) {
            foreach (Arr::dot($original[$index]['data']) as $key => $value) {
                $this->assertSame($value, data_get($stage['data'], $key));
            }
            $this->assertSame($original[$index]['data']['responsible_role'], $stage['data']['responsible_role']);
            $this->assertArrayNotHasKey('stage_type_id', $stage['data']);
            $this->assertArrayNotHasKey('stage_type_labels', $stage['data']);
        }
        $this->assertArrayHasKey('legacy:approval', WorkflowStageType::selectOptions('legacy:approval', 'approval'));
        $this->assertArrayHasKey('legacy:rejected', WorkflowStageType::selectOptions('legacy:rejected', 'rejected'));
    }

    public function test_existing_application_snapshots_and_engine_progress_stay_frozen_after_catalog_rename_disable_and_deletion(): void
    {
        $type = $this->type('Frozen review label');
        $workflow = WorkflowNotification::query()->create(['name' => 'Frozen catalog workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
            $this->stage($type),
        ]]);
        $form = CustomForm::query()->create(['name' => 'Catalog snapshot form', 'slug' => 'catalog-snapshot', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $before = $entry->fresh()->getRawOriginal();
        $savedStage = $entry->workflow_snapshot['stages'][1]['data'];
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $type->update(['label_en' => 'Updated review label', 'label_km' => 'ការពិនិត្យដែលបានកែ', 'is_active' => false]);

        $this->assertSame('Frozen review label', WorkflowStageType::labelForStage($savedStage, preferSaved: true));
        $this->assertSame('Updated review label', WorkflowStageType::labelForStage($workflow->stages[1]['data']));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $workflow->delete();
        $this->assertTrue($type->fresh()->canDelete());
        $type->delete();

        $this->assertSame('Frozen review label', WorkflowStageType::labelForStage($savedStage, preferSaved: true));
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
    }

    public function test_reapplying_default_catalog_migration_preserves_renamed_disabled_records_and_existing_workflows(): void
    {
        $builtin = WorkflowStageType::query()->where('behavior', 'review')->where('is_system', true)->firstOrFail();
        $builtin->update(['label_en' => 'Renamed system review', 'label_km' => 'ការពិនិត្យប្រព័ន្ធថ្មី', 'is_active' => false]);
        $custom = $this->type('Migration custom alias');
        $workflow = WorkflowNotification::query()->create(['name' => 'Untouched legacy stage', 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Custom old review', 'stage_type' => 'review']]]]);
        $before = $workflow->getRawOriginal('stages');
        $migration = require database_path('migrations/2026_11_04_000002_create_workflow_stage_types_table.php');
        $migration->up();
        $migration->up();

        $this->assertSame('Renamed system review', $builtin->fresh()->label_en);
        $this->assertFalse($builtin->fresh()->is_active);
        $this->assertSame(5, WorkflowStageType::query()->where('is_system', true)->count());
        $this->assertModelExists($custom);
        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame(StageBehavior::Review->label(), WorkflowStageType::labelForStage(['stage_type' => 'review'], preferSaved: true));
    }

    private function type(string $label, string $behavior = 'review'): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => $behavior, 'is_active' => true]);
    }

    private function stage(WorkflowStageType $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Keep this custom stage name', 'stage_type' => $type->behavior, 'stage_type_id' => $type->getKey(), 'responsible_role' => ['registrar_officer']]];
    }

    private function user(string $username): User
    {
        return User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }

    private function assertValidation(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected validation error for '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
