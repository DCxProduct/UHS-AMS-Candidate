<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsSelectionResultSync;
use App\Support\PassedResultMenuOptions;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class EntranceResultConfirmationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $candidate;

    private array $acknowledgementOverrides = [];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test',
            'token' => 'entrance-results-test', 'account_key' => 'test-account-encryption', 'timeout' => 5,
        ]);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response(array_replace([
            'application_id' => $request['application_id'], 'status' => 'synced',
            'selection_result_id' => 'result-'.$request['application_id'],
            'source_account_id' => (string) $this->candidate->id,
            'account_id' => 'account-'.$this->candidate->id, 'account_status' => 'enrolled',
            'profile_synced' => true, 'profile_entry_id' => 'profile-'.$this->candidate->id,
        ], $this->acknowledgementOverrides)));
        Mail::fake();
        $this->operator = $this->user('entrance-result-operator', 'admin', 'result_operator');
        $this->candidate = $this->user('entrance-result-candidate', 'student');
        $this->grant($this->operator, ['ViewAny']);
        $this->actingAs($this->operator);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_native_entrance_row_confirms_then_notifies_once_without_action_grants(): void
    {
        [$entry] = $this->application();
        $before = $this->businessState($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertFalse($this->operator->can('NotifyStudent:ExamResult'));
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionVisible('notify_student', $entry);
        $action = $component->instance()->getTable()->getAction('notify_student')->record($entry);
        $this->assertSame(__('exam_results.confirm_passed_and_notify'), $action->getLabel());
        $component->mountTableAction('notify_student', $entry);
        Http::assertNothingSent();
        $this->assertDatabaseCount('notifications', 0);

        $component->callMountedTableAction()->assertHasNoTableActionErrors();

        Http::assertSentCount(1);
        $this->assertNotified([$entry]);
        $this->assertDatabaseHas('ams_selection_result_syncs', ['custom_form_entry_id' => $entry->id]);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame($before, $this->businessState($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(app(AmsSelectionResultSync::class)->sync($entry, true, 'notify_student'));
        $this->assertFalse(ExamResultsTable::confirmAndNotify($entry));
        Http::assertSentCount(1);
        $this->assertNotified([$entry]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        Mail::assertNothingSent();
    }

    public function test_confirm_only_and_row_share_the_acknowledgement_while_red_header_only_notifies(): void
    {
        [$first] = $this->application();
        $second = $this->sameApplication($first);
        $third = $this->sameApplication($first);
        $snapshots = collect([$first, $second, $third])->mapWithKeys(fn ($entry): array => [$entry->id => $entry->getRawOriginal('workflow_snapshot')])->all();
        $this->header('confirm_passed', [$first, $second]);
        Http::assertSentCount(2);
        $this->assertDatabaseCount('notifications', 0);
        Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$first, $second, $third])
            ->callTableAction('notify_student', $first)->assertHasNoTableActionErrors();
        Http::assertSentCount(2);
        $this->assertNotified([$first]);

        $this->header('notify_all_students', [$second, $third]);

        Http::assertSentCount(2);
        $this->assertDatabaseCount('ams_selection_result_syncs', 2);
        $this->assertNotified([$first, $second, $third]);
        Livewire::test(ListExamResults::class)->assertActionVisible('confirm_passed_and_notify')
            ->assertActionVisible('confirm_passed')->assertActionHidden('notify_all_students')
            ->assertTableActionHidden('notify_student', $second)->assertTableActionVisible('notify_student', $third)
            ->callAction('confirm_passed_and_notify', arguments: [
                'selectedRecordKeys' => [$second->id, $third->id], 'selectedRecordsCount' => 2,
            ])->assertHasNoActionErrors();
        Http::assertSentCount(3);
        $this->assertNotified([$first, $second, $third]);
        Livewire::test(ListExamResults::class)->assertActionHidden('confirm_passed_and_notify')
            ->assertActionHidden('confirm_passed')->assertActionHidden('notify_all_students')
            ->assertCanNotSeeTableRecords([$first, $second, $third]);
        foreach ([$first, $second, $third] as $entry) {
            $this->assertFalse(app(AmsSelectionResultSync::class)->sync($entry, true));
            $this->assertSame($snapshots[$entry->id], $entry->fresh()->getRawOriginal('workflow_snapshot'));
        }
        Http::assertSentCount(3);
        $this->assertDatabaseCount('ams_selection_result_syncs', 3);
        $this->assertNotified([$first, $second, $third]);
    }

    public function test_red_header_keeps_its_original_name_modal_and_notification_only_pipeline(): void
    {
        [$entry] = $this->application();
        $component = Livewire::test(ListExamResults::class)->assertActionVisible('notify_all_students');
        $action = $component->instance()->getAction('notify_all_students');
        $this->assertSame(__('exam_results.notify_all_students'), $action->getLabel());
        $this->assertSame(__('exam_results.notify_all_confirm_title'), $action->getModalHeading());
        $this->assertSame(__('exam_results.notify_all_confirm_description'), $action->getModalDescription());
        $this->assertSame(__('exam_results.send_notification'), $action->getModalSubmitActionLabel());

        $this->header('notify_all_students', [$entry]);

        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        $this->assertNotified([$entry]);
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry)
            ->assertActionVisible('confirm_passed')->assertActionVisible('confirm_passed_and_notify')
            ->assertActionHidden('notify_all_students')
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ams_selection_result_syncs', 1);
        $this->assertNotified([$entry]);
        Livewire::test(ListExamResults::class)->assertCanNotSeeTableRecords([$entry])
            ->assertActionHidden('confirm_passed')->assertActionHidden('confirm_passed_and_notify')
            ->assertActionHidden('notify_all_students');
    }

    public function test_native_combined_bulk_confirms_each_selected_record_and_retries_do_not_resend(): void
    {
        [$first] = $this->application();
        $second = $this->sameApplication($first);
        [$unselected] = $this->application();

        $this->header('confirm_passed_and_notify', [$first, $second]);

        Http::assertSentCount(2);
        $this->assertNotified([$first, $second]);
        Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$unselected])
            ->assertCanNotSeeTableRecords([$first, $second]);
        $this->header('notify_all_students', [$first, $second]);
        $this->assertFalse(ExamResultsTable::confirmAndNotify($first));
        Http::assertSentCount(2);
        $this->assertNotified([$first, $second]);
    }

    public function test_local_notification_failure_retries_the_confirmed_row_without_reposting_to_ams(): void
    {
        [$entry] = $this->application();
        $failOnce = true;
        Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$failOnce): void {
            if ($failOnce && $event->notifiable instanceof User && $event->notifiable->is($this->candidate)) {
                $failOnce = false;
                throw new \RuntimeException('Simulated local notification failure after AMS acknowledgement.');
            }
        });
        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('ams_selection_result_syncs', 1);
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());

        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();

        Http::assertSentCount(1);
        $this->assertNotified([$entry]);
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
    }

    public function test_forged_entry_acknowledgement_cannot_skip_ams_and_stale_form_saves_cannot_erase_real_acknowledgement(): void
    {
        [$entry] = $this->application();
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $forged = [
            'version' => 1, 'application_id' => (string) $entry->id, 'source_account_id' => (string) $this->candidate->id,
            'status' => 'synced', 'selection_result_id' => 'forged-result', 'account_id' => 'forged-account',
            'account_status' => 'enrolled', 'profile_synced' => true, 'profile_entry_id' => 'forged-profile', 'synced_at' => now()->toIso8601String(),
        ];
        $entry->update(['data' => [...$entry->data, 'ams_selection_result_sync' => $forged]]);
        $stale = $entry->fresh();
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);

        $this->assertFalse(app(AmsSelectionResultSync::class)->sync($entry));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('notifications', 0);
        $acknowledgement = DB::table('ams_selection_result_syncs')->where('custom_form_entry_id', $entry->id)->value('acknowledgement');
        $this->assertSame('result-'.$entry->id, json_decode($acknowledgement, true)['selection_result_id']);
        $stale->update(['data' => [...$stale->data, 'answer' => 'Ordinary form edit', 'ams_selection_result_sync' => null]]);
        $this->assertSame($acknowledgement, DB::table('ams_selection_result_syncs')->where('custom_form_entry_id', $entry->id)->value('acknowledgement'));
        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry->fresh())->assertHasNoTableActionErrors();
        Http::assertSentCount(1);
        $this->assertNotified([$entry]);
        $this->assertSame('Ordinary form edit', data_get($entry->fresh()->data, 'answer'));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
    }

    public static function invalidAcknowledgements(): array
    {
        return [
            'profile not acknowledged' => [['profile_synced' => false]],
            'boolean account ID' => [['account_id' => true]],
            'floating account ID' => [['account_id' => 1.5]],
            'boolean profile ID' => [['profile_entry_id' => true]],
            'floating profile ID' => [['profile_entry_id' => 1.5]],
        ];
    }

    #[DataProvider('invalidAcknowledgements')]
    public function test_incomplete_remote_acknowledgement_is_not_persisted_and_cannot_release_a_notification(array $invalid): void
    {
        [$entry] = $this->application();
        $this->acknowledgementOverrides = $invalid;

        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->acknowledgementOverrides = [];
        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        Http::assertSentCount(2);
        $this->assertDatabaseCount('ams_selection_result_syncs', 1);
        $this->assertNotified([$entry]);
    }

    public function test_frozen_completed_role_menu_and_message_control_row_and_all_entrance_headers(): void
    {
        [$entry, $workflow] = $this->application();
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['other_result_operator'];
        $stages[0]['data']['passed_result_menu'] = PassedResultMenuOptions::EXIT_EXAM_RESULTS;
        $stages[0]['data']['notification_message'] = 'Changed live final message';
        $workflow->update(['stages' => $stages]);
        $new = $this->sameApplication($entry);
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->assertCanNotSeeTableRecords([$new])->assertTableActionVisible('notify_student', $entry);
        foreach (['confirm_passed', 'confirm_passed_and_notify', 'notify_all_students'] as $name) {
            $component->assertActionVisible($name);
        }
        $component->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertNotified([$entry]);
        Http::assertSentCount(1);
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($new, true, 'notify_student'));
        [$wrongMenu] = $this->application(PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($wrongMenu, true, 'notify_student'), 409);
        Http::assertSentCount(1);
    }

    public function test_wrong_frozen_role_cannot_use_row_headers_or_forged_public_callbacks_even_with_global_grants(): void
    {
        [$entry] = $this->application(roles: ['other_result_operator']);
        $this->grant($this->operator, ['NotifyStudent', 'NotifyAllStudents']);
        $before = $this->storedRow($entry);
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionHidden('notify_student', $entry);
        foreach (['confirm_passed', 'confirm_passed_and_notify', 'notify_all_students'] as $name) {
            $component->assertActionHidden($name);
        }
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(false, [$entry->id], 1));
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$entry->id], 1));
        $component->instance()->sendNotificationsFromTableSelection([$entry->id], 1);
        $this->assertDenied(fn () => ExamResultsTable::confirmAndNotify($entry));
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($entry, true));
        $this->assertSame($before, $this->storedRow($entry));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_disabled_final_button_and_an_unfinished_prior_stage_cannot_be_confirmed(): void
    {
        [$disabled] = $this->application();
        $snapshot = $disabled->workflow_snapshot;
        $snapshot['stages'][0]['data']['buttons'] = [];
        DB::table('custom_form_entries')->where('id', $disabled->id)->update(['workflow_snapshot' => json_encode($snapshot)]);
        [$unready] = $this->application(unready: true);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($unready));
        $before = [$this->storedRow($disabled), $this->storedRow($unready)];
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$disabled])
            ->assertCanNotSeeTableRecords([$unready])->assertTableActionHidden('notify_student', $disabled);
        foreach (['confirm_passed', 'confirm_passed_and_notify', 'notify_all_students'] as $name) {
            $component->assertActionHidden($name);
        }
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$disabled->id, $unready->id], 2));
        $component->instance()->sendNotificationsFromTableSelection([$disabled->id, $unready->id], 2);
        $this->assertDenied(fn () => ExamResultsTable::confirmAndNotify($disabled->fresh()));
        $this->assertDenied(fn () => app(AmsSelectionResultSync::class)->sync($unready, true));
        $this->assertSame($before, [$this->storedRow($disabled), $this->storedRow($unready)]);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
    }

    public function test_exit_row_keeps_its_bell_notification_and_inherited_ams_headers_are_hidden(): void
    {
        [$row] = $this->application(PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $bulk = $this->sameApplication($row);
        $component = Livewire::test(ListExitExamResults::class)->assertCanSeeTableRecords([$row, $bulk])
            ->assertTableActionVisible('notify_student', $row)->assertActionVisible('notify_all_students')
            ->assertActionHidden('confirm_passed')->assertActionHidden('confirm_passed_and_notify');
        $action = $component->instance()->getTable()->getAction('notify_student')->record($row);
        $this->assertSame(__('exam_results.notify_student'), $action->getLabel());
        $this->assertSame('heroicon-o-bell-alert', $action->getIcon());
        $component->callTableAction('notify_student', $row)->assertHasNoTableActionErrors();
        Livewire::test(ListExitExamResults::class)->callAction('notify_all_students', arguments: [
            'selectedRecordKeys' => [$bulk->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors();
        $this->assertNotified([$row, $bulk]);
        $this->assertDenied(fn () => $component->instance()->confirmPassedFromTableSelection(true, [$row->id], 1));
        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_selection_result_syncs', 0);
    }

    private function header(string $name, array $entries): void
    {
        Livewire::test(ListExamResults::class)->callAction($name, arguments: [
            'selectedRecordKeys' => array_map(fn (CustomFormEntry $entry): int => $entry->id, $entries),
            'selectedRecordsCount' => count($entries),
        ])->assertHasNoActionErrors();
    }

    private function application(string $menu = PassedResultMenuOptions::EXAM_RESULTS, array $roles = ['result_operator'], bool $unready = false): array
    {
        $form = CustomForm::query()->create(['name' => 'Entrance confirmation test', 'slug' => 'entrance-confirm-'.uniqid(), 'is_active' => true, 'passed_result_menu' => $menu]);
        $stages = [['type' => 'stage', 'data' => [
            'stage_type' => 'completed', 'stage_name' => 'Frozen final stage', 'responsible_role' => $roles,
            'passed_result_menu' => $menu, 'notification_channels' => ['system'],
            'notification_message' => 'Frozen final notification :candidate_id',
        ]]];
        if ($unready) {
            array_unshift($stages, ['type' => 'stage', 'data' => [
                'stage_type' => 'review', 'stage_name' => 'Prior check', 'responsible_role' => ['result_operator'], 'notification_channels' => [],
            ]]);
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Entrance result workflow '.uniqid(), 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$this->createEntry($form->id, $unready), $workflow];
    }

    private function sameApplication(CustomFormEntry $entry): CustomFormEntry
    {
        return $this->createEntry($entry->custom_form_id);
    }

    private function createEntry(int $formId, bool $unready = false): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $formId, 'created_by' => $this->candidate->id,
            'review_status' => $unready ? 'pending' : 'passed', 'candidate_id' => 'OLD-42',
            'data' => ['registration_status' => $unready ? 'pending' : 'passed', 'candidate_status' => $unready ? 'pending' : 'passed', 'answer' => 'Original answer'],
        ])->fresh();
    }

    private function user(string $name, string $type, ?string $role = null): User
    {
        $user = User::query()->forceCreate([
            'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'test-password', 'registration_type' => $type, 'is_active' => true,
        ]);
        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }

    private function grant(User $user, array $actions): void
    {
        foreach (['ExamResult', 'ExitExamResult'] as $subject) {
            foreach ($actions as $action) {
                $user->givePermissionTo(Permission::findOrCreate($action.':'.$subject, 'web'));
            }
        }
    }

    private function assertNotified(array $entries): void
    {
        $ids = array_map(fn (CustomFormEntry $entry): int => $entry->id, $entries);
        $notifications = $this->candidate->notifications()->get();
        $this->assertCount(count($entries), $notifications);
        $this->assertEqualsCanonicalizing($ids, $notifications->map(fn ($notification): int => (int) data_get($notification->data, 'viewData.workflow_entry_id'))->all());
        $this->assertSame(['Frozen final notification OLD-42'], $notifications->pluck('data.body')->unique()->values()->all());
        $this->assertSame(count($entries), DB::table('workflow_notification_deliveries')->where('user_id', $this->candidate->id)->count());
    }

    private function businessState(CustomFormEntry $entry): array
    {
        return [$entry->candidate_id, $entry->review_status, $entry->custom_form_id, $entry->created_by,
            data_get($entry->data, 'answer'), data_get($entry->data, 'registration_status'), data_get($entry->data, 'candidate_status')];
    }

    private function storedRow(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function assertDenied(callable $callback, int $status = 403): void
    {
        try {
            $callback();
            $this->fail('The result action must enforce the frozen workflow role and menu.');
        } catch (AuthorizationException $exception) {
            $this->assertSame($status, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
