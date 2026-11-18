<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Models\CandidateEntranceStatistic;
use App\Models\Role;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CandidateEntranceStatisticSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFormOptions();
    }

    protected function tearDown(): void
    {
        $this->resetFormOptions();
        parent::tearDown();
    }

    private function resetFormOptions(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new \ReflectionProperty(\App\Support\FormEntryData::class, $property))->setValue(null, []);
        }
    }

    public function test_global_search_narrows_candidate_entrance_statistics_records(): void
    {
        $matching = $this->createEntry(
            seatNumber: 'DEMO-087',
            nameEnglish: 'Sok Dara',
            nameKhmer: 'សុខ ដារា',
        );
        $other = $this->createEntry(
            seatNumber: 'DEMO-088',
            nameEnglish: 'Chan Lina',
            nameKhmer: 'ចាន់ លីណា',
        );

        $this->assertSame([$matching->id], $this->search('DEMO-087')->pluck('id')->all());
        $this->assertSame([$other->id, $matching->id], $this->search('DEMO')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('Sok Dara')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('sok dara')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('SOK')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('សុខ')->pluck('id')->all());
    }

    public function test_global_search_returns_no_records_for_an_impossible_value(): void
    {
        $this->createEntry(seatNumber: 'DEMO-087', nameEnglish: 'Sok Dara', nameKhmer: 'សុខ ដារា');

        $this->assertCount(0, $this->search('NO_SUCH_QA_927')->get());
    }

    public function test_search_finds_displayed_candidate_numbers_major_labels_gender_dates_and_configured_result_status(): void
    {
        $matching = $this->createEntry('SEAT-QA-001', 'Sok Dara', 'សុខ ដារា');
        $other = $this->createEntry('SEAT-QA-002', 'Chan Lina', 'ចាន់ លីណា');
        $major = \App\Models\Major::query()->create([
            'key' => 'qa_medical', 'abbreviation' => 'QMED', 'name_en' => 'Clinical Medicine', 'name_kh' => 'វេជ្ជសាស្ត្រ', 'is_active' => true,
        ]);
        $matching->forceFill([
            'candidate_id' => 'MED260002',
            'data' => [...$matching->data, 'selected_major' => $major->id, 'gender' => 'male',
                'date_of_birth' => '2000-11-18', 'candidate_status' => 'passed', 'candidate_reviewed_at' => '2026-11-18 16:07:00', 'academic_year' => '2026_2027'],
            'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => true, 'stages' => [[
                'data' => ['stage_type' => 'awaiting_results', 'status_message' => 'Qualified Clinical Candidate'],
            ]]],
        ])->saveQuietly();

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $majorText = $locale === 'en' ? 'cLiNiCaL mEdIcInE' : 'វេជ្ជសាស្ត្រ';
            $genderText = $locale === 'en' ? 'mAlE' : 'ប្រុស';
            foreach (['MED-0002', 'med-0002', 'mEd260002', $majorText, $genderText, '18-Nov-2000', '១៨-វិច្ឆិកា-២០០០', '18-Nov-2026', 'qualified clinical candidate', '2026-2027', '២០២៦-២០២៧'] as $term) {
                $this->assertSame([$matching->id], $this->search($term)->whereKey([$matching->id, $other->id])->pluck('id')->all(), $locale.' '.$term);
            }
        }
    }

    public function test_exit_statistics_searches_its_related_visible_candidate_id_and_displayed_values_without_leaking_the_outer_scope(): void
    {
        $entry = $this->createEntry('EXIT-QA-001', 'Sok Dara', 'សុខ ដារា');
        $entry->forceFill(['candidate_id' => 'MED260002'])->saveQuietly();
        $matching = \App\Models\CandidateExitStatistic::query()->create([
            'custom_form_entry_id' => $entry->id, 'form_type' => 'bachelor', 'academic_year' => '2026-2027', 'user_type' => 'candidate',
            'gender' => 'male', 'date_of_birth' => '2000-11-18', 'candidate_status' => 'passed', 'candidate_reviewed_at' => '2026-11-18',
        ]);
        $outsideEntry = $this->createEntry('EXIT-QA-002', 'Chan Lina', 'ចាន់ លីណា');
        $outsideEntry->forceFill(['candidate_id' => 'MED270002'])->saveQuietly();
        \App\Models\CandidateExitStatistic::query()->create([
            'custom_form_entry_id' => $outsideEntry->id, 'gender' => 'male', 'date_of_birth' => '2000-11-18', 'candidate_status' => 'passed',
        ]);
        app()->setLocale('km');
        foreach (['MED-0002', 'med-0002', 'mEd260002', 'ប្រុស', '១៨-វិច្ឆិកា-២០០០', '18-Nov-2026'] as $term) {
            $query = \App\Models\CandidateExitStatistic::query()->whereKey($matching->id);
            (new ReflectionMethod(\App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable::class, 'applyGlobalSearch'))->invoke(null, $query, $term);
            $this->assertSame([$matching->id], $query->pluck('id')->all(), $term);
        }
    }

    public function test_clearing_search_resets_the_livewire_search_state(): void
    {
        $this->createEntry(seatNumber: 'DEMO-087', nameEnglish: 'Sok Dara', nameKhmer: 'សុខ ដារា');

        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $permission = \Spatie\Permission\Models\Permission::query()->firstOrCreate([
            'name' => 'ViewAny:CandidateEntranceStatistic',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $admin = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Search Admin',
            'username' => 'search_admin',
            'email' => 'search-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin);

        Livewire::test(ListCandidateEntranceStatistics::class)
            ->set('tableSearch', 'NO_SUCH_QA_927')
            ->call('resetTableSearch')
            ->assertSet('tableSearch', '');
    }

    private function search(string $term): Builder
    {
        $query = CandidateEntranceStatisticResource::getEloquentQuery();
        $method = new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'applyGlobalSearch');
        $method->invoke(null, $query, $term);

        return $query;
    }

    private function createEntry(string $seatNumber, string $nameEnglish, string $nameKhmer): CandidateEntranceStatistic
    {
        $username = strtolower(str_replace('-', '_', $seatNumber)).'_'.strtolower(str_replace(' ', '_', $nameEnglish));
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => $nameKhmer,
            'name_latin' => $nameEnglish,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'seat_number' => $seatNumber,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $form = CustomForm::query()->create([
            'name' => 'Admission Form '.uniqid(),
            'slug' => 'admission-form-'.uniqid(),
            'is_active' => true,
            'requires_payment' => false,
        ]);

        $entry = CandidateEntranceStatistic::query()->create([
            'custom_form_id' => $form->id,
            'created_by' => $user->id,
            'data' => [
                'seat_number' => $seatNumber,
                'first_name_en' => explode(' ', $nameEnglish)[0],
                'last_name_en' => explode(' ', $nameEnglish, 2)[1] ?? '',
                'first_name_kh' => explode(' ', $nameKhmer)[0],
                'last_name_kh' => explode(' ', $nameKhmer, 2)[1] ?? '',
                'candidate_status' => 'pending',
            ],
        ]);

        $entry->forceFill(['review_status' => 'accepted'])->saveQuietly();

        DB::table('payments')->insert([
            'users_id' => $user->id,
            'form_id' => $form->id,
            'custom_form_entry_id' => $entry->id,
            'status_payt' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $entry->fresh();
    }
}
