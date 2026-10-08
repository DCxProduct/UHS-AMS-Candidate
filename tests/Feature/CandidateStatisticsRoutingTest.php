<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Models\CandidateExitStatistic;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\CandidateTypeResolver;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\StatisticsMenuOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateStatisticsRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_menu_defaults_to_entrance_exam_statistics(): void
    {
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Entrance Form']),
            'slug' => 'entrance-form',
            'is_active' => true,
        ]);

        $this->assertSame(
            StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            $form->statistics_menu,
        );
    }

    public function test_passed_exit_entry_is_stored_once_and_pending_reversion_is_synced(): void
    {
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Exit Form']),
            'slug' => 'exit-form',
            'is_active' => true,
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
        ]);

        $user = User::query()->create([
            'registration_type' => 'student',
            'academic_year' => '2026-2027',
            'name' => 'ដារា សុខ',
            'username' => 'exit_statistics_candidate',
            'email' => 'exit-statistics-candidate@example.test',
            'seat_number' => 'EXIT-001',
            'name_latin' => 'Dara Sok',
            'date_of_birth' => '2000-01-02',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'created_by' => $user->id,
            'review_status' => 'passed',
            'reviewed_at' => now(),
            'data' => json_encode([
                'first_name_kh' => 'ដារា',
                'last_name_kh' => 'សុខ',
                'first_name_en' => 'Dara',
                'last_name_en' => 'Sok',
                'gender' => 'male',
                'date_of_birth' => '2000-01-02',
                'academic_year' => '2026-2027',
                'major' => 'Medicine',
                'candidate_status' => 'passed',
                'candidate_reviewed_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $entry = CustomFormEntry::query()->findOrFail($entryId);

        CandidateStatisticsSynchronizer::syncPassedEntry($entry);
        CandidateStatisticsSynchronizer::syncPassedEntry($entry);

        $this->assertSame(1, CandidateExitStatistic::query()->where('custom_form_entry_id', $entryId)->count());
        $this->assertDatabaseHas('candidate_exit_statistics', [
            'custom_form_entry_id' => $entryId,
            'seat_number' => 'EXIT-001',
            'candidate_status' => 'passed',
        ]);

        CandidateStatisticsSynchronizer::syncPendingEntry($entry);

        $this->assertDatabaseHas('candidate_exit_statistics', [
            'custom_form_entry_id' => $entryId,
            'candidate_status' => 'pending',
            'candidate_reviewed_at' => null,
        ]);
    }

    public function test_statistics_use_the_candidate_type_selected_during_registration(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'registered_exit_candidate',
            'username' => 'registered_exit_candidate',
            'email' => 'registered-exit-candidate@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        SystemUser::query()->create([
            'name' => 'registered_exit_candidate',
            'username' => 'registered_exit_candidate',
            'email' => 'registered-exit-candidate@example.test',
            'roles' => ['national_exit_exam_application_bachelor'],
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->assertSame(
            'national_exit_exam_application_bachelor',
            CandidateTypeResolver::resolve($user),
        );

        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Exit Form']),
            'slug' => 'registered-exit-form',
            'is_active' => true,
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
        ]);

        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'created_by' => $user->id,
            'review_status' => 'passed',
            'data' => json_encode(['candidate_status' => 'passed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CandidateStatisticsSynchronizer::syncPassedEntry(CustomFormEntry::query()->findOrFail($entryId));

        $this->assertDatabaseHas('candidate_exit_statistics', [
            'custom_form_entry_id' => $entryId,
            'user_type' => 'national_exit_exam_application_bachelor',
        ]);
    }

    public function test_passed_entrance_entry_remains_visible_in_entrance_statistics(): void
    {
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Entrance Form']),
            'slug' => 'entrance-pass-form',
            'is_active' => true,
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'requires_payment' => false,
        ]);

        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'review_status' => 'passed',
            'data' => json_encode(['candidate_status' => 'passed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(
            CandidateEntranceStatisticResource::getEloquentQuery()
                ->whereKey($entryId)
                ->exists(),
        );
    }

    public function test_exit_assignment_moves_pending_entry_to_exit_statistics(): void
    {
        $form = CustomForm::query()->create([
            'name' => json_encode(['en' => 'Exit Review Form']),
            'slug' => 'exit-review-form',
            'is_active' => true,
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
        ]);

        $entryId = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $form->id,
            'review_status' => 'accepted',
            'data' => json_encode(['candidate_status' => 'pending']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        CandidateStatisticsSynchronizer::syncEntry(CustomFormEntry::query()->findOrFail($entryId));

        $this->assertFalse(
            CandidateEntranceStatisticResource::getEloquentQuery()
                ->whereKey($entryId)
                ->exists(),
        );
        $this->assertDatabaseHas('candidate_exit_statistics', [
            'custom_form_entry_id' => $entryId,
            'candidate_status' => 'pending',
        ]);
    }
}
