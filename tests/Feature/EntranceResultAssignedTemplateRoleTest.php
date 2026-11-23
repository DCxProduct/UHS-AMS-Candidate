<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsSelectionResultSync;
use App\Support\PassedResultMenuOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class EntranceResultAssignedTemplateRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test',
            'token' => 'assigned-template-test', 'account_key' => 'test-account-encryption', 'timeout' => 5,
        ]);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response([
            'application_id' => $request['application_id'], 'status' => 'synced',
            'selection_result_id' => 'result-'.$request['application_id'],
            'source_account_id' => (string) $this->candidate->id,
            'account_id' => 'account-'.$this->candidate->id, 'account_status' => 'enrolled',
            'profile_synced' => true, 'profile_entry_id' => 'profile-'.$this->candidate->id,
        ]));
        Mail::fake();
        $this->candidate = $this->user('student');
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_actual_admin_cannot_use_historical_static_results_after_the_form_is_assigned_to_registrars(): void
    {
        $admin = $this->user('admin', 'admin');
        $this->actingAs($admin);
        [$form, $entry] = $this->staticApplication();
        $before = $this->storedRow($entry);
        $this->attachTemplate($form);

        $this->assertTrue($admin->can('NotifyStudent:ExamResult'));
        $this->assertTrue($admin->can('NotifyAllStudents:ExamResult'));
        $this->assertFalse($entry->workflow_snapshot['has_workflow']);
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionHidden('notify_student', $entry);
        $this->assertHeaderVisibility($component, false);
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(false, [$entry->id], 1));
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $this->assertDenied(fn () => ExamResultsTable::confirmAndNotify($entry));
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($entry, true));
        $component->instance()->sendNotificationsFromTableSelection([$entry->id], 1);
        $this->assertSame(0, ExamResultsTable::sendPassedNotifications([$entry]));
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();
    }

    public static function registrarRoles(): array
    {
        return [['registrar_manager'], ['registrar_officer']];
    }

    #[DataProvider('registrarRoles')]
    public function test_each_assigned_registrar_can_use_all_native_actions_without_notification_grants_and_without_replacing_old_snapshots(string $role): void
    {
        $registrar = $this->user('admin', $role);
        $this->grant($registrar, ['ViewAny']);
        $this->actingAs($registrar);
        [$form, $confirmOnly] = $this->staticApplication();
        $combined = $this->entry($form);
        $notifyOnly = $this->entry($form);
        $row = $this->entry($form);
        $entries = [$confirmOnly, $combined, $notifyOnly, $row];
        $snapshots = collect($entries)->mapWithKeys(fn ($entry): array => [$entry->id => $entry->getRawOriginal('workflow_snapshot')])->all();
        $this->attachTemplate($form);

        $this->assertFalse($registrar->can('NotifyStudent:ExamResult'));
        $this->assertFalse($registrar->can('NotifyAllStudents:ExamResult'));
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords($entries)
            ->assertTableActionVisible('notify_student', $row);
        $this->assertHeaderVisibility($component, true);
        $this->header('confirm_passed', [$confirmOnly]);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('notifications', 0);
        $this->header('confirm_passed_and_notify', [$combined]);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('notifications', 1);
        $this->header('notify_all_students', [$notifyOnly]);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('notifications', 2);
        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $row)->assertHasNoTableActionErrors();
        Http::assertSentCount(3);
        $this->assertDatabaseCount('notifications', 3);
        $this->assertDatabaseCount('ams_selection_result_syncs', 3);
        foreach ($entries as $entry) {
            $this->assertSame($snapshots[$entry->id], $entry->fresh()->getRawOriginal('workflow_snapshot'));
            $this->assertFalse($entry->workflow_snapshot['has_workflow']);
            $this->assertSame('OLD-42', $entry->candidate_id);
            $this->assertSame('passed', $entry->review_status);
            $this->assertSame('Original answer', $entry->data['answer']);
            $this->assertArrayNotHasKey('workflow_progress', $entry->data);
        }
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($confirmOnly->id)->exists());
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($combined->id)->exists());
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($row->id)->exists());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        Mail::assertNothingSent();
    }

    public function test_detaching_a_historical_static_form_restores_distinct_row_and_bulk_permissions(): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny']);
        $this->actingAs($registrar);
        [$form, $entry] = $this->staticApplication();
        $workflow = $this->attachTemplate($form);
        $this->assertTrue(ExamResultsTable::canNotifyRecord($entry));
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), true);
        $workflow->forms()->detach($form);

        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), false);
        $this->grant($registrar, ['NotifyStudent']);
        Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry);
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), false);
        $registrar->revokePermissionTo('NotifyStudent:ExamResult')->unsetRelation('permissions');
        $this->grant($registrar, ['NotifyAllStudents']);
        Livewire::test(ListExamResults::class)->assertTableActionHidden('notify_student', $entry);
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), true);
        $this->header('notify_all_students', [$entry]);
        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
    }

    public static function unavailableTemplates(): array
    {
        return [['no roles'], ['disabled notification'], ['other result menu']];
    }

    #[DataProvider('unavailableTemplates')]
    public function test_attached_templates_without_an_available_notification_stage_fail_closed_even_with_global_action_grants(string $configuration): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        [$form, $entry] = $this->staticApplication();
        $before = $this->storedRow($entry);
        $workflow = $this->attachTemplate(
            $form,
            roles: $configuration === 'no roles' ? [] : ['registrar_manager', 'registrar_officer'],
            menu: $configuration === 'other result menu' ? PassedResultMenuOptions::EXIT_EXAM_RESULTS : PassedResultMenuOptions::EXAM_RESULTS,
        );
        if ($configuration === 'disabled notification') {
            $stages = $workflow->stages;
            $stages[0]['data']['buttons'] = [];
            DB::table('workflow_notifications')->where('id', $workflow->id)->update(['stages' => json_encode($stages)]);
        }

        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionHidden('notify_student', $entry);
        $this->assertHeaderVisibility($component, false);
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($entry));
        $component->instance()->sendNotificationsFromTableSelection([$entry->id], 1);
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();
    }

    public static function optionalFinalStages(): array
    {
        return [['payment'], ['awaiting_results']];
    }

    #[DataProvider('optionalFinalStages')]
    public function test_a_template_without_completed_uses_its_existing_final_payment_or_results_role(string $type): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny']);
        $this->actingAs($registrar);
        [$form, $entry] = $this->staticApplication();
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->attachTemplate($form, type: $type);

        $component = Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry);
        $this->assertHeaderVisibility($component, true);
        $this->header('notify_all_students', [$entry]);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        Http::assertNothingSent();
    }

    public function test_mixed_red_bulk_notifies_only_records_whose_current_assigned_template_role_matches(): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        [$allowedForm, $allowed] = $this->staticApplication();
        [$wrongForm, $wrong] = $this->staticApplication();
        $this->attachTemplate($allowedForm);
        $this->attachTemplate($wrongForm, roles: ['cashier_officer']);
        $before = $this->storedRow($wrong);

        $this->header('notify_all_students', [$allowed, $wrong]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($before, $this->storedRow($wrong));
        $this->assertFalse(ExamResultsTable::hasStudentPassedNotification($wrong));
        $this->assertTrue(ExamResultsTable::hasStudentPassedNotification($allowed));
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        Http::assertNothingSent();
    }

    public function test_current_template_role_is_rechecked_after_a_combined_confirmation_is_mounted(): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        [$form, $entry] = $this->staticApplication();
        $workflow = $this->attachTemplate($form);
        $before = $this->storedRow($entry);
        $component = Livewire::test(ListExamResults::class)->mountAction('confirm_passed_and_notify', [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ]);
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['cashier_officer'];
        $workflow->update(['stages' => $stages]);

        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($entry));
        $component->instance()->sendNotificationsFromTableSelection([$entry->id], 1);
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), false);
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();
    }

    public function test_exit_historical_static_notifications_keep_original_permission_behavior_after_a_template_is_attached(): void
    {
        $admin = $this->user('admin', 'admin');
        $this->actingAs($admin);
        [$form, $row] = $this->staticApplication(PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $bulk = $this->entry($form);
        $snapshots = [$row->id => $row->getRawOriginal('workflow_snapshot'), $bulk->id => $bulk->getRawOriginal('workflow_snapshot')];
        $this->attachTemplate($form, menu: PassedResultMenuOptions::EXIT_EXAM_RESULTS);

        Livewire::test(ListExitExamResults::class)->assertTableActionVisible('notify_student', $row)
            ->assertActionVisible('notify_all_students')->assertActionHidden('confirm_passed')
            ->assertActionHidden('confirm_passed_and_notify')
            ->callTableAction('notify_student', $row)->assertHasNoTableActionErrors();
        Livewire::test(ListExitExamResults::class)->callAction('notify_all_students', arguments: [
            'selectedRecordKeys' => [$bulk->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        foreach ([$row, $bulk] as $entry) {
            $this->assertSame($snapshots[$entry->id], $entry->fresh()->getRawOriginal('workflow_snapshot'));
        }
        Http::assertNothingSent();
    }

    public function test_each_forms_current_template_controls_its_own_row_and_mixed_batch_actions(): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        [$registrarForm, $registrarEntry] = $this->workflowApplication(['registrar_officer']);
        [$cashierForm, $cashierEntry] = $this->workflowApplication(['cashier_officer']);
        $snapshots = [$registrarEntry->id => $registrarEntry->getRawOriginal('workflow_snapshot'), $cashierEntry->id => $cashierEntry->getRawOriginal('workflow_snapshot')];
        $cashierBefore = $this->storedRow($cashierEntry);

        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$registrarEntry, $cashierEntry])
            ->assertTableActionVisible('notify_student', $registrarEntry)->assertTableActionHidden('notify_student', $cashierEntry);
        $this->assertHeaderVisibility($component, true);
        $this->assertTrue(ExamResultsTable::canNotifyRecord($registrarEntry));
        $this->assertFalse(ExamResultsTable::canNotifyRecord($cashierEntry));
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$registrarEntry->id, $cashierEntry->id], 2));
        $this->assertNoResultEffects();
        $this->header('notify_all_students', [$registrarEntry, $cashierEntry]);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame($cashierBefore, $this->storedRow($cashierEntry));
        $this->assertFalse(ExamResultsTable::hasStudentPassedNotification($cashierEntry));
        Http::assertNothingSent();

        $cashier = $this->user('admin', 'cashier_officer');
        $this->grant($cashier, ['ViewAny']);
        $this->actingAs($cashier);
        $this->assertFalse($cashier->can('NotifyStudent:ExamResult'));
        $this->assertFalse($cashier->can('NotifyAllStudents:ExamResult'));
        $this->assertFalse(ExamResultsTable::canNotifyRecord($registrarEntry));
        $component = Livewire::test(ListExamResults::class)->assertTableActionHidden('notify_student', $registrarEntry)
            ->assertTableActionVisible('notify_student', $cashierEntry);
        $this->assertHeaderVisibility($component, true);
        $this->header('confirm_passed_and_notify', [$cashierEntry]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('ams_selection_result_syncs', 1);
        Http::assertSentCount(1);
        foreach ([$registrarEntry, $cashierEntry] as $entry) {
            $this->assertSame($snapshots[$entry->id], $entry->fresh()->getRawOriginal('workflow_snapshot'));
        }
        $this->assertNotSame($registrarForm->id, $cashierForm->id);
    }

    public function test_reassigning_a_form_changes_the_responsible_role_immediately_without_replacing_frozen_processing(): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        [$form, $entry] = $this->workflowApplication(['registrar_officer'], 'Frozen original notification');
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $before = $this->storedRow($entry);
        $component = Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry)
            ->mountAction('confirm_passed_and_notify', ['selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1]);
        $replacementForm = $this->form();
        $replacement = $this->attachTemplate($replacementForm, roles: ['cashier_officer'], message: 'Replacement template notification');
        WorkflowNotification::assignForm($form->id, $replacement->id);

        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class)->assertTableActionHidden('notify_student', $entry), false);
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($entry));
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();

        $cashier = $this->user('admin', 'cashier_officer');
        $this->grant($cashier, ['ViewAny']);
        $this->actingAs($cashier);
        $component = Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry);
        $this->assertHeaderVisibility($component, true);
        $component->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('Frozen original notification', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame(['registrar_officer'], data_get($entry->workflow_snapshot, 'stages.0.data.responsible_role'));
        Http::assertSentCount(1);
    }

    public function test_an_old_frozen_admin_role_does_not_bypass_a_forms_current_registrar_template(): void
    {
        $admin = $this->user('admin', 'admin');
        $this->actingAs($admin);
        [$form, $entry] = $this->workflowApplication(['admin']);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $before = $this->storedRow($entry);
        $this->assertTrue(ExamResultsTable::canNotifyRecord($entry));
        $replacement = $this->attachTemplate($this->form(), roles: ['registrar_manager', 'registrar_officer']);
        WorkflowNotification::assignForm($form->id, $replacement->id);

        $this->assertTrue($admin->can('NotifyStudent:ExamResult'));
        $this->assertSame(['admin'], data_get($entry->workflow_snapshot, 'stages.0.data.responsible_role'));
        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $component = Livewire::test(ListExamResults::class)->assertTableActionHidden('notify_student', $entry);
        $this->assertHeaderVisibility($component, false);
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(false, [$entry->id], 1));
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $this->assertDenied(fn () => ExamResultsTable::confirmAndNotify($entry));
        $component->instance()->sendNotificationsFromTableSelection([$entry->id], 1);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();
    }

    public function test_detaching_a_form_with_a_frozen_workflow_uses_current_resource_permissions_instead_of_its_old_role(): void
    {
        $cashier = $this->user('admin', 'cashier_officer');
        $this->grant($cashier, ['ViewAny']);
        $this->actingAs($cashier);
        [$form, $entry, $workflow] = $this->workflowApplication(['registrar_officer'], 'Frozen detached notification');
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $workflow->forms()->detach($form);

        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $this->assertHeaderVisibility(Livewire::test(ListExamResults::class), false);
        $this->grant($cashier, ['NotifyStudent']);
        $component = Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry);
        $this->assertHeaderVisibility($component, false);
        $cashier->revokePermissionTo('NotifyStudent:ExamResult')->unsetRelation('permissions');
        $this->grant($cashier, ['NotifyAllStudents']);
        $component = Livewire::test(ListExamResults::class)->assertTableActionHidden('notify_student', $entry);
        $this->assertHeaderVisibility($component, true);
        $this->header('notify_all_students', [$entry]);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('Frozen detached notification', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        Http::assertNothingSent();
    }

    public static function unavailableFrozenStages(): array
    {
        return [['unfinished prior stage'], ['frozen notification disabled']];
    }

    #[DataProvider('unavailableFrozenStages')]
    public function test_a_current_template_role_cannot_bypass_original_stage_readiness_or_disabled_buttons(string $configuration): void
    {
        $registrar = $this->user('admin', 'registrar_officer');
        $this->grant($registrar, ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($registrar);
        $form = $this->form();
        $workflow = $this->attachTemplate($form, roles: ['registrar_officer']);
        if ($configuration === 'unfinished prior stage') {
            $stages = $workflow->stages;
            array_unshift($stages, ['type' => 'stage', 'data' => [
                'stage_type' => 'review', 'stage_name' => 'Original prior check',
                'responsible_role' => ['registrar_officer'], 'notification_channels' => [],
            ]]);
            $workflow->update(['stages' => $stages]);
            $entry = CustomFormEntry::query()->forceCreate([
                'custom_form_id' => $form->id, 'created_by' => $this->candidate->id,
                'review_status' => 'pending', 'candidate_id' => 'OLD-42',
                'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'answer' => 'Original answer'],
            ])->fresh();
        } else {
            $entry = $this->entry($form);
            $snapshot = $entry->workflow_snapshot;
            $snapshot['stages'][0]['data']['buttons'] = [];
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['workflow_snapshot' => json_encode($snapshot)]);
            $entry->refresh();
        }
        $before = $this->storedRow($entry);

        $this->assertFalse(ExamResultsTable::canNotifyRecord($entry));
        $component = Livewire::test(ListExamResults::class);
        $this->assertHeaderVisibility($component, false);
        if ($configuration === 'unfinished prior stage') {
            $component->assertCanNotSeeTableRecords([$entry]);
            $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1);
        } else {
            $component->assertCanSeeTableRecords([$entry])->assertTableActionHidden('notify_student', $entry);
            $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        }
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($entry));
        $this->assertSame(0, ExamResultsTable::sendPassedNotifications([$entry]));
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertNoResultEffects();
    }

    private function workflowApplication(array $roles, string $message = 'Current template notification'): array
    {
        $form = $this->form();
        $workflow = $this->attachTemplate($form, roles: $roles, message: $message);
        $entry = $this->entry($form);
        $this->assertTrue($entry->workflow_snapshot['has_workflow']);

        return [$form, $entry, $workflow];
    }

    private function form(string $menu = PassedResultMenuOptions::EXAM_RESULTS): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Assigned template application', 'slug' => 'assigned-role-'.uniqid(),
            'is_active' => true, 'passed_result_menu' => $menu,
        ]);
    }

    private function staticApplication(string $menu = PassedResultMenuOptions::EXAM_RESULTS): array
    {
        $form = $this->form($menu);
        $entry = $this->entry($form);
        $this->assertFalse($entry->workflow_snapshot['has_workflow']);

        return [$form, $entry];
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id,
            'review_status' => 'passed', 'candidate_id' => 'OLD-42',
            'data' => ['registration_status' => 'passed', 'candidate_status' => 'passed', 'answer' => 'Original answer'],
        ])->fresh();
    }

    private function attachTemplate(
        CustomForm $form,
        array $roles = ['registrar_manager', 'registrar_officer'],
        string $menu = PassedResultMenuOptions::EXAM_RESULTS,
        string $type = 'completed',
        string $message = 'Current template notification',
    ): WorkflowNotification {
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Assigned result role '.uniqid(),
            'stages' => [['type' => 'stage', 'data' => [
                'stage_type' => $type, 'stage_name' => 'Assigned result notification',
                'responsible_role' => $roles, 'notification_channels' => ['system'],
                'notification_message' => $message, 'payment_source' => 'system',
                ...($type === 'completed' ? ['passed_result_menu' => $menu] : []),
            ]]],
        ]);
        $workflow->forms()->attach($form);

        return $workflow;
    }

    private function user(string $type, ?string $role = null): User
    {
        $name = 'assigned-role-'.uniqid();
        $user = User::query()->forceCreate([
            'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'test-password',
            'registration_type' => $type, 'is_active' => true,
        ]);
        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }

    private function grant(User $user, array $actions): void
    {
        foreach ($actions as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':ExamResult', 'web'));
        }
    }

    private function header(string $name, array $entries): void
    {
        Livewire::test(ListExamResults::class)->callAction($name, arguments: [
            'selectedRecordKeys' => array_map(fn (CustomFormEntry $entry): int => $entry->id, $entries),
            'selectedRecordsCount' => count($entries),
        ])->assertHasNoActionErrors();
    }

    private function assertHeaderVisibility($component, bool $visible): void
    {
        foreach (['confirm_passed', 'confirm_passed_and_notify', 'notify_all_students'] as $action) {
            $visible ? $component->assertActionVisible($action) : $component->assertActionHidden($action);
        }
    }

    private function assertNoResultEffects(): void
    {
        Http::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    private function storedRow(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function assertDenied(callable $callback): void
    {
        try {
            $callback();
            $this->fail('The assigned workflow role must control the result action.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
