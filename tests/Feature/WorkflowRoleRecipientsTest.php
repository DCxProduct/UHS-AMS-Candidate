<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStaffRecipients;
use App\Support\UserTypeOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowRoleRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->candidate = $this->user('candidate', 'student');
        $this->form = CustomForm::query()->create([
            'name' => 'Role notification form',
            'slug' => 'role-notification-form',
            'is_active' => true,
        ]);
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('cashier_officer', 'web');
    }

    public static function notificationLocations(): array
    {
        return [
            'submission' => ['submission'],
            'checked' => ['accept'],
            'sent back' => ['send_back'],
            'rejected' => ['reject'],
            'payment' => ['payment'],
            'awaiting results' => ['awaiting_results'],
            'completed' => ['completed'],
        ];
    }

    #[DataProvider('notificationLocations')]
    public function test_every_notification_location_sends_to_all_ten_admin_role_members(string $location): void
    {
        $admins = collect(range(1, 10))->map(fn (int $number): User => $this->user('staff_'.$number, roles: ['admin']));
        $namedAdminWithoutRole = $this->user('Admin');
        $this->configure($location, ['staff_recipient_roles' => ['admin']]);
        $status = match ($location) {
            'payment' => 'approved',
            'awaiting_results' => 'passed',
            'completed' => 'completed',
            default => 'pending',
        };
        $entry = $this->entry($status, $location === 'submission');
        if (in_array($location, ['awaiting_results', 'completed'], true)) {
            $this->pay($entry);
        }
        $originalData = $entry->fresh()->data;

        $this->dispatch($entry, $location);

        foreach ($admins as $admin) {
            $notification = $admin->notifications()->sole();
            $this->assertSame('Staff: '.$location, $notification->data['body']);
            $this->assertSame($this->event($location), data_get($notification->data, 'viewData.workflow_event'));
        }
        $this->assertSame('Candidate: '.$location, $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame(0, $namedAdminWithoutRole->notifications()->count());
        $this->assertDatabaseCount('notifications', 11);

        $this->dispatch($entry, $location);
        $expected = in_array($location, ['send_back', 'reject'], true) ? 2 : 1;
        foreach ($admins as $admin) {
            $this->assertSame($expected, $admin->notifications()->count());
        }
        // Direct configured-stage sends keep their existing candidate repeat behavior.
        $candidateExpected = in_array($location, ['submission', 'accept'], true) ? 1 : 2;
        $this->assertSame($candidateExpected, $this->candidate->notifications()->count());
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($originalData, $entry->fresh()->data);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_role_membership_uses_spatie_and_linked_system_user_roles_and_excludes_ineligible_accounts(): void
    {
        $spatie = $this->user('spatie_admin', roles: ['admin']);
        $linked = $this->user('json_admin');
        SystemUser::query()->forceCreate([
            'name' => 'JSON role account',
            'username' => $linked->username,
            'email' => $linked->email,
            'roles' => ['admin'],
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->assertSame([], $linked->getRoleNames()->all());
        $owner = $this->user('application_owner', roles: ['admin']);
        $candidate = $this->user('candidate_with_admin_role', 'student', roles: ['admin']);
        $inactive = $this->user('inactive_admin', active: false, roles: ['admin']);
        $deleted = $this->user('deleted_admin', roles: ['admin']);
        $deleted->delete();
        $unassigned = $this->user('admin');
        $otherRole = $this->user('cashier', roles: ['cashier_officer']);

        $recipients = WorkflowStaffRecipients::resolve(['staff_recipient_roles' => ['admin']], $owner->id);

        $this->assertEqualsCanonicalizing([$spatie->id, $linked->id], $recipients->pluck('id')->all());
        foreach ([$owner, $candidate, $inactive, $deleted, $unassigned, $otherRole] as $excluded) {
            $this->assertNotContains($excluded->id, $recipients->pluck('id')->all());
        }
    }

    public function test_overlapping_selected_roles_send_only_one_bell_per_account(): void
    {
        $overlap = $this->user('overlapping_staff', roles: ['admin', 'cashier_officer']);
        $cashier = $this->user('cashier_staff', roles: ['cashier_officer']);
        $this->configure('accept', ['staff_recipient_roles' => ['admin', 'cashier_officer', 'admin']]);

        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'accept'));

        $this->assertSame(1, $overlap->notifications()->count());
        $this->assertSame(1, $cashier->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    public function test_legacy_candidate_and_student_roles_are_excluded_even_without_a_role_type(): void
    {
        Role::findOrCreate('candidate', 'web');
        Role::findOrCreate('student', 'web');
        $this->user('legacy_candidate_role_staff', roles: ['candidate', 'student']);

        $options = WorkflowStaffRecipients::roleOptions();
        $this->assertArrayNotHasKey('candidate', $options);
        $this->assertArrayNotHasKey('student', $options);
        $this->assertCount(0, WorkflowStaffRecipients::resolve(['staff_recipient_roles' => ['candidate', 'student']]));
    }

    public function test_untyped_candidate_catalog_roles_are_excluded_from_options_and_staff_delivery(): void
    {
        $candidateNames = collect(UserTypeOptions::defaultRecords())->pluck('key')
            ->merge(['inactive_custom_candidate', 'national_entrance_exam_application_legacy', 'NATIONAL_EXIT_EXAM_APPLICATION_LEGACY'])
            ->all();
        DB::table('user_types')->insert([
            'key' => 'inactive_custom_candidate', 'label_en' => 'Inactive custom candidate', 'label_kh' => 'ប្រភេទបេក្ខជនផ្ទាល់ខ្លួន',
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($candidateNames as $name) {
            Role::findOrCreate($name, 'web')->forceFill(['role_type_key' => null])->save();
        }
        $staff = $this->user('staff_with_candidate_types', roles: $candidateNames);
        Role::findOrCreate('custom_staff_role', 'web');
        $roleCount = Role::query()->count();
        $typeCount = DB::table('user_types')->count();

        $options = WorkflowStaffRecipients::roleOptions();

        foreach ($candidateNames as $name) {
            $this->assertArrayNotHasKey($name, $options);
        }
        $this->assertArrayHasKey('custom_staff_role', $options);
        $this->assertSame($roleCount, Role::query()->count());
        $this->assertSame($typeCount, DB::table('user_types')->count());
        $this->configure('accept', ['staff_recipient_roles' => $candidateNames]);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'accept'));
        $this->assertSame(0, $staff->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    public function test_new_role_members_are_resolved_at_delivery_time_without_repeating_existing_accept_bells(): void
    {
        $first = $this->user('first_admin', roles: ['admin']);
        $this->configure('accept', ['staff_recipient_roles' => ['admin']]);
        $entry = $this->entry();
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($entry, 'accept'));
        $late = $this->user('later_admin', roles: ['admin']);

        $this->assertFalse(WorkflowStageMessages::notifyReviewAction($entry, 'accept'));

        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $late->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    public static function emptyOrInvalidRoles(): array
    {
        return [
            'empty selection' => [[]],
            'unknown role' => [['unknown_role']],
            'candidate role' => [['candidate_only']],
            'different guard' => [['api_staff']],
            'invalid values' => [[null, 5, [], '  ']],
            'scalar role' => ['admin'],
        ];
    }

    #[DataProvider('emptyOrInvalidRoles')]
    public function test_explicit_empty_or_invalid_roles_never_broadcast_or_fall_back_to_legacy_ids(mixed $roles): void
    {
        $staff = $this->user('selected_legacy_staff', roles: ['admin']);
        Role::query()->create(['name' => 'candidate_only', 'role_type_key' => 'candidate', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'api_staff', 'guard_name' => 'api']);
        $staff->assignRole('candidate_only');
        $messages = ['staff_recipient_roles' => $roles, 'staff_recipient_ids' => [$staff->id]];
        $this->assertCount(0, WorkflowStaffRecipients::resolve($messages));
        $this->configure('accept', $messages);

        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'accept'));

        $this->assertSame(0, $staff->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    public function test_legacy_explicit_user_ids_are_preserved_until_roles_are_configured(): void
    {
        $staff = $this->user('legacy_selected_staff');
        $unselected = $this->user('legacy_unselected_staff', roles: ['admin']);
        $inactive = $this->user('legacy_inactive_staff', active: false);
        $messages = ['staff_recipient_ids' => [
            $staff->id, (string) $staff->id, $inactive->id, $this->candidate->id, 'invalid', -1,
        ]];
        $this->assertSame([$staff->id], WorkflowStaffRecipients::resolve($messages)->pluck('id')->all());
        $workflow = $this->configure('submission', $messages);
        $this->assertArrayNotHasKey('staff_recipient_roles', $workflow->fresh()->stages[0]['data']['submission_messages']);
        $entry = $this->entry(submitted: true);

        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(0, $unselected->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
    }

    public function test_saving_role_selections_normalizes_all_seven_locations_without_rewriting_workflow_messages(): void
    {
        $messages = [
            'status_message' => 'Keep the shared status',
            'notification_message' => 'Keep candidate notification',
            'staff_notification_message' => 'Keep staff notification',
            'staff_recipient_roles' => [' admin ', 'admin', 'cashier_officer', '', 5, null],
            'staff_recipient_ids' => [$this->candidate->id],
        ];
        $workflow = WorkflowNotification::query()->create([
            'name' => 'All configured role locations',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Submit', 'stage_type' => 'form_submission',
                    'submission_messages' => $messages,
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'],
                    'review_actions' => array_fill_keys(['accept', 'send_back', 'reject'], $messages),
                ]],
                ...collect(['payment', 'awaiting_results', 'completed'])->map(fn (string $type): array => [
                    'type' => 'stage', 'data' => [
                        'stage_name' => $type, 'stage_type' => $type,
                        'notification_channels' => ['system', 'email'],
                        'audience_messages' => $messages,
                    ],
                ])->all(),
            ],
        ]);
        $stages = $workflow->fresh()->stages;
        $locations = [
            $stages[0]['data']['submission_messages'],
            ...array_values($stages[1]['data']['review_actions']),
            ...array_map(fn (array $stage): array => $stage['data']['audience_messages'], array_slice($stages, 2)),
        ];
        $this->assertCount(7, $locations);
        foreach ($locations as $saved) {
            $this->assertSame(['admin', 'cashier_officer'], $saved['staff_recipient_roles']);
            $this->assertArrayNotHasKey('staff_recipient_ids', $saved);
            foreach (['status_message', 'notification_message', 'staff_notification_message'] as $field) {
                $this->assertSame($messages[$field], $saved[$field]);
            }
        }
        $this->assertSame(['form_submission', 'review', 'payment', 'awaiting_results', 'completed'], array_column(array_column($stages, 'data'), 'stage_type'));
        $this->assertSame(['admin'], $stages[1]['data']['responsible_role']);
        $this->assertSame(['system', 'email'], $stages[2]['data']['notification_channels']);
    }

    private function configure(string $location, array $recipientSettings): WorkflowNotification
    {
        $messages = ['staff_notification_message' => 'Staff: '.$location, ...$recipientSettings];
        $type = in_array($location, ['accept', 'send_back', 'reject'], true) ? 'review' : ($location === 'submission' ? 'form_submission' : $location);
        $data = [
            'stage_name' => 'Configured '.$type,
            'stage_type' => $type,
            'status_message' => 'Status: '.$location,
            'notification_message' => 'Candidate: '.$location,
            'notification_channels' => ['system'],
        ];
        if ($location === 'submission') {
            $data['submission_messages'] = [
                'candidate_status_message' => 'Submitted',
                'candidate_notification_message' => 'Candidate: '.$location,
                ...$messages,
            ];
        } elseif ($type === 'review') {
            $data['review_actions'][$location] = ['notification_message' => 'Candidate: '.$location, ...$messages];
        } else {
            $data['audience_messages'] = $messages;
        }
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Configured '.$location,
            'stages' => [['type' => 'stage', 'data' => $data]],
        ]);
        $workflow->forms()->attach($this->form);

        return $workflow;
    }

    private function dispatch(CustomFormEntry $entry, string $location): void
    {
        if ($location === 'submission') {
            WorkflowSubmissionMessages::notify($entry);
        } elseif (in_array($location, ['accept', 'send_back', 'reject'], true)) {
            WorkflowStageMessages::notifyReviewAction($entry, $location);
        } else {
            WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::from($location));
        }
    }

    private function event(string $location): string
    {
        return match ($location) {
            'submission' => 'submission_staff',
            'accept', 'send_back', 'reject' => 'review_action_staff:'.$location,
            default => 'stage_staff:'.$location,
        };
    }

    private function entry(string $status = 'pending', bool $submitted = false): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->id,
            'created_by' => $this->candidate->id,
            'review_status' => $status,
            'data' => array_filter([
                'registration_status' => $status,
                'candidate_status' => 'pending',
                'submitted_at' => $submitted ? now()->toDateTimeString() : null,
            ], fn ($value): bool => $value !== null),
        ]);
    }

    private function pay(CustomFormEntry $entry): void
    {
        DB::table('payments')->insert([
            'form_id' => $entry->custom_form_id,
            'custom_form_entry_id' => $entry->id,
            'users_id' => $entry->created_by,
            'status_payt' => 'paid',
            'datetime_pay' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(string $name, string $type = 'admin', bool $active = true, array $roles = []): User
    {
        $user = User::query()->forceCreate([
            'name' => $name,
            'username' => $name,
            'registration_type' => $type,
            'email' => $name.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => $active,
        ]);
        foreach ($roles as $role) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }
}
