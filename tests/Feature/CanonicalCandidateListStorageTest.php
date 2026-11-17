<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Filament\Admin\Resources\CandidateLists\Pages\CreateCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\EditCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\ListCandidateLists;
use App\Filament\Pages\Auth\Register;
use App\Models\CandidateList;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CanonicalCandidateListStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Account admin',
            'username' => 'account-admin', 'email' => 'account-admin@example.test',
            'date_of_birth' => '1990-01-01', 'password' => 'admin-password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_candidate_crud_writes_only_the_canonical_user_and_keeps_login_roles_on_that_identity(): void
    {
        Role::findOrCreate('candidate', 'web');
        $beforeMirrors = SystemUser::query()->count();
        Livewire::test(CreateCandidateList::class)
            ->fillForm($this->formData())
            ->call('create')->assertHasNoFormErrors();

        $candidate = CandidateList::query()->where('phone', '012345678')->sole();
        $login = User::query()->where('phone', '012345678')->sole();
        $this->assertSame($candidate->id, $login->id);
        $this->assertSame('student', $login->registration_type);
        $this->assertSame('SOK DARA', $login->name_latin);
        $this->assertTrue(Hash::check('candidate-password', $login->password));
        $this->assertTrue($login->hasRole('candidate'));
        $this->assertSame($beforeMirrors, SystemUser::query()->count());

        Livewire::test(EditCandidateList::class, ['record' => $candidate->getRouteKey()])
            ->fillForm(['last_name_en' => 'VIREAK', 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('SOK VIREAK', $login->fresh()->name_latin);
        $this->assertFalse($login->fresh()->is_active);
        $this->assertSame($beforeMirrors, SystemUser::query()->count());

        Livewire::test(ListCandidateLists::class)->callTableAction('activate_account', $candidate);
        $this->assertTrue($login->fresh()->is_active);
        Livewire::test(ListCandidateLists::class)->callTableAction('deactivate_account', $candidate);
        $this->assertFalse($login->fresh()->is_active);
        $this->assertSame($beforeMirrors, SystemUser::query()->count());

        Livewire::test(ListCandidateLists::class)->callTableAction('delete', $candidate);
        $this->assertDatabaseMissing('users', ['id' => $candidate->id]);
        $this->assertSame($beforeMirrors, SystemUser::query()->count());
    }

    public function test_self_registration_creates_one_user_without_a_system_user_mirror(): void
    {
        Role::findOrCreate('candidate', 'web');
        $beforeUsers = User::query()->count();
        $beforeMirrors = SystemUser::query()->count();
        $register = new Register;
        $candidateType = array_key_first(UserTypeOptions::options());
        $user = (new ReflectionMethod(Register::class, 'handleRegistration'))->invoke($register, [
            'student_role' => $candidateType, 'first_name_en' => 'sok', 'last_name_en' => 'dara',
            'email' => 'registration@example.test', 'phone' => '098765432', 'password' => 'candidate-password',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($beforeUsers + 1, User::query()->count());
        $this->assertSame($beforeMirrors, SystemUser::query()->count());
        $this->assertSame([$candidateType], $user->candidate_roles);
        $this->assertSame('SOK DARA', $user->name_latin);
        $this->assertTrue(Hash::check('candidate-password', $user->password));
    }

    public function test_the_list_includes_only_candidate_users_and_excludes_staff_and_student_storage(): void
    {
        $candidate = CandidateList::query()->create([
            'name' => 'Candidate only', 'name_latin' => 'CANDIDATE ONLY',
            'username' => 'candidate-only', 'email' => 'candidate-only@example.test',
            'phone' => '012345678', 'password' => 'candidate-password', 'candidate_roles' => ['candidate'],
        ]);
        $student = $this->studentAccount();
        Livewire::test(ListCandidateLists::class)
            ->assertCanSeeTableRecords([$candidate])
            ->assertCountTableRecords(1);
        $this->assertSame('users', (new CandidateList)->getTable());
        $this->assertFalse(CandidateListResource::getEloquentQuery()->whereKey(auth()->id())->exists());
        $this->assertFalse(CandidateListResource::getEloquentQuery()->whereKey($student->auth_id)->exists());
    }

    public function test_promoted_email_and_phone_cannot_be_registered_as_another_candidate_account(): void
    {
        $student = $this->studentAccount();
        $before = $student->fresh()->getRawOriginal();
        $users = User::query()->count();

        Livewire::test(CreateCandidateList::class)
            ->fillForm($this->formData(['email' => 'PROMOTED@example.test', 'phone' => '099999999']))
            ->call('create')->assertHasFormErrors(['email' => 'unique', 'phone' => 'unique']);

        $this->assertSame($users, User::query()->count());
        $this->assertSame($before, $student->fresh()->getRawOriginal());
        $this->assertSame(0, SystemUser::query()->count());
    }

    private function studentAccount(): NewStudentAccount
    {
        $id = DB::table('account_identities')->insertGetId([
            'storage' => 'student', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = new NewStudentAccount;
        $student->forceFill([
            'auth_id' => $id, 'student_id' => 'PROMOTED-0001', 'username' => 'promoted-student',
            'name' => 'PROMOTED STUDENT', 'name_latin' => 'PROMOTED STUDENT',
            'first_name_latin' => 'PROMOTED', 'last_name_latin' => 'STUDENT',
            'email' => 'promoted@example.test', 'phone' => '099999999',
            'password' => Hash::make('student-password'), 'date_of_birth' => '2000-01-01',
            'is_active' => true, 'candidate_roles' => ['candidate'],
        ])->save();

        return $student;
    }

    private function formData(array $overrides = []): array
    {
        return [
            'first_name_en' => 'SOK', 'last_name_en' => 'DARA',
            'email' => 'candidate@example.test', 'phone' => '012345678',
            'candidate_type' => array_key_first(UserTypeOptions::options()),
            'password' => 'candidate-password', 'password_confirmation' => 'candidate-password',
            'is_active' => true, ...$overrides,
        ];
    }
}
