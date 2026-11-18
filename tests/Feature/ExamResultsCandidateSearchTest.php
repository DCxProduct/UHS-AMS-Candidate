<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Role;
use App\Models\User;
use App\Support\AccountStorage;
use App\Support\MenuRecordVisibility;
use App\Support\PassedResultMenuOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExamResultsCandidateSearchTest extends TestCase
{
    use RefreshDatabase;

    private int $ownerSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Candidate search administrator', 'username' => 'candidate-search-admin',
            'password' => 'password', 'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function menus(): array
    {
        return [
            'entrance results' => [ListExamResults::class, PassedResultMenuOptions::EXAM_RESULTS],
            'exit results' => [ListExitExamResults::class, PassedResultMenuOptions::EXIT_EXAM_RESULTS],
        ];
    }

    #[DataProvider('menus')]
    public function test_global_search_matches_visible_and_stored_candidate_numbers_in_any_letter_case(string $page, string $menu): void
    {
        $form = $this->form($menu);
        $owner = $this->owner();
        $match = $this->entry($form, $owner, 'MED260002');
        $other = $this->entry($form, $owner, 'PHA260003');
        $list = Livewire::test($page)
            ->assertCanSeeTableRecords([$match, $other])
            ->assertTableColumnStateSet('candidate_id', 'MED-0002', $match)
            ->assertSee('MED-0002');

        foreach (['MED-0002', 'med-0002', 'MeD-0002', 'MED-000', 'MED260002', 'med260002', 'MED26', '0002'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$match])
                ->assertCanNotSeeTableRecords([$other])->assertCountTableRecords(1);
            $this->assertSame([$match->id], $list->instance()->getFilteredTableQuery()->pluck('custom_form_entries.id')->all(), $search);
        }
        $list->searchTable('MED-9999')->assertCanNotSeeTableRecords([$match, $other])->assertCountTableRecords(0);
        $this->assertSame('MED260002', $match->fresh()->candidate_id);
    }

    #[DataProvider('menus')]
    public function test_creator_candidate_number_is_searchable_only_when_the_entry_has_no_number_of_its_own(string $page, string $menu): void
    {
        $form = $this->form($menu);
        $owner = $this->owner('MED260007');
        $nullId = $this->entry($form, $owner, null);
        $emptyId = $this->entry($form, $owner, '');
        $ownId = $this->entry($form, $owner, 'PHA260004');
        $list = Livewire::test($page)
            ->assertTableColumnStateSet('candidate_id', 'MED-0007', $nullId)
            ->assertTableColumnStateSet('candidate_id', 'MED-0007', $emptyId)
            ->assertTableColumnStateSet('candidate_id', 'PHA-0004', $ownId);

        foreach (['MED-0007', 'med260007'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$nullId, $emptyId])
                ->assertCanNotSeeTableRecords([$ownId])->assertCountTableRecords(2);
        }
        $list->searchTable('pHa-0004')->assertCanSeeTableRecords([$ownId])
            ->assertCanNotSeeTableRecords([$nullId, $emptyId])->assertCountTableRecords(1);
    }

    #[DataProvider('menus')]
    public function test_candidate_search_preserves_result_menu_passed_status_and_cleared_record_boundaries(string $page, string $menu): void
    {
        $hiddenFlag = $menu === PassedResultMenuOptions::EXAM_RESULTS ? ExamResultResource::HIDDEN_FLAG : ExitExamResultResource::HIDDEN_FLAG;
        $form = $this->form($menu);
        $otherMenu = $this->form($menu === PassedResultMenuOptions::EXAM_RESULTS
            ? PassedResultMenuOptions::EXIT_EXAM_RESULTS
            : PassedResultMenuOptions::EXAM_RESULTS);
        $owner = $this->owner('MED260002');
        $ownId = $this->entry($form, $owner, 'MED260002');
        $fallbackId = $this->entry($form, $owner, null);
        $excluded = [
            $this->entry($otherMenu, $owner, 'MED260002'),
            $this->entry($otherMenu, $owner, null),
            $this->entry($form, $owner, 'MED260002', ['candidate_status' => 'pending', 'registration_status' => 'pending'], ['review_status' => 'pending']),
            $this->entry($form, $owner, null, ['candidate_status' => 'pending', 'registration_status' => 'pending'], ['review_status' => 'pending']),
            $this->entry($form, $owner, 'MED260002', [$hiddenFlag => true]),
            $this->entry($form, $owner, null, [], [MenuRecordVisibility::deletionColumn($hiddenFlag) => now()]),
        ];
        $list = Livewire::test($page)->assertCanSeeTableRecords([$ownId, $fallbackId])->assertCanNotSeeTableRecords($excluded);

        foreach (['MED-0002', 'med-0002', 'MED260002'] as $search) {
            $list->searchTable($search)->assertCanSeeTableRecords([$ownId, $fallbackId])
                ->assertCanNotSeeTableRecords($excluded)->assertCountTableRecords(2);
            $this->assertEqualsCanonicalizing([$ownId->id, $fallbackId->id], $list->instance()->getFilteredTableQuery()->pluck('custom_form_entries.id')->all());
        }
    }

    private function form(string $menu): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Candidate search '.$menu, 'slug' => 'candidate-search-'.$menu,
            'passed_result_menu' => $menu, 'is_active' => true,
        ]);
    }

    private function owner(?string $candidateId = null): User
    {
        if ($candidateId !== null && ! Schema::hasColumn('users', 'candidate_id')) {
            // Older installations exposed this optional account-level fallback.
            Schema::table('users', fn (Blueprint $table) => $table->string('candidate_id', 100)->nullable());
            DB::statement('DROP VIEW account_users');
            AccountStorage::createView();
        }
        $owner = new User;
        $owner->forceFill([
            'registration_type' => 'student', 'name' => 'SEARCH OWNER', 'name_latin' => 'SEARCH OWNER',
            'username' => 'candidate-search-owner-'.++$this->ownerSequence,
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
            ...($candidateId === null ? [] : ['candidate_id' => $candidateId]),
        ])->save();

        return $owner;
    }

    private function entry(CustomForm $form, User $owner, ?string $candidateId, array $data = [], array $attributes = []): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $owner->id,
            'candidate_id' => $candidateId, 'review_status' => 'passed',
            'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => false, 'stages' => []],
            'data' => ['candidate_status' => 'passed', 'registration_status' => 'approved', ...$data],
            ...$attributes,
        ])->saveQuietly();

        return $entry;
    }
}
