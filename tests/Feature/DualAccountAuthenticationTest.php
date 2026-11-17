<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Student\Pages\MyProfile;
use App\Models\AccountIdentity;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\User;
use App\Support\PhonePasswordResetOtp;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DualAccountAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        Http::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_candidates_login_by_phone_and_email_from_users_only(): void
    {
        $candidate = $this->candidate();

        foreach ([$candidate->email, $candidate->phone] as $identifier) {
            Livewire::test(Login::class)->fillForm(['login' => $identifier, 'password' => 'OriginalPassword1!'])
                ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/dashboard');
            $this->assertSame($candidate->id, Auth::id());
            $this->assertSame(User::class, Auth::user()::class);
            Auth::logout();
        }

        $this->assertSame(1, DB::table('users')->where('id', $candidate->id)->count());
        $this->assertSame(0, NewStudentAccount::query()->count());
    }

    public function test_promoted_students_login_by_phone_email_or_student_id_without_a_users_row(): void
    {
        [$originalId, $account] = $this->promoted();

        foreach ([$account->email, $account->phone, strtolower($account->student_id)] as $identifier) {
            Livewire::test(Login::class)->fillForm(['login' => $identifier, 'password' => 'OriginalPassword1!'])
                ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/dashboard');
            $actor = Auth::user();
            $this->assertInstanceOf(StudentLogin::class, $actor);
            $this->assertSame($originalId, (int) Auth::id());
            $this->assertSame($originalId, $actor->id);
            $this->assertSame($originalId, $actor->getKey());
            $this->assertSame($account->id, $actor->getRawOriginal('id'));
            $this->assertTrue($actor->can('ViewAny:CustomFormEntry'));
            $this->assertSame(User::class, $actor->getMorphClass());
            Auth::logout();
        }

        $this->assertDatabaseMissing('users', ['id' => $originalId]);
        $this->assertSame(1, NewStudentAccount::query()->where('auth_id', $originalId)->count());
    }

    public function test_provider_sessions_and_projected_owner_models_use_logical_ids_not_student_table_ids(): void
    {
        [$originalId, $account] = $this->promoted();
        $other = $this->candidate(['username' => 'other-account', 'email' => 'other@example.test', 'phone' => '098765432']);
        $this->assertNotSame($originalId, $other->id);
        $provider = Auth::createUserProvider('users');

        $student = $provider->retrieveById($originalId);
        $this->assertInstanceOf(StudentLogin::class, $student);
        $this->assertSame($account->id, $student->getRawOriginal('id'));
        $this->assertSame(User::class, $provider->retrieveById($other->id)::class);

        $projected = AccountUser::query()->find($originalId, ['id', 'name']);
        $this->assertInstanceOf(StudentLogin::class, $projected);
        $this->assertSame($originalId, $projected->getKey());
        $this->assertSame($account->id, $projected->fresh()->getRawOriginal('id'));
        $projected->forceFill(['locale' => 'en'])->save();
        $this->assertSame('en', NewStudentAccount::query()->findOrFail($account->id)->locale);

        $provider->updateRememberToken($student, 'remember-promoted-student');
        $this->assertInstanceOf(StudentLogin::class, $provider->retrieveByToken($originalId, 'remember-promoted-student'));
        $this->assertSame('remember-promoted-student', $account->fresh()->remember_token);
        $this->assertDatabaseMissing('users', ['id' => $originalId]);
    }

    public function test_password_broker_and_phone_otp_continue_using_the_promoted_account(): void
    {
        [$originalId, $account] = $this->promoted();
        $student = AccountUser::query()->findOrFail($originalId);
        $token = Password::broker()->createToken($student);

        $status = Password::broker()->reset([
            'email' => $account->email, 'token' => $token,
            'password' => 'ChangedPassword1!', 'password_confirmation' => 'ChangedPassword1!',
        ], function (User $user, string $password): void {
            $this->assertInstanceOf(StudentLogin::class, $user);
            $user->forceFill(['password' => Hash::make($password)])->save();
        });

        $this->assertSame(Password::PASSWORD_RESET, $status);
        $this->assertTrue(Hash::check('ChangedPassword1!', $account->fresh()->password));
        $this->assertFalse(Hash::check('OriginalPassword1!', $account->fresh()->password));
        $otp = PhonePasswordResetOtp::issue($student);
        $this->assertTrue(PhonePasswordResetOtp::consume($student->fresh(), $otp));
        $this->assertFalse(PhonePasswordResetOtp::consume($student->fresh(), $otp));
        $this->assertDatabaseMissing('users', ['id' => $originalId]);
    }

    public function test_wrong_password_and_disabled_promoted_account_cannot_login(): void
    {
        [, $account] = $this->promoted();
        Livewire::test(Login::class)->fillForm(['login' => $account->student_id, 'password' => 'WrongPassword1!'])
            ->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();

        $account->update(['is_active' => false]);
        Livewire::test(Login::class)->fillForm(['login' => $account->email, 'password' => 'OriginalPassword1!'])
            ->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();
    }

    public function test_promoted_profile_updates_the_student_store_and_rejects_another_accounts_email(): void
    {
        [$originalId, $account] = $this->promoted();
        $other = $this->candidate(['username' => 'other-account', 'email' => 'other@example.test', 'phone' => '098765432']);
        $student = AccountUser::query()->findOrFail($originalId);

        Livewire::actingAs($student)->test(MyProfile::class)
            ->set('data.first_name_en', 'NEW')
            ->set('data.last_name_en', 'NAME')
            ->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseHas('new_student_accounts', [
            'id' => $account->id, 'auth_id' => $originalId,
            'name_latin' => 'NEW NAME', 'first_name_latin' => 'NEW', 'last_name_latin' => 'NAME',
        ]);
        $this->assertDatabaseMissing('users', ['id' => $originalId]);

        Livewire::actingAs($student->fresh())->test(MyProfile::class)
            ->set('data.email', $other->email)
            ->call('save')->assertHasFormErrors(['email' => 'unique']);
        $this->assertSame($account->email, $account->fresh()->email);
    }

    public function test_identifiers_matching_different_accounts_are_rejected(): void
    {
        [, $account] = $this->promoted();
        $this->candidate(['username' => $account->student_id, 'email' => 'other@example.test', 'phone' => '098765432']);

        Livewire::test(Login::class)->fillForm(['login' => $account->student_id, 'password' => 'OriginalPassword1!'])
            ->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();
    }

    public function test_failed_credential_insert_does_not_leave_an_orphan_identity(): void
    {
        $this->candidate(['seat_number' => 'RESERVED-SEAT']);
        $before = AccountIdentity::query()->count();

        try {
            $this->candidate(['username' => 'other-account', 'email' => 'other@example.test', 'phone' => '098765432', 'seat_number' => 'RESERVED-SEAT']);
            $this->fail('The duplicate seat number must reject the credential insert.');
        } catch (QueryException) {
            $this->assertSame($before, AccountIdentity::query()->count());
            $this->assertSame(1, User::query()->count());
        }
    }

    public function test_password_rotation_updates_both_auth_stores_without_recreating_candidates(): void
    {
        [$originalId, $account] = $this->promoted();
        $candidate = $this->candidate(['username' => 'other-account', 'email' => 'other@example.test', 'phone' => '098765432']);

        $this->artisan('auth:rotate-default-password', [
            'from' => 'OriginalPassword1!', 'to' => 'RotatedPassword1!', '--dry-run' => true,
        ])->assertSuccessful();
        $this->assertTrue(Hash::check('OriginalPassword1!', $account->fresh()->password));

        $this->artisan('auth:rotate-default-password', [
            'from' => 'OriginalPassword1!', 'to' => 'RotatedPassword1!',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('RotatedPassword1!', $account->fresh()->password));
        $this->assertTrue(Hash::check('RotatedPassword1!', $candidate->fresh()->password));
        $this->assertDatabaseMissing('users', ['id' => $originalId]);
    }

    public function test_failed_student_account_insert_does_not_leave_an_orphan_identity(): void
    {
        NewStudentAccount::query()->create([
            'student_id' => 'RESERVED-STUDENT-ID', 'first_name_latin' => 'EXISTING', 'last_name_latin' => 'STUDENT',
        ]);
        $before = AccountIdentity::query()->count();

        try {
            NewStudentAccount::query()->create([
                'student_id' => 'RESERVED-STUDENT-ID', 'first_name_latin' => 'DUPLICATE', 'last_name_latin' => 'STUDENT',
            ]);
            $this->fail('A duplicate student ID must reject the account insert.');
        } catch (QueryException) {
            $this->assertSame($before, AccountIdentity::query()->count());
            $this->assertSame(1, NewStudentAccount::query()->count());
        }
    }

    private function candidate(array $overrides = []): User
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'username' => 'candidate-login', 'email' => 'candidate-login@example.test', 'phone' => '012345678',
            'date_of_birth' => '2000-01-01', 'password' => 'OriginalPassword1!', 'is_active' => true,
            'candidate_roles' => ['candidate'], ...$overrides,
        ]);
        $candidate->assignRole(Role::findOrCreate('candidate', 'web'));
        $candidate->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));

        return $candidate->fresh();
    }

    private function promoted(): array
    {
        // Make the storage row ID differ from the logical account ID.
        User::query()->create([
            'registration_type' => 'admin', 'name' => 'Unrelated operator', 'username' => 'unrelated-operator',
            'date_of_birth' => '1990-01-01', 'password' => 'UnrelatedPassword1!', 'is_active' => true,
        ]);
        NewStudentAccount::query()->create([
            'student_id' => 'MANUAL-ONLY', 'first_name_latin' => 'MANUAL', 'last_name_latin' => 'RECORD',
        ]);
        $candidate = $this->candidate();
        $account = new NewStudentAccount;
        $profile = $candidate->getRawOriginal();
        $profile['candidate_roles'] = $candidate->candidate_roles;
        unset($profile['id']);
        $account->forceFill([
            ...$profile, 'student_id' => 'STU-260001', 'auth_id' => $candidate->id,
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
        ])->saveQuietly();
        AccountIdentity::query()->whereKey($candidate->id)->update(['storage' => 'student']);
        DB::table('users')->where('id', $candidate->id)->delete();

        return [$candidate->id, $account->fresh()];
    }
}
