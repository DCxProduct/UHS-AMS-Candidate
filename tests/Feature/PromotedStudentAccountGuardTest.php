<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateLists\Pages\CreateCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\EditCandidateList;
use App\Models\AccountUser;
use App\Models\CandidateList;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\PromotedStudentAccountGuard;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PromotedStudentAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $login;

    private NewStudentAccount $promoted;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        UserTypeOptions::options();
        $operator = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Account operator', 'username' => 'account-operator',
            'password' => 'operator-password', 'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $operator->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($operator);
        $authId = DB::table('account_identities')->insertGetId(['storage' => 'student', 'created_at' => now(), 'updated_at' => now()]);
        $this->promoted = new NewStudentAccount;
        $this->promoted->forceFill([
            'auth_id' => $authId, 'source_candidate_id' => 99999, 'username' => 'promoted-login',
            'name' => 'PROMOTED ACCOUNT', 'name_latin' => 'PROMOTED ACCOUNT',
            'password' => bcrypt('preserved-password'), 'date_of_birth' => '2000-01-01',
            'candidate_roles' => ['candidate'], 'student_id' => 'STU260001',
            'email' => 'promoted@example.test', 'phone' => '013111111',
            'first_name_latin' => 'PROMOTED', 'last_name_latin' => 'ACCOUNT',
        ])->save();
        $this->login = AccountUser::query()->findOrFail($authId)->fresh();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_candidate_create_cannot_reuse_canonical_promoted_login_contacts(): void
    {
        $before = $this->login->getRawOriginal();
        foreach ([
            ['email', 'promoted@example.test'], ['phone', '013111111'],
        ] as [$field, $value]) {
            Livewire::test(CreateCandidateList::class)
                ->fillForm($this->formData([$field => $value]))
                ->call('create')->assertHasFormErrors([$field]);
        }

        $this->assertDatabaseCount('system_users', 0);
        $this->assertSame($before, $this->login->fresh()->getRawOriginal());
    }

    public function test_candidate_edit_cannot_claim_promoted_contact_or_overwrite_its_login(): void
    {
        $data = $this->recordData();
        unset($data['roles']);
        $candidate = CandidateList::query()->create([...$data, 'candidate_roles' => ['candidate']]);
        $sourceBefore = $candidate->fresh()->getRawOriginal();
        $loginBefore = $this->login->getRawOriginal();
        foreach (['email' => 'promoted@example.test', 'phone' => '013111111'] as $field => $value) {
            Livewire::test(EditCandidateList::class, ['record' => $candidate->getRouteKey()])
                ->fillForm([$field => $value])
                ->call('save')->assertHasFormErrors([$field]);
        }

        $this->assertSame($sourceBefore, $candidate->fresh()->getRawOriginal());
        $this->assertSame($loginBefore, $this->login->fresh()->getRawOriginal());
        $this->assertDatabaseCount('system_users', 0);
    }

    public function test_direct_system_user_writes_reject_normalized_contacts_and_promoted_username(): void
    {
        $before = $this->login->getRawOriginal();
        foreach ([
            ['email', ' PROMOTED@EXAMPLE.TEST '],
            ['phone', '+0 (13) 111-111'],
            ['username', 'PROMOTED-LOGIN'],
        ] as [$field, $value]) {
            try {
                SystemUser::query()->create($this->recordData([$field => $value]));
                $this->fail('Promoted identifiers must not create another system-user mirror.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }

        $this->assertDatabaseCount('system_users', 0);
        $this->assertSame($before, $this->login->fresh()->getRawOriginal());
    }

    public function test_editing_an_existing_system_user_is_also_guarded_before_the_database_write(): void
    {
        $candidate = SystemUser::query()->create($this->recordData());
        $before = $candidate->fresh()->getRawOriginal();
        try {
            $candidate->forceFill(['email' => 'promoted@example.test'])->save();
            $this->fail('An existing row must not claim a promoted identity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertSame($before, $candidate->fresh()->getRawOriginal());
    }

    public function test_a_stale_source_record_cannot_sync_credentials_after_its_promotion(): void
    {
        $candidate = SystemUser::query()->create($this->recordData());
        $candidate->syncLoginUser();
        $login = $candidate->findLinkedLoginUser();
        $promoted = new NewStudentAccount;
        $promoted->forceFill([
            'auth_id' => $login->id, 'source_candidate_id' => $candidate->id,
            'student_id' => 'STU260002', 'email' => $candidate->email, 'phone' => $candidate->phone,
            'username' => $login->username, 'name' => $login->name, 'name_latin' => $login->name_latin,
            'password' => $login->getRawOriginal('password'), 'date_of_birth' => $login->date_of_birth,
            'first_name_latin' => 'OTHER', 'last_name_latin' => 'CANDIDATE',
        ])->save();
        DB::table('system_users')->where('id', $candidate->id)->delete();
        DB::table('users')->where('id', $login->id)->delete();
        DB::table('account_identities')->where('id', $login->id)->update(['storage' => 'student']);
        $before = $promoted->fresh()->getRawOriginal();

        try {
            $candidate->forceFill(['password' => 'replacement-password'])->syncLoginUser();
            $this->fail('A stale candidate mirror must not overwrite a promoted login.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame($before, $promoted->fresh()->getRawOriginal());
        $this->assertDatabaseMissing('users', ['id' => $login->id]);
        $this->assertDatabaseCount('system_users', 0);
    }

    public function test_manual_student_accounts_also_reserve_contacts_against_candidate_duplicates(): void
    {
        NewStudentAccount::query()->create([
            'student_id' => 'MANUAL-ACCOUNT', 'email' => 'manual@example.test', 'phone' => '010222222',
            'first_name_latin' => 'MANUAL', 'last_name_latin' => 'ACCOUNT',
        ]);
        $this->assertFalse(PromotedStudentAccountGuard::canUseContact('email', 'manual@example.test'));
        $this->assertFalse(PromotedStudentAccountGuard::canUseContact('phone', '010222222'));

        Livewire::test(CreateCandidateList::class)
            ->fillForm($this->formData(['email' => 'manual@example.test', 'phone' => '010222222']))
            ->call('create')->assertHasFormErrors(['email', 'phone']);
        $this->assertDatabaseMissing('users', ['registration_type' => 'student', 'email' => 'manual@example.test']);
        $this->assertDatabaseCount('system_users', 0);
        $this->assertDatabaseHas('new_student_accounts', ['student_id' => 'MANUAL-ACCOUNT']);
    }

    public function test_profile_contact_changes_are_reserved_from_the_canonical_student_record(): void
    {
        $this->login->update(['email' => 'updated-profile@example.test', 'phone' => '098123456']);

        $this->assertFalse(PromotedStudentAccountGuard::canUseContact('email', ' UPDATED-PROFILE@EXAMPLE.TEST '));
        $this->assertFalse(PromotedStudentAccountGuard::canUseContact('phone', '+0 (98) 123-456'));
        Livewire::test(CreateCandidateList::class)
            ->fillForm($this->formData(['email' => 'updated-profile@example.test', 'phone' => '098123456']))
            ->call('create')->assertHasFormErrors(['email', 'phone']);
        $this->assertDatabaseMissing('users', ['id' => $this->login->id]);
        $this->assertSame('updated-profile@example.test', $this->promoted->fresh()->email);
    }

    private function formData(array $overrides = []): array
    {
        return [
            'first_name_en' => 'OTHER', 'last_name_en' => 'CANDIDATE', 'email' => 'other@example.test',
            'phone' => '010000001', 'candidate_type' => array_key_first(UserTypeOptions::options()),
            'password' => 'new-password', 'password_confirmation' => 'new-password', 'is_active' => true,
            ...$overrides,
        ];
    }

    private function recordData(array $overrides = []): array
    {
        return [
            'name' => 'OTHER CANDIDATE', 'username' => 'other-candidate', 'email' => 'other@example.test',
            'phone' => '010000001', 'roles' => [array_key_first(UserTypeOptions::options())],
            'password' => 'other-password', 'is_active' => true, ...$overrides,
        ];
    }
}
