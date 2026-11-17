<?php

namespace Tests\Feature;

use App\Models\NewStudentAccount;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalCandidateTypeDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_type_removes_only_that_classification_from_both_account_stores_and_legacy_mirrors(): void
    {
        $removed = UserType::query()->create(['key' => 'removed_candidate_type', 'label_en' => 'Removed type', 'label_kh' => 'Removed type', 'is_active' => true]);
        $retained = UserType::query()->create(['key' => 'retained_candidate_type', 'label_en' => 'Retained type', 'label_kh' => 'Retained type', 'is_active' => true]);
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'Candidate', 'username' => 'deletion-candidate',
            'email' => 'deletion-candidate@example.test', 'password' => 'candidate-password', 'date_of_birth' => '2000-01-01',
            'candidate_roles' => [' REMOVED_CANDIDATE_TYPE ', $retained->key, 'candidate', 'registration-marker'],
        ])->fresh();
        $student = new NewStudentAccount;
        $student->forceFill([
            'student_id' => 'DELETION-STUDENT', 'email' => 'deletion-student@example.test',
            'first_name_latin' => 'PROMOTED', 'last_name_latin' => 'STUDENT',
            'candidate_roles' => [$removed->key, $retained->key, 'candidate', 'registration-marker'],
        ])->save();
        $student->refresh();
        $legacy = SystemUser::query()->create([
            'name' => 'Legacy candidate', 'username' => 'deletion-legacy', 'email' => 'deletion-legacy@example.test',
            'password' => 'legacy-password', 'roles' => [$removed->key, $retained->key, 'candidate', 'registration-marker'],
            'is_active' => true,
        ])->fresh();
        $before = [$candidate->getRawOriginal(), $student->getRawOriginal(), $legacy->getRawOriginal()];

        $removed->delete();

        foreach ([$candidate, $student, $legacy] as $index => $account) {
            $account->refresh();
            $field = $account instanceof SystemUser ? 'roles' : 'candidate_roles';
            $this->assertSame([$retained->key, 'candidate', 'registration-marker'], $account->getAttribute($field));
            $after = $account->getRawOriginal();
            unset($before[$index][$field], $before[$index]['updated_at'], $after[$field], $after['updated_at']);
            $this->assertSame($before[$index], $after);
        }
        $this->assertDatabaseMissing('user_types', ['key' => $removed->key]);
        $this->assertDatabaseMissing('roles', ['name' => $removed->key]);
        $this->assertDatabaseHas('user_types', ['key' => $retained->key]);
        $this->assertDatabaseHas('roles', ['name' => $retained->key]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('new_student_accounts', 1);
        $this->assertDatabaseCount('system_users', 1);
    }
}
