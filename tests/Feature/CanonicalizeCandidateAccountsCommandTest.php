<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CanonicalizeCandidateAccountsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('candidate', 'web');
        Role::findOrCreate('admin', 'web');
        UserType::query()->create([
            'key' => 'legacy_candidate_category', 'label_en' => 'Legacy category',
            'label_kh' => 'Legacy category', 'is_active' => false,
        ]);
    }

    public function test_existing_login_credentials_profile_permissions_and_history_remain_while_the_mirror_is_removed(): void
    {
        $user = $this->user([
            'name' => 'Latest profile', 'name_latin' => 'LATEST PROFILE', 'phone' => '012345678',
            'email' => 'latest@example.test', 'username' => 'latest-user', 'is_active' => false,
        ]);
        $user->assignRole('candidate');
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $source = $this->mirror([
            'email' => ' LATEST@example.test ', 'phone' => '012 345 678', 'username' => 'old-user',
            'password' => 'older-password', 'roles' => ['legacy_candidate_category'],
        ]);
        $before = $user->fresh()->getRawOriginal();
        $roles = DB::table('model_has_roles')->get()->toArray();
        $permissions = DB::table('model_has_permissions')->get()->toArray();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        $user->refresh();
        foreach (array_diff(array_keys($before), ['candidate_roles', 'source_candidate_id', 'updated_at']) as $field) {
            $this->assertSame($before[$field], $user->getRawOriginal($field), $field);
        }
        $this->assertTrue(Hash::check('current-password', $user->password));
        $this->assertSame(['legacy_candidate_category'], $user->candidate_roles);
        $this->assertSame($source->id, $user->source_candidate_id);
        $this->assertEquals($roles, DB::table('model_has_roles')->get()->toArray());
        $this->assertEquals($permissions, DB::table('model_has_permissions')->get()->toArray());
        $this->assertDatabaseMissing('system_users', ['id' => $source->id]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('new_student_accounts', 0);
    }

    public function test_an_orphan_candidate_mirror_becomes_a_single_canonical_login_with_the_same_password(): void
    {
        $source = $this->mirror(['roles' => ['candidate'], 'username' => null]);
        $password = $source->password;

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('student_012345678', $user->username);
        $this->assertSame($source->email, $user->email);
        $this->assertSame($source->phone, $user->phone);
        $this->assertSame($password, $user->password);
        $this->assertTrue($user->hasRole('candidate'));
        $this->assertSame(['candidate'], $user->candidate_roles);
        $this->assertSame($source->id, $user->source_candidate_id);
        $this->assertDatabaseMissing('system_users', ['id' => $source->id]);
        $this->assertDatabaseCount('account_identities', 1);
    }

    public function test_staff_and_mixed_role_mirrors_are_left_untouched(): void
    {
        $staff = $this->mirror(['username' => 'staff', 'email' => 'staff@example.test', 'phone' => '011111111', 'roles' => ['admin']]);
        $mixed = $this->mirror(['username' => 'mixed', 'email' => 'mixed@example.test', 'phone' => '022222222', 'roles' => ['candidate', 'admin']]);
        $before = DB::table('system_users')->orderBy('id')->get()->toArray();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        $this->assertEquals($before, DB::table('system_users')->orderBy('id')->get()->toArray());
        $this->assertModelExists($staff);
        $this->assertModelExists($mixed);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_orphan_with_an_inactive_configured_candidate_type_remains_a_candidate_login(): void
    {
        $source = $this->mirror(['roles' => ['legacy_candidate_category']]);

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('student', $user->registration_type);
        $this->assertSame(['legacy_candidate_category'], $user->candidate_roles);
        $this->assertDatabaseMissing('system_users', ['id' => $source->id]);
    }

    public function test_a_candidate_mirror_cannot_claim_a_staff_login(): void
    {
        $user = $this->user(['registration_type' => 'admin']);
        $user->assignRole('admin');
        $source = $this->mirror();
        $before = $user->fresh()->getRawOriginal();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertFailed();

        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertModelExists($source);
    }

    public function test_protected_admin_and_configured_staff_sources_remain_untouched_even_when_listed_as_candidate_types(): void
    {
        foreach (['admin', 'custom_staff'] as $key) {
            UserType::query()->create(['key' => $key, 'label_en' => $key, 'label_kh' => $key]);
        }
        Role::findOrCreate('custom_staff', 'web')->forceFill(['role_type_key' => 'staff'])->save();
        $protected = $this->mirror(['username' => 'admin', 'roles' => ['candidate']]);
        $admin = $this->mirror(['username' => 'configured-admin', 'email' => 'admin@example.test', 'phone' => '011111111', 'roles' => ['admin']]);
        $custom = $this->mirror(['username' => 'configured-staff', 'email' => 'staff@example.test', 'phone' => '022222222', 'roles' => ['custom_staff']]);

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        foreach ([$protected, $admin, $custom] as $source) {
            $this->assertModelExists($source);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_staff_classification_on_a_candidate_login_prevents_mirror_canonicalization(): void
    {
        $user = $this->user(['candidate_roles' => ['admin']]);
        $source = $this->mirror();
        $before = $user->fresh()->getRawOriginal();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertFailed();

        $this->assertSame($before, $user->fresh()->getRawOriginal());
        $this->assertModelExists($source);
    }

    public function test_ambiguity_rolls_back_the_entire_batch_without_deleting_any_mirror(): void
    {
        $safe = $this->mirror(['username' => 'safe-orphan', 'email' => 'safe@example.test', 'phone' => '033333333']);
        $first = $this->user();
        $second = $this->user(['username' => 'other-user', 'email' => 'other@example.test', 'phone' => '099999999']);
        $source = $this->mirror(['username' => 'ambiguous-user', 'email' => $first->email, 'phone' => $second->phone]);
        $beforeUsers = DB::table('users')->orderBy('id')->get()->toArray();
        $beforeIdentities = DB::table('account_identities')->orderBy('id')->get()->toArray();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertFailed();

        $this->assertModelExists($safe);
        $this->assertModelExists($source);
        $this->assertEquals($beforeUsers, DB::table('users')->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeIdentities, DB::table('account_identities')->orderBy('id')->get()->toArray());
    }

    public function test_preview_rolls_back_new_logins_mirror_deletion_and_metadata_backfills(): void
    {
        $source = $this->mirror();
        $user = $this->user(['username' => 'unmirrored-user', 'email' => 'unmirrored@example.test', 'phone' => '099999999']);
        $user->assignRole(Role::findOrCreate('legacy_candidate_category', 'web'));
        $beforeUsers = DB::table('users')->orderBy('id')->get()->toArray();
        $beforeIdentities = DB::table('account_identities')->orderBy('id')->get()->toArray();

        $this->artisan('accounts:canonicalize-candidates')->expectsOutputToContain('No changes saved')->assertSuccessful();

        $this->assertModelExists($source);
        $this->assertEquals($beforeUsers, DB::table('users')->orderBy('id')->get()->toArray());
        $this->assertEquals($beforeIdentities, DB::table('account_identities')->orderBy('id')->get()->toArray());
        $this->assertNull($user->fresh()->candidate_roles);
    }

    public function test_unmirrored_candidates_backfill_from_spatie_categories_and_repeated_apply_is_idempotent(): void
    {
        $user = $this->user();
        $user->assignRole(['candidate', Role::findOrCreate('legacy_candidate_category', 'web')]);

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();
        $this->assertSame(['legacy_candidate_category'], $user->fresh()->candidate_roles);
        $before = $user->fresh()->getRawOriginal();
        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();
        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    public function test_backfill_does_not_change_a_protected_admin_login_misclassified_as_a_candidate(): void
    {
        $user = $this->user(['username' => 'admin']);
        $before = $user->fresh()->getRawOriginal();

        $this->artisan('accounts:canonicalize-candidates', ['--apply' => true])->assertSuccessful();

        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    private function user(array $overrides = []): User
    {
        return User::query()->create([
            'registration_type' => 'student', 'name' => 'Current candidate', 'name_latin' => 'CURRENT CANDIDATE',
            'username' => 'current-user', 'email' => 'candidate@example.test', 'phone' => '012345678',
            'date_of_birth' => '2000-01-01', 'password' => 'current-password', 'is_active' => true,
            ...$overrides,
        ]);
    }

    private function mirror(array $overrides = []): SystemUser
    {
        return SystemUser::query()->create([
            'name' => 'Legacy candidate', 'username' => 'legacy-user', 'email' => 'candidate@example.test',
            'phone' => '012345678', 'password' => 'legacy-password', 'roles' => ['candidate'], 'is_active' => true,
            ...$overrides,
        ]);
    }
}
