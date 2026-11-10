<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Support\FormEntryData;
use App\Support\PassedResultMenuOptions;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class ResultAndApplicationFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->resetOptionCaches();
        $this->admin = User::query()->create([
            'name' => 'Facet admin', 'username' => 'facet_admin', 'registration_type' => 'admin',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        $this->resetOptionCaches();
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function resetOptionCaches(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        (new ReflectionProperty(CustomFormEntriesTable::class, 'majorLocalizedNameCache'))->setValue(null, []);
        (new ReflectionProperty(CustomForm::class, 'profileFormIdCache'))->setValue(null, []);
    }

    public static function passedMenus(): array
    {
        return [
            'entrance results' => [ListExamResults::class, PassedResultMenuOptions::EXAM_RESULTS, 'hidden_from_exam_results'],
            'exit results' => [ListExitExamResults::class, PassedResultMenuOptions::EXIT_EXAM_RESULTS, 'hidden_from_exit_exam_results'],
        ];
    }

    #[DataProvider('passedMenus')]
    public function test_passed_result_filter_choices_follow_other_filters_search_and_menu_visibility(string $page, string $menu, string $hiddenFlag): void
    {
        $medicine = $this->major('medicine');
        $nursing = $this->major('nursing');
        $unlisted = $this->major('unlisted');
        $form = $this->form($menu);
        $this->entry($form, $medicine, '2025-2026', 'MedicineOnly');
        $this->entry($form, $medicine, '2026-2027', 'MedicineNextYear');
        $this->entry($form, $nursing, '2026-2027', 'NursingOnly');
        $multipleMajors = $this->entry($form, $medicine, '2026-2027', 'MultiMajorCandidate', ['major' => [$medicine->id, $nursing->id]]);
        $this->entry($form, $unlisted, '2088-2089', 'ClearedCandidate', [$hiddenFlag => true]);
        $otherMenu = $menu === PassedResultMenuOptions::EXAM_RESULTS
            ? PassedResultMenuOptions::EXIT_EXAM_RESULTS
            : PassedResultMenuOptions::EXAM_RESULTS;
        $this->entry($this->form($otherMenu), $unlisted, '2090-2091', 'OtherMenuCandidate');

        $component = Livewire::test($page);
        $this->assertSame([$medicine->id, $nursing->id], array_keys($this->filterOptions($component, 'exam_result_filters', 'major')));
        $this->assertSame(['2025-2026', '2026-2027'], array_keys($this->filterOptions($component, 'exam_result_filters', 'academic_year')));

        $component->set('tableFilters.exam_result_filters.academic_year', '2025-2026');
        $this->assertSame([$medicine->id], array_keys($this->filterOptions($component, 'exam_result_filters', 'major')));
        $this->assertSame(['2025-2026', '2026-2027'], array_keys($this->filterOptions($component, 'exam_result_filters', 'academic_year')));

        $component->set('tableFilters.exam_result_filters.academic_year', null)
            ->set('tableFilters.exam_result_filters.major', (string) $medicine->id);
        $this->assertSame([$medicine->id, $nursing->id], array_keys($this->filterOptions($component, 'exam_result_filters', 'major')));
        $this->assertContains($multipleMajors->id, $component->instance()->getFilteredTableQuery()->pluck('id')->all());

        $component->set('tableFilters.exam_result_filters.major', null)->set('tableSearch', 'NursingOnly');
        $this->assertSame([$nursing->id], array_keys($this->filterOptions($component, 'exam_result_filters', 'major')));
        $this->assertSame(['2026-2027'], array_keys($this->filterOptions($component, 'exam_result_filters', 'academic_year')));
        $component->set('tableSearch', 'NoCandidateHasThisValue');
        $this->assertSame([], $this->filterOptions($component, 'exam_result_filters', 'major'));
    }

    public function test_application_filters_keep_form_scope_and_exclude_drafts_and_cleared_rows(): void
    {
        $medicine = $this->major('medicine');
        $nursing = $this->major('nursing');
        $unlisted = $this->major('unlisted');
        $form = $this->form(PassedResultMenuOptions::EXAM_RESULTS);
        $this->entry($form, $medicine, '2025-2026', 'MedicineOnly', status: 'approved');
        $this->entry($form, $nursing, '2026-2027', 'NursingOnly', status: 'pending');
        $this->entry($form, $unlisted, '2090-2091', 'DraftCandidate', status: 'draft');
        $this->entry($form, $unlisted, '2090-2091', 'ClearedCandidate', ['hidden_from_form_entries' => true]);
        $this->entry($this->form(PassedResultMenuOptions::EXAM_RESULTS), $unlisted, '2090-2091', 'OtherApplication');

        $component = Livewire::withQueryParams(['form_id' => (string) $form->id])->test(ListCustomFormEntries::class);
        $this->assertSame([$medicine->id, $nursing->id], array_keys($this->filterOptions($component, 'application_review_filters', 'major')));
        $component->set('tableFilters.application_review_filters.review_status', 'accepted');
        $this->assertSame([$medicine->id], array_keys($this->filterOptions($component, 'application_review_filters', 'major')));

        $component->set('tableFilters.application_review_filters.review_status', null)
            ->set('tableFilters.application_review_filters.major', (string) $medicine->id);
        $this->assertSame([$medicine->id, $nursing->id], array_keys($this->filterOptions($component, 'application_review_filters', 'major')));
        $component->set('tableFilters.application_review_filters.major', null)->set('tableSearch', 'NursingOnly');
        $this->assertSame([$nursing->id], array_keys($this->filterOptions($component, 'application_review_filters', 'major')));
    }

    private function filterOptions($component, string $filter, string $field): array
    {
        $select = $component->instance()->getTableFiltersForm()->getComponentByStatePath($filter.'.'.$field);
        $this->assertInstanceOf(Select::class, $select);

        return $select->getOptions();
    }

    private function major(string $key): Major
    {
        return Major::query()->create(['key' => $key, 'name_en' => ucfirst($key), 'abbreviation' => strtoupper($key), 'is_active' => true]);
    }

    private function form(string $menu): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Facet application '.++$this->sequence, 'slug' => 'facet-application-'.$this->sequence,
            'is_active' => true, 'requires_payment' => false, 'passed_result_menu' => $menu,
        ]);
    }

    private function entry(CustomForm $form, Major $major, string $year, string $name, array $extra = [], string $status = 'passed'): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $this->admin->id, 'review_status' => $status,
            'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => false],
            'data' => [
                'major' => (string) $major->id, 'academic_year' => $year, 'first_name_kh' => $name,
                'first_name_en' => $name, 'last_name_en' => 'Candidate',
                'candidate_status' => $status === 'passed' ? 'passed' : 'pending',
                'registration_status' => $status, ...$extra,
            ],
        ])->saveQuietly();

        return $entry;
    }
}
