<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\EditNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ListNewStudentAccounts;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\User;
use App\Support\StudentAccountArchive;
use App\Support\UserTypeOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class StudentAccountArchiveTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/external/ams/new-student-accounts';

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config()->set('ams.student_accounts_token', 'archive-test-token');
        Http::fake();
        Mail::fake();
        $this->operator = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Archive operator', 'username' => 'archive-operator',
            'email' => 'archive-operator@example.test', 'password' => 'OperatorPassword1!',
            'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $this->actingAs($this->operator);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_archive_tabs_and_actions_are_removed_even_with_stale_role_and_direct_grants(): void
    {
        $this->grant('ViewAny', 'ArchiveAccount', 'RestoreAccount');
        $role = Role::findOrCreate('legacy-archive-operator', 'web');
        $role->givePermissionTo(['ArchiveAccount:NewStudentAccount', 'RestoreAccount:NewStudentAccount']);
        $this->operator->assignRole($role);
        [$account, $student, $entry, $payment] = $this->studentAccount();
        $archived = NewStudentAccount::query()->create([
            'student_id' => 'LEGACY-ARCHIVED', 'first_name_latin' => 'LEGACY', 'last_name_latin' => 'STUDENT', 'is_active' => false,
        ]);
        $this->setLegacyArchiveState($archived, true);
        $before = $this->storedState();
        $history = [$entry->getRawOriginal(), $payment->getRawOriginal(), $this->authorizationState($student)];

        $list = Livewire::test(ListNewStudentAccounts::class)
            ->assertCanSeeTableRecords([$account])->assertCanNotSeeTableRecords([$archived])
            ->assertTableActionDoesNotExist('archive_account', record: $account)
            ->assertTableActionDoesNotExist('restore_account', record: $account);
        $this->assertSame([], $list->instance()->getTabs());
        foreach (['current', 'archived'] as $oldTab) {
            $list->set('activeTab', $oldTab)->assertCanSeeTableRecords([$account])->assertCanNotSeeTableRecords([$archived]);
        }
        foreach ([$account, $archived] as $record) {
            $this->assertFalse(NewStudentAccountResource::can('archiveAccount', $record));
            $this->assertFalse(NewStudentAccountResource::can('restoreAccount', $record));
        }
        $list->mountTableAction('archive_account', $account)->callMountedTableAction();
        $list->mountTableAction('restore_account', $archived)->callMountedTableAction();
        $this->assertSame($before, $this->storedState());
        $this->assertSame($history, [$entry->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), $this->authorizationState($student)]);
    }

    public function test_legacy_archive_ends_existing_sessions_and_blocks_every_login_identifier(): void
    {
        $this->grant('ViewAny');
        [$account, $student] = $this->studentAccount();
        Auth::logout();
        Auth::login($student);
        $sessionKey = Auth::guard()->getName();
        $this->withSession(['archive_session_marker' => 'retained'])
            ->get('/dashboard')->assertOk()->assertSessionHas($sessionKey, $student->getKey());

        // Simulate another staff request while this session still holds its
        // authenticated student model from before the archive operation.
        Auth::setUser($this->operator);
        $this->setLegacyArchiveState($account, true);
        Auth::setUser($student);
        $this->assertTrue($student->is_active);
        $this->get('/dashboard')->assertRedirect('/login')
            ->assertSessionMissing('archive_session_marker')->assertSessionMissing($sessionKey);
        $this->assertGuest();

        foreach ([$account->phone, $account->email, $account->student_id] as $identifier) {
            Livewire::test(Login::class)->fillForm(['login' => $identifier, 'password' => 'StudentPassword1!'])
                ->call('authenticate')->assertHasFormErrors(['login']);
            $this->assertGuest();
        }

    }

    public function test_update_and_activation_permissions_do_not_authorize_removed_archive_actions(): void
    {
        $this->grant('ViewAny', 'Update', 'ActivateAccount', 'DeactivateAccount');
        [$account] = $this->studentAccount();
        $before = $this->storedState();
        $this->assertFalse(NewStudentAccountResource::can('archiveAccount', $account));
        $this->assertFalse(NewStudentAccountResource::can('restoreAccount', $account));
        $this->assertSame($before, $this->storedState());
    }

    public function test_stale_and_quiet_model_saves_cannot_change_current_archive_state_or_rearchive_a_restored_account(): void
    {
        $this->grant('ArchiveAccount', 'RestoreAccount');
        [$account, $student] = $this->studentAccount();
        $staleManagement = $account->fresh();
        $staleLogin = $student->fresh();
        $this->setLegacyArchiveState($account, true);
        $archivedAt = $account->fresh()->getRawOriginal('archived_at');

        foreach (['save', 'saveQuietly'] as $save) {
            $management = clone $staleManagement;
            $management->forceFill(['archived_at' => null, 'is_active' => true, 'last_name_latin' => 'UPDATED']);
            $management->{$save}();
            $login = clone $staleLogin;
            $login->forceFill(['archived_at' => null, 'is_active' => true, 'name_latin' => 'SOK UPDATED']);
            $login->{$save}();
            $this->assertSame($archivedAt, $account->fresh()->getRawOriginal('archived_at'));
            $this->assertFalse($account->fresh()->is_active);
        }

        $staleArchived = $account->fresh();
        $this->setLegacyArchiveState($account->fresh(), false);
        $staleArchived->forceFill(['last_name_latin' => 'RESTORED'])->saveQuietly();
        $current = $account->fresh();
        $this->assertNull($current->archived_at);
        $this->assertTrue($current->is_active);
        $current->forceFill(['archived_at' => now()])->saveQuietly();
        $this->assertNull($account->fresh()->archived_at);
        $this->assertTrue(Hash::check('StudentPassword1!', $account->fresh()->password));
    }

    public function test_legacy_archive_cannot_be_bypassed_by_activation_or_edit(): void
    {
        $this->grant('ViewAny', 'Update', 'ArchiveAccount', 'RestoreAccount', 'ActivateAccount', 'DeactivateAccount');
        [$account] = $this->studentAccount();
        $this->setLegacyArchiveState($account, true);
        $before = $this->storedState();
        $this->assertRejected(fn () => StudentAccountArchive::setActive($account->fresh(), true));
        $editor = Livewire::test(EditNewStudentAccount::class, ['record' => $account->getRouteKey()])
            ->assertFormFieldIsDisabled('is_active');
        $writer = new ReflectionMethod(EditNewStudentAccount::class, 'handleRecordUpdate');
        $this->assertRejected(fn () => $writer->invoke($editor->instance(), $account->fresh(), ['is_active' => true]));
        $this->assertSame($before, $this->storedState());

    }

    public function test_archived_contacts_remain_reserved_and_ams_retries_never_restore_or_duplicate_the_account(): void
    {
        $this->grant('ArchiveAccount');
        [$account, $student] = $this->studentAccount();
        $this->setLegacyArchiveState($account, true);
        $before = $this->storedState();
        Auth::logout();
        $registration = Livewire::test(Register::class);
        $registration->fillForm([
            'student_role' => array_key_first(UserTypeOptions::options()), 'first_name_en' => 'DUPLICATE', 'last_name_en' => 'CANDIDATE',
            'email' => $account->email, 'phone' => $account->phone,
            'password' => 'AnotherPassword1!', 'password_confirmation' => 'AnotherPassword1!',
            'captcha_answer' => session('register_captcha_answer'),
        ])->call('register')->assertHasFormErrors(['email', 'phone']);
        $this->assertGuest();
        $this->assertSame($before, $this->storedState());

        $archivedAt = $account->fresh()->getRawOriginal('archived_at');
        $this->withToken('archive-test-token')->postJson(self::ENDPOINT, $this->payload())
            ->assertOk()->assertJsonPath('data.id', $account->id);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertSame($archivedAt, $account->fresh()->getRawOriginal('archived_at'));
        $this->assertSame($student->getKey(), $account->fresh()->auth_id);
        $this->assertTrue(Hash::check('StudentPassword1!', $account->fresh()->password));
        $before = $this->storedState();
        $this->withToken('archive-test-token')->postJson(self::ENDPOINT, $this->payload(['student_id' => 'DIFFERENT-STUDENT-ID']))->assertConflict();
        $this->assertSame($before, $this->storedState());
        $this->assertDatabaseCount('new_student_accounts', 1);
        $this->assertDatabaseMissing('users', ['id' => $student->getKey()]);
    }

    public function test_candidate_accounts_cannot_use_removed_archive_actions_even_with_stale_permissions(): void
    {
        [$account, $student] = $this->studentAccount();
        $student->givePermissionTo([
            Permission::findOrCreate('ArchiveAccount:NewStudentAccount', 'web'),
            Permission::findOrCreate('RestoreAccount:NewStudentAccount', 'web'),
        ]);
        $this->actingAs($student);
        $this->assertFalse(NewStudentAccountResource::can('archiveAccount', $account));
        $this->assertFalse(NewStudentAccountResource::can('restoreAccount', $account));
        $this->setLegacyArchiveState($account, true);
        $this->assertFalse(NewStudentAccountResource::can('restoreAccount', $account));
    }

    public function test_archive_migration_preserves_existing_credentials_and_history_and_rollback_leaves_archived_accounts_disabled(): void
    {
        $this->grant('ArchiveAccount');
        [$account, $student, $entry, $payment] = $this->studentAccount();
        $account->update(['phone' => '85512345678']);
        $migration = $this->archiveMigration();
        $history = [$entry->getRawOriginal(), $payment->getRawOriginal(), $this->authorizationState($student)];

        try {
            $migration->down();
            $before = $account->fresh()->getRawOriginal();
            $candidateBefore = $this->operator->fresh()->getRawOriginal();
            $migration->up();
            $this->assertNull($account->fresh()->archived_at);
            $this->assertSame($before, Arr::except($account->fresh()->getRawOriginal(), ['archived_at']));
            $this->assertSame($candidateBefore, $this->operator->fresh()->getRawOriginal());

            $this->setLegacyArchiveState($account->fresh(), true);
            $beforeRollback = Arr::except($account->fresh()->getRawOriginal(), ['archived_at']);
            $migration->down();
            $this->assertFalse(Schema::hasColumn('new_student_accounts', 'archived_at'));
            $this->assertSame($beforeRollback, $account->fresh()->getRawOriginal());
            $restored = AccountUser::query()->findOrFail($student->getKey());
            $this->assertInstanceOf(StudentLogin::class, $restored);
            $this->assertFalse($restored->is_active);
            $this->assertSame('85512345678', $restored->phone);
            $this->assertTrue(Hash::check('StudentPassword1!', $restored->password));
            $this->assertSame($history, [$entry->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), $this->authorizationState($student)]);
        } finally {
            if (! Schema::hasColumn('new_student_accounts', 'archived_at')) {
                $migration->up();
            }
        }
    }

    private function studentAccount(): array
    {
        $account = new NewStudentAccount;
        $account->forceFill([
            ...$this->payload(), 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'password' => 'StudentPassword1!', 'date_of_birth' => '2000-01-02',
            'candidate_roles' => ['candidate'], 'is_active' => true,
        ])->save();
        $account->refresh();
        $student = AccountUser::query()->findOrFail($account->auth_id);
        $this->assertInstanceOf(StudentLogin::class, $student);
        $student->assignRole(Role::findOrCreate('candidate', 'web'));
        $student->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $form = CustomForm::query()->create(['name' => 'Archive history', 'slug' => 'archive-history', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $student->getKey(), 'review_status' => 'approved',
            'candidate_id' => 'CAND-ARCHIVE-001', 'data' => ['answer' => 'Retained history', 'registration_status' => 'approved'],
        ])->saveQuietly();
        $payment = Payment::query()->create([
            'users_id' => $student->getKey(), 'received_by' => $this->operator->getKey(), 'form_id' => $form->id,
            'custom_form_entry_id' => $entry->getKey(), 'receipt_number' => 'ARCHIVE-001',
            'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now(),
        ]);

        return [$account->fresh(), $student->fresh(), $entry->fresh(), $payment->fresh()];
    }

    private function grant(string ...$actions): void
    {
        foreach ($actions as $action) {
            $this->operator->givePermissionTo(Permission::findOrCreate($action.':NewStudentAccount', 'web'));
        }
        $this->operator->unsetRelation('roles')->unsetRelation('permissions');
    }

    /** Seed historical archive states without bringing back the removed actions. */
    private function setLegacyArchiveState(NewStudentAccount $account, bool $archived): void
    {
        DB::table('new_student_accounts')->where('id', $account->getKey())->update([
            'archived_at' => $archived ? now() : null,
            'is_active' => ! $archived,
        ]);
        $account->refresh();
    }

    private function assertRejected(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Archived accounts must be restored explicitly before they can be enabled.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertContains($exception->getStatusCode(), [403, 409]);
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function authorizationState(User $student): array
    {
        return [
            DB::table('model_has_roles')->where('model_type', User::class)->where('model_id', $student->getKey())->orderBy('role_id')->get()->toJson(),
            DB::table('model_has_permissions')->where('model_type', User::class)->where('model_id', $student->getKey())->orderBy('permission_id')->get()->toJson(),
        ];
    }

    private function storedState(): array
    {
        return collect(['new_student_accounts', 'account_identities', 'users', 'custom_form_entries', 'payments'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function archiveMigration(): Migration
    {
        return require database_path('migrations/2026_11_18_000001_add_archive_state_to_new_student_accounts_table.php');
    }

    private function payload(array $overrides = []): array
    {
        return [
            'student_id' => 'STU-ARCHIVE-001', 'email' => 'archived-student@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA', ...$overrides,
        ];
    }
}
