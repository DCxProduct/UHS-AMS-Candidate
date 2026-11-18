<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\User;
use App\Support\UserTypeOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StudentAccountRetentionTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/external/ams/new-student-accounts';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config()->set('ams.student_accounts_token', 'student-retention-test-token');
        Http::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_management_and_login_delete_variants_cannot_remove_a_promoted_student_or_its_history(): void
    {
        [$account, $authId, $entry, $payment, $staleCandidate] = $this->promotedStudent();
        $this->assertNotSame($account->id, $authId);
        $before = $this->storedState();

        foreach ([
            'management delete' => fn () => $account->fresh()->delete(),
            'management delete quietly' => fn () => $account->fresh()->deleteQuietly(),
            'management force delete' => fn () => $account->fresh()->forceDelete(),
            'management force delete quietly' => fn () => $account->fresh()->forceDeleteQuietly(),
            'login delete' => fn () => $this->student($authId)->delete(),
            'login delete quietly' => fn () => $this->student($authId)->deleteQuietly(),
            'login force delete' => fn () => $this->student($authId)->forceDelete(),
            'login force delete quietly' => fn () => $this->student($authId)->forceDeleteQuietly(),
            'stale original user delete' => fn () => (clone $staleCandidate)->delete(),
            'stale original user force delete' => fn () => (clone $staleCandidate)->forceDelete(),
            'stale original user force delete quietly' => fn () => (clone $staleCandidate)->forceDeleteQuietly(),
        ] as $operation => $delete) {
            try {
                $delete();
                $this->fail($operation.' must be rejected before identities, role pivots or history are changed.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors(), $operation);
            }

            $this->assertSame($before, $this->storedState(), $operation);
            $this->assertTrue(Hash::check('StudentPassword1!', $this->student($authId)->password));
        }
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_an_inactive_retained_student_still_reserves_its_contacts_for_registration_and_model_creation(): void
    {
        [$account] = $this->promotedStudent();
        $account->update(['is_active' => false]);
        $before = $this->storedState();
        Auth::logout();
        $registration = Livewire::test(Register::class);
        $registration->fillForm([
            'student_role' => array_key_first(UserTypeOptions::options()),
            'first_name_en' => 'ANOTHER', 'last_name_en' => 'CANDIDATE',
            'phone' => $account->phone, 'email' => $account->email,
            'password' => 'AnotherPassword1!', 'password_confirmation' => 'AnotherPassword1!',
            'captcha_answer' => session('register_captcha_answer'),
        ])->call('register')->assertHasFormErrors(['email', 'phone']);
        $this->assertGuest();

        try {
            User::query()->create([
                'registration_type' => 'student', 'name' => 'Another candidate', 'username' => 'another-candidate',
                'email' => $account->email, 'phone' => $account->phone,
                'password' => 'AnotherPassword1!', 'date_of_birth' => '2000-01-01', 'is_active' => true,
            ]);
            $this->fail('An inactive retained student must continue to reserve its contact details.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        $this->assertSame($before, $this->storedState());
    }

    public function test_unpromoted_candidate_accounts_keep_their_existing_soft_and_permanent_deletion_behavior(): void
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'Unpromoted candidate', 'username' => 'unpromoted-candidate',
            'email' => 'unpromoted@example.test', 'phone' => '098111222',
            'password' => 'CandidatePassword1!', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $id = $candidate->id;
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'));

        $this->assertTrue($candidate->delete());
        $this->assertSoftDeleted('users', ['id' => $id]);
        $this->assertDatabaseHas('account_identities', ['id' => $id, 'storage' => 'candidate']);
        $this->assertTrue($candidate->forceDelete());

        $this->assertDatabaseMissing('users', ['id' => $id]);
        $this->assertDatabaseMissing('account_identities', ['id' => $id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_type' => User::class, 'model_id' => $id]);
    }

    public function test_reactivation_restores_student_id_login_with_the_original_password_profile_and_ownership(): void
    {
        [$account, $authId, $entry, $payment] = $this->promotedStudent();
        $beforeEntry = $entry->getRawOriginal();
        $beforePayment = $payment->getRawOriginal();
        $beforeProfile = array_intersect_key($account->getRawOriginal(), array_flip([
            'student_id', 'auth_id', 'password', 'name_latin', 'first_name_kh', 'last_name_kh', 'date_of_birth', 'academic_year', 'seat_number',
        ]));
        $account->update(['is_active' => false]);
        Auth::logout();

        Livewire::test(Login::class)
            ->fillForm(['login' => 'STU-RETAIN-0001', 'password' => 'StudentPassword1!'])
            ->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();

        $account->fresh()->update(['is_active' => true]);
        Livewire::test(Login::class)
            ->fillForm(['login' => 'STU-RETAIN-0001', 'password' => 'StudentPassword1!'])
            ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/dashboard');

        $this->assertInstanceOf(StudentLogin::class, Auth::user());
        $this->assertSame($authId, (int) Auth::id());
        $this->assertSame($beforeProfile, array_intersect_key($account->fresh()->getRawOriginal(), $beforeProfile));
        $this->assertSame($beforeEntry, $entry->fresh()->getRawOriginal());
        $this->assertSame($beforePayment, $payment->fresh()->getRawOriginal());
        $this->assertTrue($this->student($authId)->hasRole('candidate'));
        $this->assertTrue($this->student($authId)->hasDirectPermission('ViewAny:CustomFormEntry'));
    }

    public function test_retry_retains_the_inactive_student_id_and_rejects_a_different_id_for_the_same_person(): void
    {
        [$account, $authId] = $this->promotedStudent();
        $account->update(['is_active' => false]);
        $identityCount = DB::table('account_identities')->count();

        $this->withToken('student-retention-test-token')->postJson(self::ENDPOINT, $this->payload())
            ->assertOk()->assertJsonPath('data.id', $account->id);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertSame($authId, $account->fresh()->auth_id);
        $this->assertDatabaseCount('new_student_accounts', 1);
        $this->assertSame($identityCount, DB::table('account_identities')->count());
        $beforeConflict = $this->storedState();

        $this->withToken('student-retention-test-token')->postJson(self::ENDPOINT, $this->payload([
            'student_id' => 'STU-DIFFERENT-0002',
        ]))->assertConflict();

        $this->assertSame($beforeConflict, $this->storedState());
    }

    private function promotedStudent(): array
    {
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Retention administrator', 'username' => 'retention-admin',
            'password' => 'AdminPassword1!', 'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $candidate = User::query()->forceCreate([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារា',
            'username' => 'retained-candidate', 'email' => 'retained@example.test', 'phone' => '012345678',
            'password' => 'StudentPassword1!', 'date_of_birth' => '2000-01-02',
            'academic_year' => '2026-2027', 'seat_number' => 'SEAT-001',
            'candidate_roles' => ['candidate'], 'is_active' => true,
        ])->fresh();
        $authId = $candidate->id;
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'));
        $candidate->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $form = CustomForm::query()->create(['name' => 'Retained application', 'slug' => 'retained-application', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $authId, 'review_status' => 'approved',
            'candidate_id' => 'CAND-0001', 'data' => ['registration_status' => 'approved', 'answer' => 'Retained answer'],
        ])->saveQuietly();
        $entry = $entry->fresh();
        $payment = Payment::query()->create([
            'users_id' => $authId, 'received_by' => $admin->id, 'form_id' => $form->id,
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'RETAIN-001',
            'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now(),
        ])->fresh();

        $this->withToken('student-retention-test-token')->postJson(self::ENDPOINT, $this->payload())->assertCreated();

        return [NewStudentAccount::query()->where('student_id', 'STU-RETAIN-0001')->sole(), $authId, $entry, $payment, $candidate];
    }

    private function student(int $authId): StudentLogin
    {
        $student = AccountUser::query()->findOrFail($authId);
        $this->assertInstanceOf(StudentLogin::class, $student);

        return $student;
    }

    private function storedState(): array
    {
        $state = [];
        foreach (['account_identities', 'new_student_accounts', 'users', 'custom_form_entries', 'payments'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        }
        foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $table => $column) {
            $state[$table] = DB::table($table)->orderBy('model_id')->orderBy($column)->get()->map(fn ($row): array => (array) $row)->all();
        }

        return $state;
    }

    private function payload(array $overrides = []): array
    {
        return [
            'student_id' => 'STU-RETAIN-0001', 'email' => 'retained@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA', ...$overrides,
        ];
    }
}
