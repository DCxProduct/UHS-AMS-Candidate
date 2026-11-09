<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeDeletionTest extends TestCase
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
        $admin = $this->user('stage-deletion-admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public function test_admin_can_delete_unused_builtin_and_custom_types_from_their_row_actions(): void
    {
        $builtin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        $custom = $this->type('Unused row deletion type');

        foreach ([$builtin, $custom] as $type) {
            $this->assertFalse($type->inUse());
            $this->assertTrue($type->canDelete());
            $this->assertTrue(WorkflowStageTypeResource::canDelete($type));
            Livewire::test(ListWorkflowStageTypes::class)
                ->assertTableActionVisible('delete', $type)
                ->assertTableActionEnabled('delete', $type)
                ->callTableAction('delete', $type)->assertHasNoTableActionErrors();
            $this->assertModelMissing($type);
        }
    }

    public function test_staff_with_delete_permission_can_delete_unused_types_by_row_and_bulk_without_update_permission(): void
    {
        $staff = $this->staff('stage-deletion-staff', ['ViewAny', 'Delete']);
        $this->assertFalse($staff->can('Update:WorkflowStageType'));
        $builtin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableActionHidden('edit', $builtin)
            ->assertTableActionVisible('delete', $builtin)
            ->callTableAction('delete', $builtin)->assertHasNoTableActionErrors();
        $this->assertModelMissing($builtin);

        $nextBuiltin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        $custom = $this->type('Unused bulk deletion type');
        Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$nextBuiltin, $custom])->assertHasNoTableBulkActionErrors();
        $this->assertModelMissing($nextBuiltin);
        $this->assertModelMissing($custom);
    }

    public function test_admin_can_bulk_delete_every_unused_catalog_type(): void
    {
        $this->type('Extra unused catalog type');
        $types = WorkflowStageType::query()->get();
        $this->assertTrue($types->contains(fn (WorkflowStageType $type): bool => $type->is_system));
        $this->assertTrue($types->every(fn (WorkflowStageType $type): bool => ! $type->inUse()));

        Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', $types)->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseCount('workflow_stage_types', 0);
    }

    public function test_view_and_update_permissions_do_not_allow_row_or_bulk_delete_invocations(): void
    {
        $builtin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        $custom = $this->type('Unauthorized deletion target');

        foreach (['view' => ['ViewAny'], 'update' => ['ViewAny', 'Update']] as $name => $permissions) {
            $this->staff('stage-deletion-'.$name, $permissions);
            $this->assertFalse(WorkflowStageTypeResource::canDelete($builtin));
            $this->assertFalse(WorkflowStageTypeResource::canDeleteAny());
            Livewire::test(ListWorkflowStageTypes::class)
                ->assertTableActionHidden('delete', $builtin)
                ->assertTableActionHidden('delete', $custom)
                ->mountTableAction('delete', $builtin)->callMountedTableAction();
            Livewire::test(ListWorkflowStageTypes::class)
                ->assertTableBulkActionHidden('delete')
                ->mountTableBulkAction('delete', [$builtin, $custom])->callMountedTableBulkAction();

            $this->assertModelExists($builtin);
            $this->assertModelExists($custom);
        }
    }

    public function test_deleting_all_types_stays_empty_when_options_and_workflow_create_form_are_read(): void
    {
        $this->deleteUnusedCatalogThroughModelEvents();
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame([], WorkflowStageType::selectOptions());
            $this->assertSame([], WorkflowStageType::selectOptions(999999, 'verification'));
            foreach (array_keys(StageBehavior::options()) as $behavior) {
                $this->assertNull(WorkflowStageType::selectionForBehavior($behavior));
            }

            $component = Livewire::test(CreateWorkflowNotification::class);
            $stagePickers = collect($component->instance()->form->getFlatFields(withHidden: true))
                ->filter(fn ($field): bool => $field instanceof Select && str_ends_with($field->getStatePath(), '.stage_type_id'));
            $this->assertNotEmpty($stagePickers);
            foreach ($stagePickers as $picker) {
                $this->assertSame([], $picker->getOptions());
            }
            $this->assertSame(0, WorkflowNotification::query()->count());
            $this->assertDatabaseCount('workflow_stage_types', 0);
        }
    }

    public function test_a_new_custom_stage_can_be_created_from_a_button_after_the_catalog_is_cleared(): void
    {
        $this->deleteUnusedCatalogThroughModelEvents();
        $button = WorkflowButton::query()->create(['label_en' => 'My new check button', 'action_key' => 'accept']);
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm([
                'label_en' => 'My only stage', 'label_km' => 'ដំណាក់កាលតែមួយ',
                'workflow_button_ids' => [$button->getKey()], 'is_active' => true,
            ])
            ->call('create')->assertHasNoFormErrors();

        $type = WorkflowStageType::query()->sole();
        $this->assertFalse($type->is_system);
        $this->assertSame('review', $type->behavior);
        $this->assertSame([$button->getKey()], $type->workflow_button_ids);
        $this->assertSame([$type->getKey() => 'My only stage'], WorkflowStageType::selectOptions());
        $this->assertDatabaseCount('workflow_stage_types', 1);
    }

    public function test_deleting_a_type_no_longer_used_by_templates_preserves_applications_frozen_with_that_type(): void
    {
        $type = $this->type('Original frozen catalog type');
        $workflow = WorkflowNotification::query()->create(['name' => 'Original frozen catalog workflow', 'stages' => [[
            'type' => 'stage',
            'data' => ['stage_type_id' => $type->getKey(), 'stage_type' => 'review', 'stage_name' => 'Original review', 'responsible_role' => ['admin']],
        ]]]);
        $form = CustomForm::query()->create(['name' => 'Frozen deletion form', 'slug' => 'frozen-deletion-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']])->fresh();
        $snapshot = $entry->workflow_snapshot;
        $this->assertSame($type->getKey(), data_get($snapshot, 'stages.0.data.stage_type_id'));

        // The template no longer selects this type; the application's snapshot still does.
        $stages = $workflow->stages;
        unset($stages[0]['data']['stage_type_id']);
        $workflow->update(['stages' => $stages]);
        $templateBefore = $workflow->fresh()->getRawOriginal('stages');
        $entryBefore = $entry->getRawOriginal();
        $this->assertFalse($type->inUse());

        Livewire::test(ListWorkflowStageTypes::class)->callTableAction('delete', $type)->assertHasNoTableActionErrors();

        $this->assertModelMissing($type);
        $this->assertSame($templateBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame($snapshot['stages'], WorkflowNotification::forEntry($entry->fresh())->stages);
    }

    private function deleteUnusedCatalogThroughModelEvents(): void
    {
        foreach (WorkflowStageType::query()->get() as $type) {
            $this->assertFalse($type->inUse());
            $type->delete();
        }
        $this->assertDatabaseCount('workflow_stage_types', 0);
    }

    private function staff(string $username, array $verbs): User
    {
        $role = Role::findOrCreate($username.'-role', 'web');
        foreach ($verbs as $verb) {
            $role->givePermissionTo(Permission::findOrCreate($verb.':WorkflowStageType', 'web'));
        }
        $user = $this->user($username);
        $user->assignRole($role);
        $this->flushSession();
        $this->actingAs($user);

        return $user;
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'password' => 'password',
            'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
    }

    private function type(string $label): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => 'review', 'enabled_actions' => ['accept']]);
    }
}
