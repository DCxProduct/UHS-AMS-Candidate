<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType as StageTypeCatalog;
use App\Services\CandidatePaymentRecorder;
use App\Services\WorkflowCandidateVerifier;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowVerificationStageTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $candidate;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->staff = $this->user('admin');
        $this->authorizeStaff($this->staff, 'registrar_officer');
        $this->actingAs($this->staff);
        $this->candidate = $this->user('student');
        $this->major = Major::query()->create([
            'key' => 'verified-nurse', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true,
        ]);
    }

    public function test_catalog_and_workflow_forms_save_a_verification_only_definition_and_its_notification_settings(): void
    {
        $admin = $this->user('admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        foreach (['en' => 'Application Received', 'km' => 'បានទទួលពាក្យស្នើសុំ'] as $locale => $label) {
            app()->setLocale($locale);
            $this->assertSame($label, __('workflow_notifications.actions.verify'));
            $this->assertSame($label, WorkflowStageType::Verification->label());
            $catalogForm = Livewire::test(CreateWorkflowStageType::class);
            $field = $catalogForm->instance()->form->getFlatFields()['enabled_actions'];
            $this->assertSame($label, $field->getOptions()['verify']);
            $catalogForm->assertSee($label);
        }
        app()->setLocale('en');
        $builtin = StageTypeCatalog::query()->where('behavior', 'verification')->where('is_system', true)->firstOrFail();
        $builtin->update(['is_active' => false, 'stage_settings' => ['status_message' => 'Saved receipt wording']]);
        DB::table('workflow_stage_types')->where('id', $builtin->id)->update([
            'label_en' => 'Registrar Verification', 'label_km' => 'ការផ្ទៀងផ្ទាត់ដោយការិយាល័យចុះឈ្មោះ',
        ]);
        $settings = $builtin->fresh()->getRawOriginal('stage_settings');
        StageTypeCatalog::selectOptions($builtin->id);
        $this->assertSame($builtin->id, StageTypeCatalog::selectionForBehavior('verification'));
        $this->assertSame(['en' => 'Application Received', 'km' => 'បានទទួលពាក្យស្នើសុំ'], $builtin->fresh()->labels());
        $this->assertFalse($builtin->fresh()->is_active);
        $this->assertSame($settings, $builtin->fresh()->getRawOriginal('stage_settings'));
        $builtin->update(['label_en' => 'Custom receipt name', 'label_km' => 'ឈ្មោះទទួលពាក្យផ្ទាល់ខ្លួន']);
        $customized = $builtin->fresh()->getRawOriginal();
        StageTypeCatalog::selectOptions($builtin->id);
        $this->assertSame($customized, $builtin->fresh()->getRawOriginal());

        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Registrar verification', 'label_km' => 'ការផ្ទៀងផ្ទាត់របស់មន្ត្រី'])
            ->set('data.enabled_actions', ['verify'])->assertSet('data.behavior', 'verification')
            ->call('create')->assertHasNoFormErrors();
        $type = StageTypeCatalog::query()->where('label_en', 'Registrar verification')->firstOrFail();
        $this->assertSame(['verify'], $type->enabledActionKeys());
        $this->assertSame(['verify'], array_keys(WorkflowStageTypeSettings::actions('verification')));
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.label_en', 'Confirmed application details')->call('save')->assertHasNoFormErrors();
        $this->assertSame('verification', $type->fresh()->behavior);
        $this->assertSame(['verify'], $type->fresh()->enabled_actions);

        $stages = $this->stages();
        $stages[1]['data']['stage_type'] = null;
        $stages[1]['data']['responsible_role'] = [];
        $component = Livewire::test(CreateWorkflowNotification::class)->set('data.stages', [])
            ->fillForm(['name' => 'Configurable verification', 'stages' => $stages]);
        $keys = array_keys($component->get('data.stages'));
        $path = 'data.stages.'.$keys[1].'.data';
        foreach ($keys as $index => $key) {
            if ($index !== 1) {
                $component->set('data.stages.'.$key.'.data.responsible_role', ['registrar_officer']);
            }
        }
        $component->set($path.'.stage_type_id', $type->id)->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->where('name', 'Configurable verification')->firstOrFail();
        $this->assertNull(data_get($workflow->stages, '1.data.responsible_role'));
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $keys = array_keys($component->get('data.stages'));
        $path = 'data.stages.'.$keys[1].'.data';
        $component->set($path.'.responsible_role', ['registrar_officer'])
            ->set($path.'.status_message', 'Application verified')
            ->set($path.'.notification_message', 'Your application details have been verified.')
            ->set($path.'.notification_channels', ['system'])
            ->call('save')->assertHasNoFormErrors();
        $saved = data_get($workflow->fresh()->stages, '1.data');
        $this->assertSame('verification', $saved['stage_type']);
        $this->assertSame($type->id, $saved['stage_type_id']);
        $this->assertSame(['verify'], $saved['enabled_actions']);
        $this->assertSame(['registrar_officer'], $saved['responsible_role']);
        $this->assertSame('Application verified', $saved['status_message']);
        $this->assertSame('Your application details have been verified.', $saved['notification_message']);
        $workflow->delete();
        Livewire::test(ListWorkflowStageTypes::class)->callTableAction('delete', $type);
        $this->assertModelMissing($type);

        foreach ([[$this->stages()[1], $this->stages()[1]], [$this->stages()[1], ['type' => 'stage', 'data' => ['stage_type' => 'unsupported_stage']]]] as $invalidTypes) {
            try {
                WorkflowNotification::query()->create(['name' => 'Invalid verification '.uniqid(), 'stages' => $invalidTypes]);
                $this->fail('Workflow stage types must remain unique and supported.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('stages', $exception->errors());
            }
        }
    }

    public static function workflowSavePages(): array
    {
        return [
            'create in English' => ['en', false],
            'edit in English' => ['en', true],
            'create in Khmer' => ['km', false],
            'edit in Khmer' => ['km', true],
        ];
    }

    #[DataProvider('workflowSavePages')]
    public function test_receipt_before_review_saves_in_both_locales_and_can_be_reordered_without_relaxing_type_validation(string $locale, bool $editing): void
    {
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        app()->setLocale($locale);
        [$review, $receipt] = array_slice($this->stages(), 0, 2);
        $review['data']['review_actions']['accept'] = [
            'status_message' => 'Ready for receipt',
            'notification_message' => 'Please bring your application.',
            'staff_notification_message' => '',
            'staff_recipient_roles' => [],
        ];
        $validStages = ['review' => $review, 'receipt' => $receipt];
        $name = 'Workflow save feedback';
        $workflow = $editing ? WorkflowNotification::query()->create(['name' => $name, 'stages' => array_values($validStages)]) : null;
        $component = Livewire::test(
            $editing ? EditWorkflowNotification::class : CreateWorkflowNotification::class,
            $editing ? ['record' => $workflow->getRouteKey()] : [],
        )->set('data.stages', [])->fillForm([
            'name' => $name,
            'stages' => ['receipt' => $receipt, 'review' => $review],
        ]);
        $component->call($editing ? 'save' : 'create')->assertHasNoFormErrors();
        $saved = WorkflowNotification::query()->where('name', $name)->sole();
        $this->assertSame(['verification', 'review'], array_column(array_column($saved->stages, 'data'), 'stage_type'));
        $this->assertSame('Please bring your application.', data_get($saved->stages, '1.data.review_actions.accept.notification_message'));
        $this->assertEmpty(data_get($saved->stages, '1.data.review_actions.accept.staff_notification_message'));
        $this->assertEmpty(data_get($saved->stages, '1.data.review_actions.accept.staff_recipient_roles'));

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $saved->getRouteKey()]);
        $component->callAction(TestAction::make('moveUp')
            ->schemaComponent('stages-section.stages', schema: 'form')
            ->arguments(['item' => array_keys($component->get('data.stages'))[1]]))
            ->call('save')
            ->assertHasNoFormErrors();
        $saved->refresh();
        $this->assertSame(['review', 'verification'], array_column(array_column($saved->stages, 'data'), 'stage_type'));
        $this->assertSame('Please bring your application.', data_get($saved->stages, '0.data.review_actions.accept.notification_message'));
        $this->assertEmpty(data_get($saved->stages, '0.data.review_actions.accept.staff_notification_message'));
        $this->assertEmpty(data_get($saved->stages, '0.data.review_actions.accept.staff_recipient_roles'));
        $before = $saved->getRawOriginal('stages');
        foreach ([['receipt' => $receipt, 'duplicate' => $receipt], ['review' => $review, 'unknown' => ['type' => 'stage', 'data' => ['stage_name' => 'Unsupported', 'stage_type' => 'unsupported_stage']]]] as $invalidStages) {
            Livewire::test(EditWorkflowNotification::class, ['record' => $saved->getRouteKey()])
                ->set('data.stages', [])->fillForm(['name' => $name, 'stages' => $invalidStages])
                ->call('save')->assertHasFormErrors(['stages']);
            $this->assertSame($before, $saved->fresh()->getRawOriginal('stages'));
        }
    }

    public function test_catalog_and_workflow_picker_display_review_second_and_received_third_without_reordering_saved_applications(): void
    {
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $builtins = DB::table('workflow_stage_types')->where('is_system', true)->get()->keyBy('behavior');
        DB::table('workflow_stage_types')->where('is_system', true)->delete();
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed', 'verification'] as $index => $behavior) {
            DB::table('workflow_stage_types')->insert(array_replace((array) $builtins[$behavior], ['id' => $index + 1]));
        }
        $firstCustom = StageTypeCatalog::query()->create([
            'label_en' => 'Custom review', 'label_km' => 'ការពិនិត្យផ្ទាល់ខ្លួន', 'behavior' => 'review', 'is_active' => true,
        ]);
        $secondCustom = StageTypeCatalog::query()->create([
            'label_en' => 'Custom submission', 'label_km' => 'ការដាក់ស្នើផ្ទាល់ខ្លួន', 'behavior' => 'form_submission', 'is_active' => true,
        ]);
        $expected = StageTypeCatalog::query()->whereIn('id', [1, 2, 6, 3, 4, 5])->get()->keyBy('id');
        $expected = collect([1, 2, 6, 3, 4, 5])->map(fn (int $id) => $expected[$id])->push($firstCustom, $secondCustom);
        $expectedIds = $expected->pluck('id')->all();
        $this->assertSame('review', $expected[1]->behavior);
        $this->assertSame('verification', $expected[2]->behavior);
        $form = $this->form();
        $stages = $this->stages();
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Existing payment-first application',
            'stages' => [$stages[2], $stages[0], $stages[1], $stages[3], $stages[4]],
        ]);
        $workflow->forms()->attach($form);
        $entry = $this->entry($form);
        $catalogBefore = DB::table('workflow_stage_types')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $workflowBefore = $workflow->getRawOriginal('stages');
        $entryBefore = $this->row($entry);

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(ListWorkflowStageTypes::class)->assertCanSeeTableRecords($expected, inOrder: true);
            $this->assertSame($expectedIds, $component->instance()->getTableRecords()->pluck('id')->all());
            $this->assertSame($expectedIds, array_keys(StageTypeCatalog::selectOptions()));
            $workflowForm = Livewire::test(CreateWorkflowNotification::class);
            $field = collect($workflowForm->instance()->form->getFlatFields())
                ->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.stage_type_id'));
            $this->assertNotNull($field);
            $this->assertSame($expectedIds, array_keys($field->getOptions()));
        }

        $this->assertSame($catalogBefore, DB::table('workflow_stage_types')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($workflowBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $this->row($entry));
    }

    public static function reviewPaths(): array
    {
        return ['table' => ['row'], 'PDF modal' => ['modal'], 'PDF HTTP approval' => ['http']];
    }

    #[DataProvider('reviewPaths')]
    public function test_checked_application_waits_for_confirmed_verification_before_id_and_payment_and_notifies_once(string $path): void
    {
        [$form, $workflow] = $this->workflow();
        $entry = $this->entry($form);
        $this->review($entry, $path);
        $this->assertSame(2, $entry->fresh()->workflow_snapshot['schema_version']);
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertNull($entry->fresh()->candidate_id);
        $this->assertFalse(data_get($entry->data, 'workflow_progress.verification_completed'));
        $this->assertSame(__('workflow_notifications.status_defaults.verification'), WorkflowStageMessages::statusText($entry));
        $this->assertFalse(PaymentStatusListResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $snapshot = $entry->fresh()->getRawOriginal('workflow_snapshot');
        $stages = $workflow->stages;
        $stages[1]['data']['notification_message'] = 'Changed live verification notice';
        $workflow->update(['stages' => $stages]);
        $before = $this->row($entry);
        $this->assertFalse(WorkflowStageProgression::verified($entry));
        $this->assertSame($before, $this->row($entry));
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->mountTableAction('verify', $entry);
        $column = $component->instance()->getTable()->getColumn('review_status')->record($entry->fresh());
        $this->assertSame(__('workflow_notifications.status_defaults.verification'), $column->formatState($column->getState()));
        $this->assertSame('verify', $component->instance()->getMountedTableAction()?->getName());
        $this->assertSame(__('workflow_notifications.confirmations.verify_body'), $component->instance()->getMountedAction()?->getModalDescription());
        $this->assertNotNull($component->instance()->getMountedAction()?->getModalSubmitAction());
        $this->assertSame($before, $this->row($entry));
        $component->unmountTableAction();
        $this->assertSame($before, $this->row($entry));
        $this->assertDatabaseCount('candidate_id_sequences', 0);

        $component->mountTableAction('verify', $entry)->callMountedTableAction()->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertTrue(data_get($entry->data, 'workflow_progress.verification_completed'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame('Original answer', $entry->data['answer']);
        $this->assertSame($this->candidate->id, $entry->created_by);
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('Verified', WorkflowStageMessages::statusText($entry));
        $column->record($entry);
        $this->assertSame('Verified', $column->formatState($column->getState()));
        $this->assertSame(['Frozen verification notice'], $this->candidate->notifications()->get()->pluck('data.body')->all());
        $this->assertTrue(PaymentStatusListResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $after = $this->row($entry);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($after, $this->row($entry));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $component->call('$refresh')->assertTableActionHidden('verify', $entry);

        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'VERIFIED-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_workflow_receipt_rechecks_view_manager_responsible_role_owner_actor_and_fresh_stage_after_mount(): void
    {
        [$form] = $this->workflow();
        $entry = $this->entry($form);
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        $this->assertFalse(WorkflowStageProgression::verified($entry));
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->review($entry);
        $before = $this->row($entry);
        $this->staff->revokePermissionTo('Verify:CustomFormEntry')->unsetRelation('permissions');
        $this->assertFalse($this->staff->can('Verify:CustomFormEntry'));
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->mountTableAction('verify', $entry);
        $this->staff->revokePermissionTo('View:CustomFormEntry')->unsetRelation('permissions');
        $component->callMountedTableAction();
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $entry);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->assertSame($before, $this->row($entry));
        $this->staff->givePermissionTo('View:CustomFormEntry');
        $this->staff->revokePermissionTo('ViewAny:CustomFormEntry')->unsetRelation('permissions');
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->staff->givePermissionTo('ViewAny:CustomFormEntry');

        $wrongRole = $this->user('admin');
        $this->authorizeStaff($wrongRole, 'cashier_officer');
        $wrongRole->revokePermissionTo('Verify:CustomFormEntry')->unsetRelation('permissions');
        $this->actingAs($wrongRole);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $entry);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->candidate->givePermissionTo('Verify:CustomFormEntry', 'View:CustomFormEntry', 'ViewAny:CustomFormEntry');
        $this->actingAs($this->candidate);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->assertSame($before, $this->row($entry));

        $this->actingAs($this->staff);
        $component = Livewire::test(ListCustomFormEntries::class)->mountTableAction('verify', $entry);
        $data = $entry->fresh()->data;
        $data['registration_status'] = 'rejected';
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'rejected', 'data' => json_encode($data)]);
        $rejected = $this->row($entry);
        $component->callMountedTableAction();
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($rejected, $this->row($entry));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
    }

    public function test_frozen_receipt_role_authorizes_native_and_direct_confirmation_without_static_verify_permission(): void
    {
        [$form, $workflow] = $this->workflow();
        $native = $this->entry($form);
        $direct = $this->entry($form);
        $this->review($native);
        $this->review($direct);
        $snapshots = [$native->getRawOriginal('workflow_snapshot'), $direct->getRawOriginal('workflow_snapshot')];

        $stages = $workflow->stages;
        $stages[1]['data']['responsible_role'] = ['cashier_officer'];
        $workflow->update(['stages' => $stages]);
        $changedRole = $this->entry($form);
        $this->review($changedRole);
        $workflow->forms()->detach($form);
        $replacement = WorkflowNotification::query()->create(['name' => 'Replacement without receipt', 'stages' => $this->stages(false)]);
        $replacement->forms()->attach($form);
        $this->assertSame($replacement->id, WorkflowNotification::forForm($form->id)?->id);

        $this->staff->revokePermissionTo('Verify:CustomFormEntry')->unsetRelation('permissions');
        $this->assertFalse($this->staff->can('Verify:CustomFormEntry'));
        $beforeChangedRole = $this->row($changedRole);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $native)
            ->assertTableActionVisible('verify', $direct)->assertTableActionHidden('verify', $changedRole)
            ->callTableAction('verify', $native)->assertHasNoTableActionErrors();
        $this->assertSame('BNM-0001', $native->refresh()->candidate_id);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($direct));
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($changedRole), 403);
        $this->assertSame($beforeChangedRole, $this->row($changedRole));
        foreach ([$native, $direct] as $index => $entry) {
            $this->assertTrue(data_get($entry->refresh()->data, 'workflow_progress.verification_completed'));
            $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry));
            $this->assertSame($snapshots[$index], $entry->getRawOriginal('workflow_snapshot'));
        }
        $after = $this->row($direct);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($direct));
        $this->assertSame($after, $this->row($direct));
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_no_other_event_or_missing_major_can_complete_verification(): void
    {
        [$form] = $this->workflow();
        $entry = $this->entry($form, data: ['major' => 'unknown major']);
        $this->review($entry);
        $before = $this->row($entry);
        $this->assertFalse(WorkflowStageProgression::reviewed($entry));
        $this->assertFalse(WorkflowStageProgression::paid($entry));
        $this->assertFalse(WorkflowStageProgression::passed($entry));
        $this->assertFalse(WorkflowStageProgression::notified($entry, WorkflowStageType::Completed));
        $this->assertNull(app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($before, $this->row($entry));
        $this->assertNull(WorkflowStageProgression::completedAt($entry->fresh(), 1));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        $this->assertDatabaseCount('notifications', 0);

        $data = $entry->fresh()->data;
        $data['major'] = (string) $this->major->id;
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['data' => json_encode($data)]);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
    }

    public function test_static_receipt_is_deferred_while_workflows_without_verification_keep_review_ids_and_frozen_old_applications(): void
    {
        foreach (['row', 'modal'] as $path) {
            $static = $this->entry($this->form());
            $this->review($static, $path);
            $this->assertNull($static->fresh()->candidate_id);
            $this->assertTrue(app(WorkflowCandidateVerifier::class)->canVerify($static->fresh()));
            Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $static);

            [$form, $workflow] = $this->workflow(false);
            $old = $this->entry($form);
            $snapshot = $old->fresh()->getRawOriginal('workflow_snapshot');
            $this->assertArrayNotHasKey('verification_completed', data_get($old->data, 'workflow_progress'));
            $workflow->update(['stages' => $this->stages()]);
            $new = $this->entry($form, owner: $this->user('student'));
            $this->review($old, $path);
            $this->assertSame('BNM-0001', $old->fresh()->candidate_id);
            $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($old->fresh()));
            $this->assertSame($snapshot, $old->fresh()->getRawOriginal('workflow_snapshot'));
            $this->review($new, $path);
            $this->assertNull($new->fresh()->candidate_id);
            $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($new->fresh()));
            Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $old)
                ->assertTableActionVisible('verify', $new);
        }

        [$form, $workflow] = $this->workflow(false);
        $legacyStages = $workflow->stages;
        array_splice($legacyStages, 1, 0, [$legacyStages[0]]);
        DB::table('workflow_notifications')->where('id', $workflow->id)->update(['stages' => json_encode($legacyStages)]);
        $legacy = $this->entry($form);
        $snapshot = $legacy->getRawOriginal('workflow_snapshot');
        $this->assertSame(1, $legacy->workflow_snapshot['schema_version']);
        $workflow->refresh()->update(['stages' => $this->stages()]);
        $this->review($legacy);
        $this->assertSame('BNM-0001', $legacy->fresh()->candidate_id);
        $this->assertSame($snapshot, $legacy->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($legacy->fresh()));
    }

    public function test_older_sibling_review_cannot_preassign_id_to_an_unverified_application_but_verify_reuses_it(): void
    {
        [$form, $workflow] = $this->workflow(false);
        $old = $this->entry($form);
        $workflow->update(['stages' => $this->stages()]);
        $new = $this->entry($form);
        $this->review($old);
        $this->assertSame('BNM-0001', $old->fresh()->candidate_id);
        $this->assertNull($new->fresh()->candidate_id);
        $this->review($new);
        $this->assertNull($new->fresh()->candidate_id);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($new));
        $this->assertSame('BNM-0001', $old->fresh()->candidate_id);
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
    }

    public function test_reversing_an_earlier_payment_resets_review_and_verification_and_reuses_the_existing_id_after_reconfirmation(): void
    {
        [$form, $workflow] = $this->workflow();
        $stages = $this->stages();
        $workflow->update(['stages' => [$stages[2], $stages[0], $stages[1], $stages[3], $stages[4]]]);
        $entry = $this->entry($form);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry));
        $payment = app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'VERIFY-REWIND-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->review($entry);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertTrue(data_get($entry->fresh()->data, 'workflow_progress.verification_completed'));
        $snapshot = $entry->getRawOriginal('workflow_snapshot');

        $payment->update(['status_payt' => 'return']);
        $this->assertTrue(WorkflowStageProgression::rollbackUnpaidAutoCompletion($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertFalse(data_get($entry->data, 'workflow_progress.reviewed'));
        $this->assertFalse(data_get($entry->data, 'workflow_progress.verification_completed'));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 2));
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertFalse(WorkflowStageProgression::verified($entry));

        $payment->update(['status_payt' => 'paid']);
        $this->assertTrue(WorkflowStageProgression::paid($entry));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->review($entry);
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertFalse(data_get($entry->data, 'workflow_progress.verification_completed'));
        $this->assertTrue(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->callTableAction('verify', $entry)->assertHasNoTableActionErrors();
        $this->assertTrue(data_get($entry->refresh()->data, 'workflow_progress.verification_completed'));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry));
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('candidate_account_ids', 1);
    }

    private function workflow(bool $verification = true): array
    {
        $form = $this->form();
        $workflow = WorkflowNotification::query()->create(['name' => 'Verification workflow '.uniqid(), 'stages' => $this->stages($verification)]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function stages(bool $verification = true): array
    {
        $types = ['review', ...($verification ? ['verification'] : []), 'payment', 'awaiting_results', 'completed'];

        return array_map(fn (string $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type, 'stage_name' => ucfirst($type), 'responsible_role' => ['registrar_officer'],
            'payment_source' => 'system', 'notification_channels' => $type === 'verification' ? ['system'] : [],
            'notification_message' => $type === 'verification' ? 'Frozen verification notice' : '',
            'status_message' => $type === 'verification' ? 'Verified' : '',
            ...($type === 'awaiting_results' ? ['statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS] : []),
            ...($type === 'completed' ? ['passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS] : []),
        ]], $types);
    }

    private function review(CustomFormEntry $entry, string $path = 'row'): void
    {
        if ($path === 'row') {
            DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
        } elseif (! DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->exists()) {
            DocumentTemplate::query()->create(['name' => 'Verification review', 'type' => 'custom_form_'.$entry->custom_form_id, 'content' => '<p>Application review</p>']);
        }
        if ($path === 'http') {
            $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        } else {
            $component = Livewire::test(ListCustomFormEntries::class)
                ->callTableAction($path === 'modal' ? ['view_template_pdf_modal', 'approve_from_view'] : 'accepted', $entry);
            if ($path === 'modal') {
                $component->assertRedirect();
            } else {
                $component->assertHasNoTableActionErrors();
            }
        }
        $entry->refresh();
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Verification application', 'slug' => 'verification-'.uniqid(), 'is_active' => true,
            'requires_payment' => true, 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ]);
    }

    private function entry(CustomForm $form, array $data = [], ?User $owner = null): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => ($owner ?? $this->candidate)->id, 'review_status' => 'pending',
            'data' => array_replace(['registration_status' => 'pending', 'candidate_status' => 'pending', 'major' => (string) $this->major->id, 'answer' => 'Original answer'], $data),
        ]);
        WorkflowStageProgression::submitted($entry);

        return $entry->fresh();
    }

    private function authorizeStaff(User $user, string $role): void
    {
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:PaymentStatusList', 'web'));
        foreach (['ViewAny', 'View', 'Accepted', 'Verify', 'ViewPdf'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
    }

    private function assertBlocked(callable $callback, ?int $expectedStatus = null): void
    {
        try {
            $callback();
            $this->fail('Verification must enforce the current stage and authorized responsible staff.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            if ($expectedStatus !== null) {
                $this->assertSame($expectedStatus, $exception->getStatusCode());
            } else {
                $this->assertContains($exception->getStatusCode(), [403, 409]);
            }
        }
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function user(string $type): User
    {
        $name = 'verify-'.$type.'-'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
