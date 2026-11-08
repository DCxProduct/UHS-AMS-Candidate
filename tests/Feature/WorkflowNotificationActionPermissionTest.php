<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\PassedResultMenuOptions;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowNotificationActionPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        $this->operator = $this->user('admin', 'result_operator');
        foreach (['ExamResult', 'ExitExamResult'] as $subject) {
            $this->grant($this->operator, $subject, ['ViewAny']);
        }
        $this->candidate = $this->user('student', 'candidate');
        $this->actingAs($this->operator);
    }

    public static function resultMenus(): array
    {
        return [[PassedResultMenuOptions::EXAM_RESULTS], [PassedResultMenuOptions::EXIT_EXAM_RESULTS]];
    }

    #[DataProvider('resultMenus')]
    public function test_frozen_final_role_authorizes_row_header_and_server_without_notification_grants(string $menu): void
    {
        [$row, $workflow] = $this->application($menu);
        $bulk = $this->sameApplication($row);
        $direct = $this->sameApplication($row);
        $snapshots = collect([$row, $bulk, $direct])->mapWithKeys(fn ($entry): array => [$entry->id => $entry->getRawOriginal('workflow_snapshot')])->all();
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['cashier_officer'];
        $stages[0]['data']['notification_message'] = 'Changed live notification';
        $workflow->update(['stages' => $stages]);
        $workflow->forms()->detach($row->custom_form_id);
        $subject = $this->subject($menu);
        $this->assertFalse($this->operator->can('NotifyStudent:'.$subject));
        $this->assertFalse($this->operator->can('NotifyAllStudents:'.$subject));
        $component = Livewire::test($this->page($menu))->assertCanSeeTableRecords([$row, $bulk, $direct])
            ->assertTableActionVisible('notify_student', $row)->assertActionVisible('notify_all_students')
            ->mountTableAction('notify_student', $row);
        $this->assertDatabaseCount('notifications', 0);
        $component->unmountTableAction()->callTableAction('notify_student', $row)->assertHasNoTableActionErrors();
        Livewire::test($this->page($menu))->callAction('notify_all_students', arguments: ['selectedRecordKeys' => [$bulk->id], 'selectedRecordsCount' => 1])->assertHasNoActionErrors();
        $this->assertTrue(ExamResultsTable::sendPassedNotification($direct, $menu));
        $this->assertFalse(ExamResultsTable::sendPassedNotification($direct, $menu));
        $this->assertSame(3, $this->candidate->notifications()->count());
        $this->assertSame(['Frozen final notification'], $this->candidate->notifications()->get()->pluck('data.body')->unique()->values()->all());
        foreach ([$row, $bulk, $direct] as $entry) {
            $this->assertSame($snapshots[$entry->id], $entry->fresh()->getRawOriginal('workflow_snapshot'));
            $this->assertSame('passed', $entry->review_status);
            $this->assertSame('Original answer', $entry->data['answer']);
            $this->assertSame('OLD-42', $entry->candidate_id);
        }
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('resultMenus')]
    public function test_static_row_and_bulk_permissions_remain_distinct(string $menu): void
    {
        [$row] = $this->application($menu, type: null);
        $bulk = $this->sameApplication($row);
        $subject = $this->subject($menu);
        Livewire::test($this->page($menu))->assertTableActionHidden('notify_student', $row)->assertActionHidden('notify_all_students');
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($row, $menu));
        $this->grant($this->operator, $subject, ['NotifyStudent']);
        Livewire::test($this->page($menu))->assertTableActionVisible('notify_student', $row)->assertActionHidden('notify_all_students')
            ->callTableAction('notify_student', $row)->assertHasNoTableActionErrors();
        $this->assertSame(0, ExamResultsTable::sendPassedNotifications([$bulk], $menu));
        $this->operator->revokePermissionTo('NotifyStudent:'.$subject)->unsetRelation('permissions');
        $this->grant($this->operator, $subject, ['NotifyAllStudents']);
        Livewire::test($this->page($menu))->assertTableActionHidden('notify_student', $bulk)->assertActionVisible('notify_all_students')
            ->callAction('notify_all_students', arguments: ['selectedRecordKeys' => [$bulk->id], 'selectedRecordsCount' => 1])->assertHasNoActionErrors();
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($bulk, $menu));
        $this->assertEqualsCanonicalizing([$row->id, $bulk->id], $this->notifiedEntryIds());
    }

    #[DataProvider('resultMenus')]
    public function test_mixed_bulk_checks_each_workflow_role_static_grant_and_result_query_scope(string $menu): void
    {
        [$allowed] = $this->application($menu);
        [$wrong] = $this->application($menu, roles: ['cashier_officer']);
        [$empty] = $this->application($menu, roles: null);
        [$static] = $this->application($menu, type: null);
        [$hidden] = $this->application($menu);
        [$otherMenu] = $this->application($menu === PassedResultMenuOptions::EXAM_RESULTS ? PassedResultMenuOptions::EXIT_EXAM_RESULTS : PassedResultMenuOptions::EXAM_RESULTS);
        $flag = $menu === PassedResultMenuOptions::EXAM_RESULTS ? 'hidden_from_exam_results' : 'hidden_from_exit_exam_results';
        DB::table('custom_form_entries')->where('id', $hidden->id)->update(['data' => json_encode(array_replace($hidden->data, [$flag => true]))]);
        $rejected = collect([$wrong, $empty, $static, $hidden, $otherMenu])->mapWithKeys(fn ($entry): array => [$entry->id => $this->row($entry)])->all();
        $ids = [$allowed->id, $wrong->id, $empty->id, $static->id, $hidden->id, $otherMenu->id];
        Livewire::test($this->page($menu))->assertActionVisible('notify_all_students')
            ->callAction('notify_all_students', arguments: ['selectedRecordKeys' => $ids, 'selectedRecordsCount' => count($ids)])->assertHasNoActionErrors();
        $this->assertSame([$allowed->id], $this->notifiedEntryIds());
        foreach ([$wrong, $empty, $static, $hidden, $otherMenu] as $entry) {
            $this->assertSame($rejected[$entry->id], $this->row($entry));
        }
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($hidden, $menu), 409);
        $this->grant($this->operator, $this->subject($menu), ['NotifyAllStudents']);
        $this->assertSame(1, ExamResultsTable::sendPassedNotifications([$wrong, $empty, $static, $hidden, $otherMenu], $menu));
        $this->assertEqualsCanonicalizing([$allowed->id, $static->id], $this->notifiedEntryIds());
        foreach ([$wrong, $empty, $hidden, $otherMenu] as $entry) {
            $this->assertSame($rejected[$entry->id], $this->row($entry));
        }
    }

    #[DataProvider('resultMenus')]
    public function test_wrong_or_empty_roles_candidates_and_missing_menu_access_cannot_notify(string $menu): void
    {
        [$entry] = $this->application($menu);
        [$empty] = $this->application($menu, roles: null);
        $before = [$this->row($entry), $this->row($empty)];
        $wrong = $this->user('admin', 'cashier_officer');
        $this->grant($wrong, $this->subject($menu), ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($wrong);
        Livewire::test($this->page($menu))->assertTableActionHidden('notify_student', $entry)->assertTableActionHidden('notify_student', $empty);
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($entry, $menu));
        $this->assertSame(0, ExamResultsTable::sendPassedNotifications([$entry, $empty], $menu));
        $this->actingAs($this->operator);
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($empty, $menu));
        $this->operator->revokePermissionTo('ViewAny:'.$this->subject($menu))->unsetRelation('permissions');
        $this->assertFalse(ExamResultsTable::canSendPassedNotifications($menu));
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($entry, $menu));
        $this->candidate->assignRole(Role::findOrCreate('result_operator', 'web'));
        $this->grant($this->candidate, $this->subject($menu), ['ViewAny', 'NotifyStudent', 'NotifyAllStudents']);
        $this->actingAs($this->candidate);
        $this->assertFalse(ExamResultsTable::canSendPassedNotifications($menu));
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($entry, $menu));
        $this->assertSame($before, [$this->row($entry), $this->row($empty)]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function changedAfterMount(): array
    {
        return [[PassedResultMenuOptions::EXAM_RESULTS, 'role'], [PassedResultMenuOptions::EXAM_RESULTS, 'status'], [PassedResultMenuOptions::EXIT_EXAM_RESULTS, 'role'], [PassedResultMenuOptions::EXIT_EXAM_RESULTS, 'status']];
    }

    #[DataProvider('changedAfterMount')]
    public function test_notification_rechecks_role_and_result_eligibility_after_confirmation_mount(string $menu, string $change): void
    {
        [$entry] = $this->application($menu);
        $component = Livewire::test($this->page($menu))->mountTableAction('notify_student', $entry);
        if ($change === 'role') {
            $this->operator->removeRole('result_operator')->unsetRelation('roles');
        } else {
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['data' => json_encode(array_replace($entry->data, ['candidate_status' => 'pending']))]);
        }
        $before = $this->row($entry);
        $component->callMountedTableAction();
        $this->assertDenied(fn () => ExamResultsTable::sendPassedNotification($entry, $menu), $change === 'role' ? 403 : 409);
        $this->assertSame(0, ExamResultsTable::sendPassedNotifications([$entry], $menu));
        $this->assertSame($before, $this->row($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function optionalFinalStages(): array
    {
        return [[PassedResultMenuOptions::EXAM_RESULTS, 'awaiting_results'], [PassedResultMenuOptions::EXAM_RESULTS, 'payment'], [PassedResultMenuOptions::EXIT_EXAM_RESULTS, 'awaiting_results'], [PassedResultMenuOptions::EXIT_EXAM_RESULTS, 'payment']];
    }

    #[DataProvider('optionalFinalStages')]
    public function test_finished_results_or_payment_workflow_uses_its_final_role_without_completed_stage(string $menu, string $type): void
    {
        [$entry] = $this->application($menu, type: $type);
        if ($type === 'payment') {
            Payment::query()->create(['custom_form_entry_id' => $entry->id, 'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id, 'receipt_number' => 'FINAL-'.$entry->id, 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now()]);
            WorkflowStageProgression::paid($entry);
        }
        $this->assertTrue(WorkflowStageProgression::finished($entry->refresh()));
        $this->assertNull(WorkflowStageProgression::currentType($entry));
        $this->assertSame(WorkflowStageType::from($type), WorkflowStageProgression::finalStageType($entry));
        Livewire::test($this->page($menu))->assertCanSeeTableRecords([$entry])->assertTableActionVisible('notify_student', $entry)
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertSame([$entry->id], $this->notifiedEntryIds());
        $this->assertFalse(ExamResultsTable::sendPassedNotification($entry, $menu));
    }

    #[DataProvider('resultMenus')]
    public function test_legacy_snapshot_with_completed_role_can_still_send_without_action_grants(string $menu): void
    {
        [$entry] = $this->application($menu, version: 1);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertFalse(WorkflowStageProgression::enabled($entry));
        Livewire::test($this->page($menu))->assertTableActionVisible('notify_student', $entry)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertSame([$entry->id], $this->notifiedEntryIds());
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
    }

    private function application(string $menu, ?array $roles = ['result_operator'], ?string $type = 'completed', int $version = 2): array
    {
        $form = CustomForm::query()->create(['name' => 'Notification authorization application', 'slug' => 'notify-auth-'.uniqid(), 'is_active' => true, 'passed_result_menu' => $menu]);
        $workflow = null;
        if ($type !== null) {
            $workflow = WorkflowNotification::query()->create(['name' => 'Notification authorization '.uniqid(), 'stages' => [['type' => 'stage', 'data' => ['stage_type' => $type, 'stage_name' => 'Frozen final stage', 'responsible_role' => $roles, 'notification_channels' => ['system'], 'notification_message' => 'Frozen final notification', 'payment_source' => 'system', ...($type === 'completed' ? ['passed_result_menu' => $menu] : [])]]]]);
            $workflow->forms()->attach($form);
        }
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'passed', 'candidate_id' => 'OLD-42', 'data' => ['registration_status' => 'passed', 'candidate_status' => 'passed', 'answer' => 'Original answer']]);
        if ($version === 1) {
            $snapshot = array_replace($entry->workflow_snapshot, ['schema_version' => 1]);
            $data = $entry->data;
            unset($data['workflow_progress']);
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['workflow_snapshot' => json_encode($snapshot), 'data' => json_encode($data)]);
        }

        return [$entry->fresh(), $workflow];
    }

    private function sameApplication(CustomFormEntry $entry): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $entry->custom_form_id, 'created_by' => $this->candidate->id, 'review_status' => 'passed', 'candidate_id' => 'OLD-42', 'data' => ['registration_status' => 'passed', 'candidate_status' => 'passed', 'answer' => 'Original answer']])->fresh();
    }

    private function page(string $menu): string
    {
        return $menu === PassedResultMenuOptions::EXAM_RESULTS ? ListExamResults::class : ListExitExamResults::class;
    }

    private function subject(string $menu): string
    {
        return class_basename(($menu === PassedResultMenuOptions::EXAM_RESULTS ? ExamResultResource::class : ExitExamResultResource::class)::getModel());
    }

    private function grant(User $user, string $subject, array $actions): void
    {
        foreach ($actions as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':'.$subject, 'web'));
        }
    }

    private function user(string $type, string $role): User
    {
        $name = 'notify-auth-'.uniqid();
        $user = User::query()->forceCreate(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'test-password', 'registration_type' => $type, 'is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));

        return $user;
    }

    private function notifiedEntryIds(): array
    {
        return $this->candidate->notifications()->get()->map(fn ($notification): int => (int) (data_get($notification->data, 'viewData.workflow_entry_id') ?? data_get($notification->data, 'viewData.review_result_entry_id')))->sort()->values()->all();
    }

    private function assertDenied(callable $callback, int $status = 403): void
    {
        try {
            $callback();
            $this->fail('Notification must enforce current authorized access.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($status, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }
}
