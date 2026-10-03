<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Models\CandidateExitStatistic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CandidateExitStatisticResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_table_has_the_standalone_candidate_fields(): void
    {
        $this->assertTrue(Schema::hasTable('candidate_exit_statistics'));
        $this->assertTrue(Schema::hasColumns('candidate_exit_statistics', [
            'form_type',
            'academic_year',
            'user_type',
            'seat_number',
            'first_name_kh',
            'last_name_kh',
            'first_name_en',
            'last_name_en',
            'gender',
            'major',
            'date_of_birth',
            'candidate_status',
            'candidate_reviewed_at',
            'hidden_from_statistics',
        ]));
    }

    public function test_statistics_records_are_stored_in_their_own_model_and_table(): void
    {
        $record = CandidateExitStatistic::query()->create([
            'form_type' => 'bachelor',
            'academic_year' => '2026-2027',
            'user_type' => 'candidate',
            'seat_number' => 'ENT-001',
            'first_name_kh' => 'ដារា',
            'last_name_kh' => 'សុខ',
            'first_name_en' => 'Dara',
            'last_name_en' => 'Sok',
            'gender' => 'male',
            'major' => 'Medicine',
            'date_of_birth' => '2000-01-01',
            'candidate_status' => 'pending',
            'hidden_from_statistics' => false,
        ]);

        $this->assertSame('candidate_exit_statistics', $record->getTable());
        $this->assertDatabaseHas('candidate_exit_statistics', [
            'id' => $record->id,
            'seat_number' => 'ENT-001',
            'candidate_status' => 'pending',
        ]);
    }

    public function test_resource_is_independent_from_candidate_entrance_statistics(): void
    {
        $this->assertSame(
            CandidateExitStatistic::class,
            CandidateExitStatisticResource::getModel(),
        );

        App::setLocale('km');
        $this->assertSame('ស្ថិតិបេក្ខជនប្រឡងចេញ', CandidateExitStatisticResource::getNavigationLabel());
        $this->assertSame(2, CandidateExitStatisticResource::getNavigationSort());

        App::setLocale('en');
        $this->assertSame('Exit Exam Statistics', CandidateExitStatisticResource::getNavigationLabel());
    }

    public function test_resource_does_not_register_a_direct_edit_page(): void
    {
        $this->assertArrayNotHasKey('edit', CandidateExitStatisticResource::getPages());
    }
}
