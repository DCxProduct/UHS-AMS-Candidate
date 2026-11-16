<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsSelectionResultSync;
use App\Support\PassedResultMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AmsSelectionResultSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response($this->acknowledgement($request), 201));
        Mail::fake();
        config(['services.ams' => ['enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'test-secret', 'account_key' => 'test-account-key', 'timeout' => 2]]);
        $admin = $this->user('sync_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('sync_candidate', 'student');
        $this->form = CustomForm::query()->create([
            'name' => 'Selection', 'slug' => 'selection', 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'], 'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ]);
    }

    public function test_confirm_sends_and_hides_only_selected_results_without_notifying_or_changing_application_facts(): void
    {
        $selected = $this->entry();
        $unselected = $this->entry();
        $before = $selected->fresh()->getRawOriginal();
        Livewire::test(ListExamResults::class)->callAction('confirm_passed', arguments: [
            'selectedRecordKeys' => [$selected->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors()
            ->assertCanNotSeeTableRecords([$selected])->assertCanSeeTableRecords([$unselected])
            ->assertSet('selectedTableRecords', [])->assertSet('deselectedTableRecords', [])->assertSet('isTrackingDeselectedTableRecords', false);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ams.example.test/api/external/candidate/selection-results'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['application_id'] === (string) $selected->id
            && $request['result']['first_name_kh'] === 'សុខ'
            && $request['result']['last_name_en'] === 'Dara'
            && $request['result']['name_latin'] === 'SOK DARA'
            && $request['result']['academic_year'] === '2026-2027'
            && $request['result']['seat_number'] === 'MED-001'
            && $request['result']['date_of_birth'] === '2000-01-02'
            && $request['result']['program'] === 'Medicine');
        $this->assertSame(0, $this->candidate->notifications()->count());
        $after = $selected->fresh()->getRawOriginal();
        $this->assertNotNull($after['passed_entrance_deleted_at']);
        unset($before['passed_entrance_deleted_at'], $after['passed_entrance_deleted_at']);
        $this->assertSame($before, $after);
        $this->assertNull($unselected->fresh()->passed_entrance_deleted_at);
        Mail::assertNothingSent();
    }

    public function test_combined_action_notifies_then_hides_the_result_and_repeat_selection_does_not_resend(): void
    {
        $entry = $this->entry();
        $page = Livewire::test(ListExamResults::class);
        $arguments = ['selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1];
        $page->callAction('confirm_passed_and_notify', arguments: $arguments)->assertHasNoActionErrors()
            ->assertCanNotSeeTableRecords([$entry]);
        $this->assertNotNull($entry->fresh()->passed_entrance_deleted_at);
        $this->assertSame(1, $this->candidate->notifications()->count());
        $page->callAction('confirm_passed_and_notify', arguments: $arguments)->assertHasNoActionErrors();
        $this->assertSame(1, $this->candidate->notifications()->count());
        Http::assertSentCount(1);
    }

    public function test_combined_action_uses_the_frozen_candidate_workflow_notification_after_ams_accepts(): void
    {
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Selection final notification',
            'stages' => [['type' => 'stage', 'data' => [
                'stage_type' => 'completed', 'stage_name' => 'Completed', 'responsible_role' => ['admin'],
                'notification_channels' => ['system'], 'notification_message' => 'Your confirmed result is available.',
                'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
            ]]],
        ]);
        $workflow->forms()->attach($this->form);
        $entry = $this->entry();
        $entry->forceFill(['workflow_snapshot' => array_replace(WorkflowEntrySnapshot::forForm($this->form->id), ['schema_version' => 1])])->saveQuietly();
        $stages = $workflow->stages;
        $stages[0]['data']['notification_message'] = 'Changed template must not replace the frozen message.';
        $workflow->update(['stages' => $stages]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $this->assertSame(0, $this->candidate->notifications()->count());

            return Http::response($this->acknowledgement($request, '52'));
        });
        Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors();
        $this->assertSame('Your confirmed result is available.', $this->candidate->notifications()->sole()->data['body']);
        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }

    public function test_remote_failure_or_wrong_acknowledgement_never_releases_notification(): void
    {
        $entry = $this->entry();
        foreach ([Http::response([], 503), Http::response(['application_id' => 'wrong', 'selection_result_id' => '51', 'status' => 'synced'])] as $response) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(fn () => $response);
            Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
                'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
            ])->assertHasNoActionErrors()->assertNotified();
            $this->assertSame(0, $this->candidate->notifications()->count());
            $this->assertNull($entry->fresh()->passed_entrance_deleted_at);
            Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry]);
        }
    }

    public function test_empty_selection_never_sends_all_and_missing_required_data_never_sends(): void
    {
        $entry = $this->entry(['date_of_birth' => 'invalid-date']);
        Livewire::test(ListExamResults::class)->callAction('confirm_passed')->assertHasNoActionErrors();
        Livewire::test(ListExamResults::class)->callAction('confirm_passed', arguments: ['selectedRecordKeys' => [null]])->assertHasNoActionErrors();
        Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors();
        Http::assertNothingSent();
        $this->assertSame(0, $this->candidate->notifications()->count());
    }

    public function test_yearless_selected_result_is_sent_without_inventing_a_year_or_notifying(): void
    {
        $entry = $this->entry(['academic_year' => null]);
        Livewire::test(ListExamResults::class)->callAction('confirm_passed', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['application_id'] === (string) $entry->id
            && $request['result']['academic_year'] === null);
        $this->assertSame(0, $this->candidate->notifications()->count());
        $this->assertNull(data_get($entry->fresh()->data, 'academic_year'));
    }

    public function test_partial_bulk_failure_only_notifies_the_candidate_result_accepted_by_ams(): void
    {
        $accepted = $this->entry();
        $failed = $this->entry();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $request['application_id'] === (string) $accepted->id
            ? Http::response($this->acknowledgement($request, '55'))
            : Http::response([], 503));
        Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
            'selectedRecordKeys' => [$accepted->id, $failed->id], 'selectedRecordsCount' => 2,
        ])->assertHasNoActionErrors()->assertCanNotSeeTableRecords([$accepted])->assertCanSeeTableRecords([$failed]);
        $this->assertNotNull($accepted->fresh()->passed_entrance_deleted_at);
        $this->assertNull($failed->fresh()->passed_entrance_deleted_at);
        Http::assertSentCount(2);
        $notification = $this->candidate->notifications()->sole();
        $this->assertSame((string) $accepted->id, (string) data_get($notification->data, 'viewData.review_result_entry_id'));
    }

    public function test_select_all_respects_filters_and_deselected_records_and_excludes_other_menu(): void
    {
        $selected = $this->entry();
        $deselected = $this->entry();
        $this->entry(['academic_year' => '2025-2026']);
        $other = $this->entry();
        $otherForm = $this->form->replicate();
        $otherForm->slug = 'exit-results';
        $otherForm->passed_result_menu = PassedResultMenuOptions::EXIT_EXAM_RESULTS;
        $otherForm->save();
        $other->forceFill(['custom_form_id' => $otherForm->id])->saveQuietly();
        Livewire::test(ListExamResults::class)
            ->filterTable('exam_result_filters', ['academic_year' => '2026-2027'])
            ->callAction('confirm_passed', arguments: [
                'selectedRecordsCount' => 1, 'isTrackingDeselectedRecords' => true, 'deselectedRecordKeys' => [$deselected->id],
            ])->assertHasNoActionErrors();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request['application_id'] === (string) $selected->id);
        Livewire::test(ListExitExamResults::class)->assertActionHidden('confirm_passed')->assertActionHidden('confirm_passed_and_notify');
    }

    public function test_direct_service_call_requires_authorized_staff_and_a_record_in_the_entrance_results_menu(): void
    {
        $entry = $this->entry();
        $this->actingAs($this->candidate);
        try {
            app(AmsSelectionResultSync::class)->sync($entry, true);
            $this->fail('Candidate must not send results.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        Http::assertNothingSent();
    }

    public function test_selected_candidates_credentials_are_encrypted_and_keep_the_existing_password_hash(): void
    {
        $this->candidate->forceFill(['phone' => '015916217', 'name_latin' => 'SITHAN VIRATANA'])->saveQuietly();
        $entry = $this->entry();
        Livewire::test(ListExamResults::class)->callAction('confirm_passed', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors()->assertCanNotSeeTableRecords([$entry]);
        Http::assertSent(function (Request $request): bool {
            $encrypter = new Encrypter(hash('sha256', 'uhs-selection-account-v1|test-account-key', true), 'AES-256-CBC');
            $account = json_decode($encrypter->decryptString($request['account']), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame((string) $this->candidate->id, $account['source_account_id']);
            $this->assertSame('015916217', $account['phone']);
            $this->assertSame('sync_candidate', $account['username']);
            $this->assertTrue(Hash::check('password', $account['password_hash']));
            $this->assertTrue(hash_equals($this->candidate->getRawOriginal('password'), $account['password_hash']));
            $this->assertStringNotContainsString($account['password_hash'], $request->body());
            $this->assertStringNotContainsString('password_hash', $request->body());

            return true;
        });
    }

    public function test_result_only_acknowledgement_does_not_hide_or_notify_before_credentials_are_stored(): void
    {
        $entry = $this->entry();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => Http::response([
            'application_id' => $request['application_id'], 'selection_result_id' => '51', 'status' => 'synced',
        ]));
        Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors()->assertCanSeeTableRecords([$entry]);
        $this->assertSame(0, $this->candidate->notifications()->count());
        $this->assertNull($entry->fresh()->passed_entrance_deleted_at);
    }

    public function test_missing_credential_encryption_key_never_transmits_or_hides_the_candidate(): void
    {
        $entry = $this->entry();
        config(['services.ams.account_key' => '']);
        Livewire::test(ListExamResults::class)->callAction('confirm_passed_and_notify', arguments: [
            'selectedRecordKeys' => [$entry->id], 'selectedRecordsCount' => 1,
        ])->assertHasNoActionErrors()->assertCanSeeTableRecords([$entry]);
        Http::assertNothingSent();
        $this->assertSame(0, $this->candidate->notifications()->count());
    }

    public static function bulkConfirmActions(): array
    {
        return [
            'confirm without notifying' => ['confirm_passed', false],
            'confirm and notify' => ['confirm_passed_and_notify', true],
        ];
    }

    #[DataProvider('bulkConfirmActions')]
    public function test_multiple_selected_candidates_each_send_their_own_credentials(string $action, bool $notify): void
    {
        $owners = [
            [$this->candidate, '015916217', 'AlphaOwnPassword123'],
            [$this->user('bulk_second', 'student'), '012345678', 'BetaOwnPassword123'],
            [$this->user('bulk_third', 'student'), '011122233', 'GammaOwnPassword123'],
        ];
        $expected = [];
        $selected = [];
        foreach ($owners as $index => [$owner, $phone, $password]) {
            $owner->forceFill(['phone' => $phone, 'password' => $password])->saveQuietly();
            $entry = $this->entry(['first_name_en' => 'OWNER-'.$index], candidate: $owner);
            $selected[] = $entry;
            $expected[(string) $entry->id] = [$owner, $password];
        }
        // Multiple successful applications for the same person still use that person's identity.
        $secondApplication = $this->entry(['major' => 'Nursing'], candidate: $this->candidate);
        $selected[] = $secondApplication;
        $expected[(string) $secondApplication->id] = [$this->candidate, 'AlphaOwnPassword123'];
        $unselectedOwner = $this->user('bulk_unselected', 'student');
        $unselected = $this->entry(candidate: $unselectedOwner);

        $page = Livewire::test(ListExamResults::class);
        $arguments = ['selectedRecordKeys' => array_map(fn ($entry) => $entry->id, $selected), 'selectedRecordsCount' => count($selected)];
        $page->callAction($action, arguments: $arguments)->assertHasNoActionErrors()
            ->assertCanNotSeeTableRecords($selected)->assertCanSeeTableRecords([$unselected]);
        Http::assertSentCount(4);
        $seen = [];
        foreach (Http::recorded() as [$request]) {
            $applicationId = (string) $request['application_id'];
            $this->assertArrayHasKey($applicationId, $expected);
            [$owner, $password] = $expected[$applicationId];
            $account = $this->decryptedAccount($request);
            $this->assertSame((string) $owner->id, $account['source_account_id']);
            $this->assertSame($owner->username, $account['username']);
            $this->assertSame($owner->email, $account['email']);
            $this->assertSame($owner->phone, $account['phone']);
            $this->assertTrue(Hash::check($password, $account['password_hash']));
            $this->assertTrue(hash_equals($owner->getRawOriginal('password'), $account['password_hash']));
            $seen[] = $applicationId;
        }
        $this->assertEqualsCanonicalizing(array_keys($expected), $seen);
        foreach ($owners as [$owner]) {
            $expectedNotifications = $notify ? ($owner->id === $this->candidate->id ? 2 : 1) : 0;
            $this->assertSame($expectedNotifications, $owner->notifications()->count());
        }
        $this->assertSame(0, $unselectedOwner->notifications()->count());
        $page->callAction($action, arguments: $arguments)->assertHasNoActionErrors();
        Http::assertSentCount(4);
        foreach ($owners as [$owner]) {
            $expectedNotifications = $notify ? ($owner->id === $this->candidate->id ? 2 : 1) : 0;
            $this->assertSame($expectedNotifications, $owner->notifications()->count());
        }
    }

    private function decryptedAccount(Request $request): array
    {
        $encrypter = new Encrypter(hash('sha256', 'uhs-selection-account-v1|test-account-key', true), 'AES-256-CBC');

        return json_decode($encrypter->decryptString($request['account']), true, flags: JSON_THROW_ON_ERROR);
    }

    private function acknowledgement(Request $request, string $resultId = '51'): array
    {
        $sourceId = $this->decryptedAccount($request)['source_account_id'];

        return [
            'application_id' => $request['application_id'], 'selection_result_id' => $resultId, 'status' => 'synced',
            'source_account_id' => $sourceId, 'account_id' => (string) (1000 + (int) $sourceId),
            'provisional_account_id' => (string) (2000 + (int) $sourceId), 'account_status' => 'provisional',
        ];
    }

    private function entry(array $data = [], ?User $candidate = null): CustomFormEntry
    {
        return CustomFormEntry::withoutEvents(fn () => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->id, 'created_by' => ($candidate ?? $this->candidate)->id, 'candidate_id' => 'MED-001',
            'review_status' => 'passed', 'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => false, 'stages' => []],
            'data' => array_replace([
                'candidate_status' => 'passed', 'registration_status' => 'passed',
                'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារា', 'first_name_en' => 'Sok', 'last_name_en' => 'Dara',
                'gender' => 'male', 'date_of_birth' => '2000-01-02', 'academic_year' => '2026-2027',
                'major' => 'Medicine', 'degree_level' => 'Bachelor',
            ], $data),
        ]));
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
