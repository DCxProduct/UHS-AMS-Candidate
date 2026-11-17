<?php

namespace Tests\Feature;

use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\SystemUser;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmsStudentAccountTransferTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/external/ams/new-student-accounts';

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ams.student_accounts_token', 'local-transfer-test-token');
        Http::fake();
        Mail::fake();
    }

    public function test_authenticated_delivery_moves_the_candidate_record_and_preserves_login_and_application_history(): void
    {
        [$candidate, $login] = $this->candidate();
        $login->assignRole(Role::findOrCreate('candidate', 'web'));
        $login->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $form = CustomForm::query()->create(['name' => 'Existing application', 'slug' => 'existing-application', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $login->id,
            'review_status' => 'approved', 'candidate_id' => 'CAND-001',
            'data' => ['answer' => 'Preserved answer', 'registration_status' => 'approved'],
        ])->saveQuietly();
        $entry = $entry->fresh();
        $beforeLogin = $login->fresh()->getRawOriginal();
        $beforeEntry = $entry->getRawOriginal();
        $roles = $login->getRoleNames()->all();
        $permissions = $login->getDirectPermissions()->pluck('name')->all();

        $response = $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload());

        $response->assertCreated()->assertJsonPath('data.student_id', 'STU-0001');
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $account = NewStudentAccount::query()->sole();
        $this->assertSame('STU-0001', $account->student_id);
        $this->assertSame('candidate@example.test', $account->email);
        $this->assertSame('SOK', $account->first_name_latin);
        $this->assertSame('DARA', $account->last_name_latin);
        $promoted = $this->assertMovedLogin($login);
        $profile = array_diff_key($beforeLogin, array_flip(['id', 'created_at', 'updated_at', 'candidate_roles', 'source_candidate_id']));
        $this->assertSame($profile, array_intersect_key($promoted->getRawOriginal(), $profile));
        $this->assertSame($beforeEntry, $entry->fresh()->getRawOriginal());
        $this->assertSame($roles, $promoted->getRoleNames()->all());
        $this->assertSame($permissions, $promoted->getDirectPermissions()->pluck('name')->all());
        $this->assertTrue(Hash::check('candidate-password', $promoted->password));
        $this->assertSame(0, DB::table('notifications')->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_repeat_delivery_updates_the_same_student_account_without_restoring_the_candidate_row(): void
    {
        [$candidate, $login] = $this->candidate();
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $account = NewStudentAccount::query()->sole();
        $account->update(['is_active' => false]);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload(['last_name_latin' => 'VIREAK']))
            ->assertOk()->assertJsonPath('data.id', $account->id);

        $this->assertSame(1, NewStudentAccount::query()->count());
        $this->assertSame('VIREAK', $account->fresh()->last_name_latin);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertMovedLogin($login);
    }

    public function test_newly_transferred_account_keeps_the_candidate_inactive_state(): void
    {
        [$candidate, $login] = $this->candidate(['is_active' => false]);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $this->assertFalse(NewStudentAccount::query()->sole()->is_active);
        $this->assertFalse($this->assertMovedLogin($login)->is_active);
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
    }

    public function test_legacy_candidate_without_a_login_keeps_its_credentials_when_the_management_row_moves(): void
    {
        $candidate = SystemUser::query()->create([
            'name' => 'SOK DARA', 'username' => 'legacy-candidate',
            'email' => 'candidate@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
            'password' => 'legacy-candidate-password', 'roles' => ['candidate'], 'is_active' => true,
        ]);
        $this->assertSame(0, User::query()->count());

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $login = AccountUser::query()->where('username', 'legacy-candidate')->sole();
        $this->assertInstanceOf(StudentLogin::class, $login);
        $this->assertSame('student', $login->registration_type);
        $this->assertTrue(Hash::check('legacy-candidate-password', $login->password));
        $this->assertSame($login->id, NewStudentAccount::query()->sole()->user_id);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
    }

    public function test_retry_cannot_move_another_candidate_into_an_already_linked_student_id(): void
    {
        [$first, $firstLogin] = $this->candidate();
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $account = NewStudentAccount::query()->sole();
        $before = $account->getRawOriginal();
        [$second, $secondLogin] = $this->candidate(['email' => 'second@example.test', 'phone' => '098111222']);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'email' => $second->email, 'phone' => $second->phone,
        ]))->assertConflict();

        $this->assertSame($before, $account->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('system_users', ['id' => $first->id]);
        $this->assertModelExists($second);
        $this->assertMovedLogin($firstLogin);
        $this->assertModelExists($secondLogin);
        $this->assertSame(1, NewStudentAccount::query()->count());
    }

    public function test_a_contactless_retry_retains_the_original_login_link_and_other_candidates(): void
    {
        [$candidate, $login] = $this->candidate();
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();
        $account = NewStudentAccount::query()->sole();
        [$other, $otherLogin] = $this->candidate(['email' => 'other@example.test', 'phone' => '098111222']);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'email' => null, 'phone' => null,
        ]))->assertOk()->assertJsonPath('data.id', $account->id);

        $this->assertSame($login->id, $account->fresh()->user_id);
        $this->assertSame('candidate@example.test', $account->fresh()->email);
        $this->assertSame('012345678', $account->fresh()->phone);
        $this->assertMovedLogin($login);
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertModelExists($other);
        $this->assertModelExists($otherLogin);
        $this->assertSame(1, NewStudentAccount::query()->count());
    }

    public function test_an_existing_unlinked_student_record_can_finish_the_transfer_on_redelivery(): void
    {
        [$candidate, $login] = $this->candidate();
        $existing = $this->legacyStudentAccount(['is_active' => false]);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())
            ->assertOk()->assertJsonPath('data.id', $existing->id);

        $this->assertSame(1, NewStudentAccount::query()->count());
        $this->assertSame($login->id, $existing->fresh()->user_id);
        $this->assertFalse($existing->fresh()->is_active);
        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertMovedLogin($login);
    }

    public function test_conflicting_legacy_origins_reject_transfer_without_changing_account_or_application_history(): void
    {
        [$mirror, $login] = $this->candidate();
        DB::table('system_users')->where('id', $mirror->id)->delete();
        $login->forceFill(['source_candidate_id' => 227, 'candidate_roles' => ['candidate']])->saveQuietly();
        $account = $this->legacyStudentAccount([
            'auth_id' => $login->id, 'user_id' => $login->id, 'source_candidate_id' => 226,
        ]);
        $form = CustomForm::query()->create(['name' => 'Origin history', 'slug' => 'origin-history', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $login->id,
            'review_status' => 'approved', 'data' => ['answer' => 'Preserved response'],
        ])->saveQuietly();
        $beforeLogin = $login->fresh()->getRawOriginal();
        $beforeAccount = $account->fresh()->getRawOriginal();
        $beforeEntry = $entry->fresh()->getRawOriginal();

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertConflict();

        $this->assertSame($beforeLogin, $login->fresh()->getRawOriginal());
        $this->assertSame($beforeAccount, $account->fresh()->getRawOriginal());
        $this->assertSame($beforeEntry, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseHas('account_identities', ['id' => $login->id, 'storage' => 'candidate']);
        $this->assertSame(1, User::query()->whereKey($login->id)->count());
        $this->assertSame(1, NewStudentAccount::query()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_contactless_legacy_linked_retry_preserves_destination_email_and_specific_candidate_category(): void
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'username' => 'legacy-linked-candidate', 'email' => null, 'phone' => '012345678',
            'password' => 'legacy-linked-password', 'date_of_birth' => '2000-01-01',
            'candidate_roles' => ['candidate'], 'is_active' => true,
        ])->fresh();
        $category = 'legacy_specific_candidate_category';
        $existing = $this->legacyStudentAccount([
            'auth_id' => $candidate->id, 'user_id' => $candidate->id, 'source_candidate_id' => 999,
            'email' => 'destination@example.test', 'phone' => $candidate->phone,
            'candidate_roles' => json_encode([$category]),
        ]);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'email' => null, 'phone' => null,
        ]))->assertOk()->assertJsonPath('data.id', $existing->id);

        $login = $this->assertMovedLogin($candidate);
        $account = $existing->fresh();
        $this->assertSame('destination@example.test', $account->email);
        $this->assertSame('012345678', $account->phone);
        $this->assertSame([$category], $account->candidate_roles);
        $this->assertSame(999, $account->source_candidate_id);
        $this->assertSame('destination@example.test', $login->email);
        $this->assertSame([$category], $login->candidate_roles);
        $this->assertTrue(Hash::check('legacy-linked-password', $login->password));
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('new_student_accounts', 1);
    }

    public function test_an_email_match_can_transfer_a_candidate_when_the_ams_phone_is_missing(): void
    {
        [$candidate, $login] = $this->candidate(['email' => 'CANDIDATE@example.test']);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload(['phone' => null]))->assertCreated();

        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertMovedLogin($login);
        $this->assertSame('012345678', NewStudentAccount::query()->sole()->phone);
    }

    public function test_a_phone_only_match_transfers_the_candidate_and_preserves_the_login_identity(): void
    {
        [$candidate, $login] = $this->candidate(['email' => null]);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload(['email' => null]))->assertCreated();

        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertMovedLogin($login);
        $this->assertNull(NewStudentAccount::query()->sole()->email);
    }

    public function test_unmatched_contacts_or_absent_contacts_do_not_remove_unrelated_candidates(): void
    {
        [$candidate, $login] = $this->candidate();
        $before = $candidate->getRawOriginal();
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'email' => 'someone-else@example.test', 'phone' => '098111222',
        ]))->assertCreated();
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'student_id' => 'STU-0002', 'email' => null, 'phone' => null,
        ]))->assertCreated();

        $this->assertSame($before, $candidate->fresh()->getRawOriginal());
        $this->assertModelExists($login);
        $this->assertSame(2, NewStudentAccount::query()->count());
    }

    public function test_email_and_phone_matching_different_candidates_rejects_the_whole_transfer(): void
    {
        [$first, $firstLogin] = $this->candidate(['phone' => '012111222']);
        [$second, $secondLogin] = $this->candidate(['email' => 'other@example.test']);
        $before = [$first->getRawOriginal(), $second->getRawOriginal()];

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertConflict();

        $this->assertSame(0, NewStudentAccount::query()->count());
        $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal()]);
        $this->assertModelExists($firstLogin);
        $this->assertModelExists($secondLogin);
    }

    public function test_staff_and_admin_contact_matches_are_never_moved_or_deleted(): void
    {
        foreach ([['admin'], ['registrar_officer'], ['candidate', 'admin']] as $index => $roles) {
            $email = 'staff-'.$index.'@example.test';
            [$staff, $login] = $this->candidate(['email' => $email, 'phone' => '01222222'.$index, 'roles' => $roles], 'admin');
            $before = [$staff->getRawOriginal(), $login->getRawOriginal()];

            $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
                'student_id' => 'STAFF-'.$index, 'email' => $email, 'phone' => $staff->phone,
            ]))->assertConflict();

            $this->assertSame($before, [$staff->fresh()->getRawOriginal(), $login->fresh()->getRawOriginal()]);
        }
        $this->assertSame(0, NewStudentAccount::query()->count());
    }

    public function test_a_candidate_role_row_linked_to_an_admin_login_is_not_transferred(): void
    {
        [$candidate, $login] = $this->candidate([], 'admin');

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertConflict();

        $this->assertModelExists($candidate);
        $this->assertModelExists($login);
        $this->assertSame(0, NewStudentAccount::query()->count());
    }

    public function test_a_source_without_a_login_cannot_be_attached_to_an_unrelated_login_from_the_payload(): void
    {
        $candidate = SystemUser::query()->create([
            'name' => 'Candidate A', 'username' => 'candidate-a',
            'email' => 'candidate-a@example.test', 'phone' => '012345678',
            'password' => 'candidate-a-password', 'roles' => ['candidate'], 'is_active' => true,
        ])->fresh();
        $unrelated = User::query()->create([
            'registration_type' => 'student', 'name' => 'Candidate B', 'username' => 'candidate-b',
            'email' => 'candidate-b@example.test', 'phone' => '098111222',
            'date_of_birth' => '2000-01-01', 'password' => 'candidate-b-password', 'is_active' => true,
        ])->fresh();
        $before = [$candidate->getRawOriginal(), $unrelated->getRawOriginal()];

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload([
            'email' => $unrelated->email, 'phone' => $candidate->phone,
        ]))->assertConflict();

        $this->assertSame($before, [$candidate->fresh()->getRawOriginal(), $unrelated->fresh()->getRawOriginal()]);
        $this->assertSame(0, NewStudentAccount::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_student_email_conflict_rolls_back_without_removing_the_existing_candidate(): void
    {
        [$candidate, $login] = $this->candidate();
        $existing = $this->legacyStudentAccount(['student_id' => 'ANOTHER-STUDENT']);
        $before = $existing->getRawOriginal();

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertConflict();

        $this->assertSame(1, NewStudentAccount::query()->count());
        $this->assertSame($before, $existing->fresh()->getRawOriginal());
        $this->assertModelExists($candidate);
        $this->assertModelExists($login);
    }

    public function test_invalid_tokens_missing_configuration_and_invalid_payload_never_move_candidate_data(): void
    {
        [$candidate, $login] = $this->candidate();
        $this->withToken('wrong-token')->postJson(self::ENDPOINT, $this->payload())->assertUnauthorized();
        config()->set('ams.student_accounts_token', null);
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertStatus(503);
        config()->set('ams.student_accounts_token', 'local-transfer-test-token');
        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload(['first_name_latin' => '']))->assertUnprocessable();

        $this->assertModelExists($candidate);
        $this->assertModelExists($login);
        $this->assertSame(0, NewStudentAccount::query()->count());
    }

    public function test_reconciliation_previews_without_saving_then_moves_only_the_existing_candidate_management_record(): void
    {
        [$candidate, $login] = $this->candidate();
        $account = $this->legacyStudentAccount();
        $form = CustomForm::query()->create(['name' => 'Historical application', 'slug' => 'historical-application', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $login->id,
            'review_status' => 'approved', 'candidate_id' => 'CAND-001',
            'data' => ['answer' => 'Historical answer', 'registration_status' => 'approved'],
        ])->saveQuietly();
        $entry = $entry->fresh();
        $before = [$candidate->getRawOriginal(), $account->getRawOriginal(), $login->getRawOriginal(), $entry->getRawOriginal()];

        $this->artisan('ams:reconcile-student-account', ['student_id' => $account->student_id])
            ->expectsOutputToContain('Preview:')->assertSuccessful();

        $this->assertSame($before, [$candidate->fresh()->getRawOriginal(), $account->fresh()->getRawOriginal(), $login->fresh()->getRawOriginal(), $entry->fresh()->getRawOriginal()]);

        $this->artisan('ams:reconcile-student-account', ['student_id' => $account->student_id, '--apply' => true])
            ->expectsOutputToContain('Transfer complete')->assertSuccessful();

        $this->assertDatabaseMissing('system_users', ['id' => $candidate->id]);
        $this->assertSame(1, NewStudentAccount::query()->count());
        $this->assertSame($login->id, $account->fresh()->user_id);
        $this->assertSame($candidate->id, $account->fresh()->source_candidate_id);
        $promoted = $this->assertMovedLogin($login);
        $this->assertSame($before[2]['password'], $promoted->getRawOriginal('password'));
        $this->assertSame($before[2]['username'], $promoted->username);
        $this->assertSame($before[3], $entry->fresh()->getRawOriginal());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_reconciliation_of_conflicting_contacts_fails_without_partially_moving_any_records(): void
    {
        [$first, $firstLogin] = $this->candidate(['phone' => '012111222']);
        [$second, $secondLogin] = $this->candidate(['email' => 'second@example.test']);
        $account = $this->legacyStudentAccount();
        $before = [$first->getRawOriginal(), $second->getRawOriginal(), $account->getRawOriginal(), $firstLogin->getRawOriginal(), $secondLogin->getRawOriginal()];

        foreach ([false, true] as $apply) {
            $arguments = ['student_id' => $account->student_id];
            if ($apply) {
                $arguments['--apply'] = true;
            }
            $this->artisan('ams:reconcile-student-account', $arguments)
                ->expectsOutputToContain('Account identity conflict')->assertFailed();

            $this->assertSame($before, [$first->fresh()->getRawOriginal(), $second->fresh()->getRawOriginal(), $account->fresh()->getRawOriginal(), $firstLogin->fresh()->getRawOriginal(), $secondLogin->fresh()->getRawOriginal()]);
            $this->assertSame(2, SystemUser::query()->count());
            $this->assertSame(1, NewStudentAccount::query()->count());
        }
    }

    public function test_a_canonical_candidate_without_a_legacy_mirror_moves_to_student_storage_and_retains_credentials(): void
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'username' => 'canonical-candidate', 'email' => 'candidate@example.test', 'phone' => '012345678',
            'password' => 'canonical-password', 'date_of_birth' => '2000-01-01',
            'candidate_roles' => ['candidate'], 'is_active' => true,
        ])->fresh();
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'));
        $password = $candidate->password;

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        $login = $this->assertMovedLogin($candidate);
        $this->assertSame($password, $login->password);
        $this->assertTrue(Hash::check('canonical-password', $login->password));
        $this->assertTrue($login->hasRole('candidate'));
        $this->assertSame(['candidate'], $login->candidate_roles);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_users', 0);
        $this->assertDatabaseCount('new_student_accounts', 1);
    }

    public function test_reversed_ams_names_follow_the_existing_latin_name_order_on_transfer_and_retry(): void
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'SITHAN MANIKA', 'name_latin' => 'SITHAN MANIKA',
            'username' => 'latin-name-order-candidate', 'email' => 'candidate@example.test', 'phone' => '012345678',
            'password' => 'latin-name-order-password', 'date_of_birth' => '2000-01-01',
            'candidate_roles' => ['candidate'], 'is_active' => true,
        ])->fresh();
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'));
        $form = CustomForm::query()->create(['name' => 'Name order history', 'slug' => 'name-order-history', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id,
            'review_status' => 'approved', 'candidate_id' => 'CAND-NAME-ORDER',
            'data' => ['answer' => 'Original application', 'registration_status' => 'approved'],
        ])->saveQuietly();
        $history = $entry->fresh()->getRawOriginal();
        $password = $candidate->password;
        $payload = $this->payload(['first_name_latin' => 'MANIKA', 'last_name_latin' => 'SITHAN']);

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $payload)->assertCreated();

        $account = NewStudentAccount::query()->sole();
        $this->assertSame('SITHAN', $account->first_name_latin);
        $this->assertSame('MANIKA', $account->last_name_latin);
        $this->assertSame('SITHAN MANIKA', $account->name_latin);
        $this->assertSame($password, $this->assertMovedLogin($candidate)->password);
        $this->assertSame($history, $entry->fresh()->getRawOriginal());

        $this->withToken('local-transfer-test-token')->postJson(self::ENDPOINT, $payload)
            ->assertOk()->assertJsonPath('data.id', $account->id);

        $account->refresh();
        $login = $this->assertMovedLogin($candidate);
        $this->assertSame('SITHAN', $account->first_name_latin);
        $this->assertSame('MANIKA', $account->last_name_latin);
        $this->assertSame('SITHAN MANIKA', $account->name_latin);
        $this->assertSame('SITHAN MANIKA', $login->name_latin);
        $this->assertSame($password, $login->password);
        $this->assertTrue(Hash::check('latin-name-order-password', $login->password));
        $this->assertTrue($login->hasRole('candidate'));
        $this->assertSame($history, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('new_student_accounts', 1);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_users', 0);
    }

    private function assertMovedLogin(User $original): StudentLogin
    {
        $this->assertDatabaseMissing('users', ['id' => $original->getKey()]);
        $login = AccountUser::withTrashed()->findOrFail($original->getKey());
        $this->assertInstanceOf(StudentLogin::class, $login);
        $this->assertSame($original->getAuthIdentifier(), $login->getAuthIdentifier());
        $this->assertSame($original->getKey(), $login->id);
        $this->assertSame($original->getKey(), NewStudentAccount::query()->where('auth_id', $login->getKey())->sole()->auth_id);

        return $login;
    }

    /** Reproduce pre-canonical records that did not yet own a logical login. */
    private function legacyStudentAccount(array $overrides = []): NewStudentAccount
    {
        $id = DB::table('new_student_accounts')->insertGetId([
            ...$this->payload(), 'is_active' => true, 'auth_id' => null,
            'created_at' => now(), 'updated_at' => now(), ...$overrides,
        ]);

        return NewStudentAccount::query()->findOrFail($id);
    }

    private function candidate(array $overrides = [], string $registrationType = 'student'): array
    {
        $number = ++$this->sequence;
        $candidate = SystemUser::query()->create([
            'name' => 'SOK DARA', 'username' => 'transfer-candidate-'.$number,
            'email' => 'candidate@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
            'password' => 'candidate-password', 'roles' => ['candidate'], 'permissions' => [],
            'is_active' => true, ...$overrides,
        ])->fresh();
        $login = User::query()->create([
            'registration_type' => $registrationType,
            'name' => $candidate->name, 'name_latin' => 'SOK DARA', 'username' => $candidate->username,
            'email' => $candidate->email, 'phone' => $candidate->phone,
            'date_of_birth' => '2000-01-01', 'password' => $candidate->password,
            'is_active' => $candidate->is_active,
        ])->fresh();

        return [$candidate, $login];
    }

    private function payload(array $overrides = []): array
    {
        return [
            'student_id' => 'STU-0001', 'email' => 'candidate@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA', ...$overrides,
        ];
    }
}
