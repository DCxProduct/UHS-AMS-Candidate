<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ListWorkflowNotifications;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Services\WorkflowNotificationDuplicator;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Once;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;
use Throwable;

class WorkflowNotificationDuplicationTest extends TestCase
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
        $this->actor('duplication-admin', ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Duplicate']);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app()->setLocale('en');
        parent::tearDown();
    }

    public function test_duplicate_preserves_exact_legacy_configuration_despite_catalog_drift_and_does_not_copy_assignments_or_history(): void
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Original accept button', 'label_km' => 'ទទួលពាក្យ', 'action_key' => 'accept']);
        $catalog = WorkflowStageType::query()->create(['label_en' => 'Original review catalog', 'label_km' => 'ពិនិត្យពាក្យ', 'behavior' => 'review', 'enabled_actions' => ['accept']]);
        $stages = [
            ['stage_name' => 'Stored flat review', 'stage_type' => 'review', 'stage_type_id' => $catalog->id,
                'responsible_role' => 'registrar_officer', 'enabled_actions' => ['accept'], 'buttons' => [$button->toDefinition()],
                'review_actions' => ['accept' => ['status_message' => 'Stored checked wording', 'notification_channels' => ['email', 'sms'], 'email_template_id' => 51]],
                'legacy_settings' => ['preserve' => ['channel' => 'legacy-channel', 'order' => [3, 1, 2]]]],
            ['type' => 'stage', 'data' => ['stage_name' => 'Stored AMS payment', 'stage_type' => 'payment', 'payment_source' => 'ams',
                'responsible_role' => ['cashier_officer', 'registrar_officer'], 'notification_channels' => ['system', 'email'],
                'notification_message' => 'Stored payment notification', 'payment_statuses' => ['unpaid_status_message' => 'Stored waiting wording'],
                'ams_settings' => ['integration_key' => 'legacy-key', 'payload_fields' => ['invoice', 'candidate_id']]]],
            ['type' => 'stage', 'data' => ['stage_name' => 'Keep unconfigured stage', 'stage_type' => null, 'responsible_role' => null]],
        ];
        // Represent a previously stored template without rewriting it through today's catalog defaults.
        $source = WorkflowNotification::withoutEvents(fn (): WorkflowNotification => WorkflowNotification::query()->create(['name' => 'Stored enrollment', 'stages' => $stages]));
        $form = CustomForm::query()->create(['name' => 'Assigned enrollment', 'slug' => 'assigned-enrollment', 'is_active' => true]);
        $source->forms()->attach($form);
        $entry = CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => auth()->id(), 'review_status' => 'approved',
            'data' => ['registration_status' => 'approved', 'workflow_progress' => ['submitted' => true, 'reviewed' => true]],
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => true, 'workflow_id' => $source->id, 'stages' => $stages],
        ]));
        Payment::query()->create(['custom_form_entry_id' => $entry->id, 'form_id' => $form->id, 'users_id' => auth()->id(), 'receipt_number' => 'DUPLICATION-UNCHANGED', 'amount_usd' => 5, 'status_payt' => 'paid']);
        $button->update(['label_en' => 'Changed current button', 'color' => 'danger']);
        $catalog->update(['label_en' => 'Changed current review catalog', 'enabled_actions' => ['reject']]);
        $sourceBefore = $source->fresh()->getRawOriginal();
        $before = $this->historySnapshot();
        $createdIds = [];
        WorkflowNotification::created(static function (WorkflowNotification $created) use (&$createdIds): void {
            $createdIds[] = $created->id;
        });

        $copy = app(WorkflowNotificationDuplicator::class)->duplicate($source);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('Stored enrollment (Copy)', $copy->name);
        $this->assertSame($stages, $copy->fresh()->stages);
        $this->assertSame([$copy->id], $createdIds);
        $this->assertSame([$form->id], $source->fresh()->forms->modelKeys());
        $this->assertCount(0, $copy->fresh()->forms);
        $this->assertSame($sourceBefore, $source->fresh()->getRawOriginal());
        $this->assertSame($before, $this->historySnapshot());
        $this->assertDatabaseCount('workflow_notifications', 2);
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_repeated_copies_have_unique_names_within_255_characters_and_refresh_stale_source_models(): void
    {
        $source = $this->workflow('Original source');
        $latestStages = $source->stages;
        data_set($latestStages, '0.data.stage_name', 'Latest stored review');
        $source->fresh()->update(['name' => 'Latest source', 'stages' => $latestStages]);
        $first = app(WorkflowNotificationDuplicator::class)->duplicate($source);
        $second = app(WorkflowNotificationDuplicator::class)->duplicate($source);
        $this->assertSame('Latest source (Copy)', $first->name);
        $this->assertSame('Latest source (Copy 2)', $second->name);
        $this->assertSame($latestStages, $first->stages);
        $this->assertSame($latestStages, $second->stages);
        $this->assertSame('Latest source', $source->fresh()->name);
        $longName = str_repeat('ក', 255);
        $long = $this->workflow($longName);
        $longFirst = app(WorkflowNotificationDuplicator::class)->duplicate($long);
        $longSecond = app(WorkflowNotificationDuplicator::class)->duplicate($long);
        $this->assertLessThanOrEqual(255, mb_strlen($longFirst->name));
        $this->assertLessThanOrEqual(255, mb_strlen($longSecond->name));
        $this->assertStringEndsWith(' (Copy)', $longFirst->name);
        $this->assertStringEndsWith(' (Copy 2)', $longSecond->name);
        $this->assertNotSame($longFirst->name, $longSecond->name);
        $this->assertSame($longName, $long->fresh()->name);
        $this->assertDatabaseCount('workflow_notifications', 6);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_one_click_duplicate_has_translated_label_and_redirects_to_edit_when_update_is_allowed(string $locale): void
    {
        app()->setLocale($locale);
        $source = $this->workflow('UI enrollment');
        $component = Livewire::test(ListWorkflowNotifications::class)->assertTableActionVisible('duplicate', $source)
            ->assertTableActionHasLabel('duplicate', __('workflow_notifications.actions.duplicate'), $source);
        $label = $component->instance()->getTable()->getAction('duplicate')->getLabel();
        $this->assertStringNotContainsString('workflow_notifications.', $label);
        if ($locale === 'en') {
            $this->assertSame('Duplicate Template', $label);
        } else {
            $this->assertMatchesRegularExpression('/[\x{1780}-\x{17FF}]/u', $label);
        }
        $component->assertTableActionHasLabel('view', $locale === 'en' ? 'View' : 'មើល', $source);
        $this->assertFalse($component->instance()->getTable()->getAction('duplicate')->isConfirmationRequired());
        $component->callTableAction('duplicate', $source);
        $copy = WorkflowNotification::query()->whereKeyNot($source->id)->sole();
        $component->assertRedirect(WorkflowNotificationResource::getUrl('edit', ['record' => $copy]));
        $this->assertSame($source->stages, $copy->stages);
        $this->assertDatabaseCount('workflow_notifications', 2);
    }

    public function test_duplicate_permission_without_view_create_or_update_can_copy_and_returns_to_the_list(): void
    {
        $role = $this->actor('duplicate-creator', ['ViewAny', 'Duplicate'])->roles->sole();
        $source = $this->workflow('Creator enrollment');
        Livewire::test(ListWorkflowNotifications::class)->assertTableActionVisible('duplicate', $source)
            ->assertTableActionHidden('view', $source)
            ->callTableAction('duplicate', $source)->assertRedirect(WorkflowNotificationResource::getUrl('index'));
        $this->assertDatabaseCount('workflow_notifications', 2);
        $this->assertEqualsCanonicalizing(['ViewAny:WorkflowNotification', 'Duplicate:WorkflowNotification'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_view_only_forged_and_revoked_actions_and_direct_service_calls_cannot_create_a_copy(): void
    {
        $source = $this->workflow('Protected enrollment');
        $this->actor('duplicate-viewer', ['ViewAny']);
        $component = Livewire::test(ListWorkflowNotifications::class)->assertTableActionHidden('duplicate', $source);
        $component->call('mountAction', 'duplicate', [], ['table' => true, 'recordKey' => $source->id])
            ->assertSet('mountedActions', [])->call('callMountedAction');
        $this->assertForbidden(fn () => app(WorkflowNotificationDuplicator::class)->duplicate($source));
        $this->actor('duplicate-create-only', ['ViewAny', 'Create']);
        Livewire::test(ListWorkflowNotifications::class)->assertTableActionHidden('duplicate', $source);
        $this->assertForbidden(fn () => app(WorkflowNotificationDuplicator::class)->duplicate($source));
        $this->actor('duplicate-no-view', ['Duplicate']);
        $this->assertForbidden(fn () => app(WorkflowNotificationDuplicator::class)->duplicate($source));
        $actor = $this->actor('duplicate-revoked', ['ViewAny', 'Duplicate']);
        $component = Livewire::test(ListWorkflowNotifications::class)->assertTableActionVisible('duplicate', $source);
        $actor->roles->sole()->revokePermissionTo('Duplicate:WorkflowNotification');
        $actor->unsetRelation('roles')->unsetRelation('permissions');
        $component->set('mountedActions', [['name' => 'duplicate', 'arguments' => [], 'context' => ['table' => true, 'recordKey' => $source->id]]])
            ->callMountedAction();
        $this->assertDatabaseCount('workflow_notifications', 1);
        $this->assertForbidden(fn () => app(WorkflowNotificationDuplicator::class)->duplicate($source));
        $this->assertDatabaseCount('workflow_notifications', 1);
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_read_permission_keeps_view_hidden_and_detail_forbidden_until_view_is_granted(): void
    {
        $actor = $this->actor('workflow-read-only', ['ViewAny']);
        $source = $this->workflow('Separate view permission');
        $before = $source->fresh()->getRawOriginal();
        Livewire::test(ListWorkflowNotifications::class)->assertSuccessful()->assertCanSeeTableRecords([$source])
            ->assertTableActionHidden('view', $source)->assertTableActionHidden('duplicate', $source);
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $source]))->assertForbidden();

        $actor->roles->sole()->givePermissionTo(Permission::findOrCreate('View:WorkflowNotification', 'web'));
        $actor->unsetRelation('roles')->unsetRelation('permissions');
        $modal = Livewire::test(ListWorkflowNotifications::class)->assertTableActionVisible('view', $source)
            ->assertTableActionHidden('duplicate', $source)->mountTableAction('view', $source)
            ->assertActionMounted(TestAction::make('view')->table($source));
        $this->assertStringContainsString('Review', (string) $modal->instance()->getMountedAction()->getModalContent());
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $source]))->assertOk()->assertSee('Separate view permission');
        $this->assertForbidden(fn () => app(WorkflowNotificationDuplicator::class)->duplicate($source));
        $this->assertSame($before, $source->fresh()->getRawOriginal());
        $this->assertDatabaseCount('workflow_notifications', 1);
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_role_form_exposes_view_and_duplicate_in_both_languages_and_saves_only_selected_rights(): void
    {
        $editor = $this->actor('duplicate-role-editor', []);
        $editor->roles->sole()->givePermissionTo([
            Permission::findOrCreate('ViewAny:Role', 'web'), Permission::findOrCreate('Update:Role', 'web'),
        ]);
        $target = Role::query()->create([
            'name' => 'workflow-duplicate-target', 'guard_name' => 'web', 'label_en' => 'Workflow duplicate target', 'role_type_key' => 'staff',
        ]);
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            Once::flush();
            $component = Livewire::test(EditRole::class, ['record' => $target->getRouteKey()]);
            $field = collect($component->instance()->form->getFlatFields(withHidden: true))
                ->first(fn ($field): bool => $field instanceof CheckboxList && $field->getName() === WorkflowNotificationResource::class);
            $this->assertNotNull($field);
            $options = $field->getOptions();
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Duplicate'] as $action) {
                $this->assertArrayHasKey($action.':WorkflowNotification', $options);
            }
            $this->assertSame($locale === 'en' ? 'View' : 'មើល', (string) $options['View:WorkflowNotification']);
            $label = (string) $options['Duplicate:WorkflowNotification'];
            if ($locale === 'en') {
                $this->assertSame('Duplicate Template', $label);
            } else {
                $this->assertMatchesRegularExpression('/[\x{1780}-\x{17FF}]/u', $label);
            }
        }
        $selected = ['ViewAny:WorkflowNotification', 'View:WorkflowNotification', 'Duplicate:WorkflowNotification'];
        $component->set($field->getStatePath(), $selected)->call('save')->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing($selected, $target->fresh()->permissions->pluck('name')->all());
        $assigned = $this->actor('duplicate-assigned-user', []);
        $assigned->syncRoles($target->fresh());
        $this->assertTrue($assigned->can('Duplicate:WorkflowNotification'));
        $this->assertTrue($assigned->can('View:WorkflowNotification'));
        $this->assertFalse($assigned->can('Create:WorkflowNotification'));
        $this->assertFalse($assigned->can('Update:WorkflowNotification'));
    }

    public function test_permission_sync_registers_duplicate_idempotently_without_granting_it_to_existing_staff(): void
    {
        $role = Role::findOrCreate('workflow-existing-staff', 'web');
        $existing = collect(['ViewAny', 'Create', 'Update', 'Delete'])->map(fn (string $action): Permission => Permission::findOrCreate($action.':WorkflowNotification', 'web'));
        $role->givePermissionTo($existing);
        $before = $role->fresh()->permissions->pluck('id')->sort()->values()->all();
        $apiPermission = Permission::findOrCreate('Duplicate:WorkflowNotification', 'api');
        $apiView = Permission::findOrCreate('View:WorkflowNotification', 'api');

        FilamentActionPermissions::sync();
        FilamentActionPermissions::sync();

        $this->assertSame(1, Permission::query()->where('guard_name', 'web')->where('name', 'Duplicate:WorkflowNotification')->count());
        $this->assertContains('Duplicate:WorkflowNotification', FilamentActionPermissions::all());
        $this->assertSame(1, Permission::query()->where('guard_name', 'web')->where('name', 'View:WorkflowNotification')->count());
        $this->assertContains('View:WorkflowNotification', FilamentActionPermissions::all());
        $this->assertSame($before, $role->fresh()->permissions->pluck('id')->sort()->values()->all());
        $this->assertFalse($role->fresh()->hasPermissionTo('Duplicate:WorkflowNotification'));
        $this->assertFalse($role->fresh()->hasPermissionTo('View:WorkflowNotification'));
        $this->assertDatabaseHas('permissions', ['id' => $apiView->id, 'name' => 'View:WorkflowNotification', 'guard_name' => 'api']);
        $this->assertDatabaseHas('permissions', ['id' => $apiPermission->id, 'name' => 'Duplicate:WorkflowNotification', 'guard_name' => 'api']);
    }

    private function assertForbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Duplicating without the dedicated duplicate and menu access permissions must be denied.');
        } catch (Throwable $error) {
            if ($error instanceof AuthorizationException) {
                $this->assertSame(403, $error->status() ?? 403);
            } else {
                $this->assertInstanceOf(HttpExceptionInterface::class, $error);
                $this->assertSame(403, $error->getStatusCode());
            }
        }
    }

    private function historySnapshot(): array
    {
        $snapshot = [];
        foreach (['workflow_notification_forms', 'custom_form_entries', 'payments', 'notifications'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
        }

        return $snapshot;
    }

    private function workflow(string $name): WorkflowNotification
    {
        return WorkflowNotification::query()->create(['name' => $name, 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']]]]);
    }

    private function actor(string $username, array $actions): User
    {
        $role = Role::findOrCreate($username.'-role', 'web');
        foreach ($actions as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':WorkflowNotification', 'web'));
        }
        $user = User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }
}
