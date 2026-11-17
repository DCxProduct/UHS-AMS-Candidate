<?php

namespace Tests\Feature;

use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\User;
use App\Support\CandidateApplicationContext;
use App\Support\CandidateApplicationLevels;
use App\Support\CandidateTypeResolver;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotedStudentAccountCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const BACHELOR = 'national_entrance_exam_application_bachelor';

    private const ASSOCIATE = 'national_entrance_exam_application_associate';

    public function test_promoted_classification_follows_the_explicit_user_link_when_contact_details_change(): void
    {
        [$user, $account] = $this->account();
        $user->forceFill(['email' => 'changed-login@example.test', 'phone' => '012999999'])->save();

        $this->assertInstanceOf(StudentLogin::class, $user);
        $this->assertNotEquals($account->id, $user->getKey());
        $this->assertSame($account->id, $user->linkedNewStudentAccount()?->id);
        $this->assertSame('changed-login@example.test', $account->fresh()->email);
        $this->assertSame(self::BACHELOR, CandidateApplicationLevels::currentType($user));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::resolve($user));
        $this->assertTrue($user->hasEffectiveRole(self::BACHELOR));
        $this->assertSame(['candidate'], $user->getRoleNames()->all());
        $this->assertNull($user->linkedSystemUser());
        foreach (['user_id', 'source_candidate_id', 'candidate_roles'] as $field) {
            $this->assertArrayNotHasKey($field, $account->toArray());
            $this->assertFalse($account->isFillable($field));
        }
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseCount('system_users', 0);
    }

    public function test_promoted_account_can_switch_category_without_recreating_candidate_or_changing_login_credentials(): void
    {
        [$user, $account] = $this->account();
        $login = $user->getRawOriginal();
        $roles = $user->getRoleNames()->all();
        $permissions = $user->getDirectPermissions()->pluck('name')->all();

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        $this->assertSame(self::ASSOCIATE, CandidateApplicationLevels::currentType($user));
        $this->assertSame([self::ASSOCIATE, 'legacy-registration-marker'], $account->fresh()->candidate_roles);
        $this->assertTrue($user->hasEffectiveRole(self::ASSOCIATE));
        $this->assertFalse($user->hasEffectiveRole(self::BACHELOR));
        $after = $user->fresh()->getRawOriginal();
        unset($login['candidate_roles'], $login['updated_at'], $after['candidate_roles'], $after['updated_at']);
        $this->assertSame($login, $after);
        $this->assertSame($roles, $user->fresh()->getRoleNames()->all());
        $this->assertSame($permissions, $user->fresh()->getDirectPermissions()->pluck('name')->all());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_users', 0);
    }

    public function test_switching_freezes_historical_system_owner_using_the_original_candidate_id(): void
    {
        Schema::table('custom_form_entries', fn (Blueprint $table) => $table->unsignedBigInteger('system_user_id')->nullable());
        [$user, $account] = $this->account();
        $form = CustomForm::query()->create(['name' => 'Historical generic application', 'slug' => 'historical-generic', 'is_active' => true, 'allowed_roles' => ['candidate']]);
        $owned = $this->historicalEntry($form, ['created_by' => $user->id]);
        $historical = $this->historicalEntry($form, ['system_user_id' => $account->source_candidate_id]);
        $unrelated = $this->historicalEntry($form, ['system_user_id' => $account->id]);
        $before = [$owned->getRawOriginal(), $historical->getRawOriginal(), $unrelated->getRawOriginal()];

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        foreach ([$owned, $historical] as $index => $entry) {
            $this->assertSame(self::BACHELOR, $entry->fresh()->data[CandidateApplicationContext::DATA_KEY]);
            $after = $entry->fresh()->getRawOriginal();
            unset($before[$index]['data'], $after['data']);
            $this->assertSame($before[$index], $after);
        }
        $this->assertSame($before[2], $unrelated->fresh()->getRawOriginal());
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('system_users', 0);
    }

    public function test_canonical_candidate_switches_category_in_its_user_record_without_a_mirror(): void
    {
        CandidateApplicationLevels::options();
        $user = User::query()->create([
            'registration_type' => 'student', 'name' => 'Canonical candidate', 'username' => 'canonical-candidate',
            'password' => 'preserved-password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
            'candidate_roles' => [self::BACHELOR, 'legacy-registration-marker'],
        ]);
        $user->assignRole(Role::findOrCreate('candidate', 'web'));
        $this->actingAs($user);
        $password = $user->getRawOriginal('password');

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        $this->assertSame(self::ASSOCIATE, CandidateApplicationLevels::currentType($user));
        $this->assertSame([self::ASSOCIATE, 'legacy-registration-marker'], $user->fresh()->candidate_roles);
        $this->assertSame($password, $user->fresh()->getRawOriginal('password'));
        $this->assertSame(['candidate'], $user->fresh()->getRoleNames()->all());
        $this->assertDatabaseCount('new_student_accounts', 0);
        $this->assertDatabaseCount('system_users', 0);
    }

    private function account(): array
    {
        CandidateApplicationLevels::options();
        DB::table('account_identities')->insert(['id' => 73, 'storage' => 'student', 'created_at' => now(), 'updated_at' => now()]);
        $account = new NewStudentAccount;
        $account->forceFill([
            'auth_id' => 73, 'source_candidate_id' => 99999,
            'candidate_roles' => [self::BACHELOR, 'legacy-registration-marker'],
            'student_id' => 'STU260001', 'username' => 'promoted-candidate', 'name' => 'PROMOTED CANDIDATE',
            'name_latin' => 'PROMOTED CANDIDATE', 'email' => 'promoted@example.test', 'phone' => '012345678',
            'password' => Hash::make('original-password'), 'date_of_birth' => '2000-01-01',
            'first_name_latin' => 'PROMOTED', 'last_name_latin' => 'CANDIDATE', 'is_active' => true,
        ])->save();
        $user = AccountUser::query()->findOrFail(73);
        $user->assignRole(Role::findOrCreate('candidate', 'web'));
        $this->actingAs($user);

        return [$user->fresh(), $account->fresh()];
    }

    private function historicalEntry(CustomForm $form, array $owner): CustomFormEntry
    {
        $id = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id, ...$owner, 'review_status' => 'failed',
            'data' => json_encode(['answer' => 'Historical answer']),
            'created_at' => now()->subMonth(), 'updated_at' => now()->subWeek(),
        ]);

        return CustomFormEntry::query()->findOrFail($id);
    }
}
