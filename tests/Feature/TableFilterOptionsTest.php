<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Models\CandidateExitStatistic;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Support\CandidateApplicationContext;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\FormEntryData;
use App\Support\StatisticsMenuOptions;
use App\Support\TableFilterOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

class TableFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    private const BACHELOR = 'national_entrance_exam_application_bachelor';

    private const ASSOCIATE = 'national_entrance_exam_application_associate';

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }

        (new ReflectionProperty(CustomForm::class, 'profileFormIdCache'))->setValue(null, []);
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Filter administrator', 'username' => 'filter_admin',
            'email' => 'filter-admin@example.test', 'date_of_birth' => '1990-01-01',
            'password' => 'test-password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public static function menus(): array
    {
        return ['entrance' => ['entrance'], 'exit' => ['exit']];
    }

    #[DataProvider('menus')]
    public function test_options_only_include_visible_menu_rows_across_all_pages(string $menu): void
    {
        $form = $this->form($menu, 'Included application');
        $medicine = $this->major('Medicine');
        $nursing = $this->major('Nursing');
        $unused = $this->major('Unused catalogue major');
        $otherMenu = $this->major('Other menu only');
        $hidden = $this->major('Cleared row only');
        for ($index = 0; $index < 10; $index++) {
            $this->entry($form, $medicine);
        }
        $this->entry($form, $nursing);
        $this->entry($this->form($menu === 'entrance' ? 'exit' : 'entrance', 'Other menu'), $otherMenu);
        $hiddenEntry = $this->entry($form, $hidden);
        if ($menu === 'exit') {
            CandidateExitStatistic::query()->where('custom_form_entry_id', $hiddenEntry->id)
                ->update(['hidden_from_statistics' => true]);
        } else {
            $hiddenEntry->forceFill(['data' => [...$hiddenEntry->data, 'hidden_from_review_applications' => true]])->saveQuietly();
        }

        $component = Livewire::test($this->page($menu));
        $options = $this->filterOptions($component, $menu, 'major');

        $this->assertSameKeys([$medicine->id, $nursing->id], $options);
        foreach ([$unused, $otherMenu, $hidden] as $absent) {
            $this->assertArrayNotHasKey($absent->id, $options);
        }
        $this->assertSameKeys(['form:'.$form->id], $this->filterOptions($component, $menu, $this->formField($menu)));
    }

    #[DataProvider('menus')]
    public function test_other_selected_filters_narrow_major_options_and_empty_combinations_stay_empty(string $menu): void
    {
        [$first, $second, $medicine, $nursing, $research] = $this->dataSet($menu);
        $component = Livewire::test($this->page($menu));

        $component->set($this->statePath($menu, $this->statusField($menu)), 'passed');
        $this->assertSameKeys([$research->id], $this->filterOptions($component, $menu, 'major'));
        $this->assertSameKeys(['form:'.$second->id], $this->filterOptions($component, $menu, $this->formField($menu)));
        $this->assertSameKeys([self::ASSOCIATE], $this->filterOptions($component, $menu, 'user_type'));

        $component->set($this->statePath($menu, $this->statusField($menu)), null)
            ->set($this->statePath($menu, $this->formField($menu)), 'form:'.$first->id);
        $this->assertSameKeys([$medicine->id, $nursing->id], $this->filterOptions($component, $menu, 'major'));

        $component->set($this->statePath($menu, 'user_type'), self::ASSOCIATE);
        $this->assertSame([], $this->filterOptions($component, $menu, 'major'));
        // Ignore this dropdown's own choice so the user can correct the empty combination.
        $this->assertSameKeys([self::BACHELOR], $this->filterOptions($component, $menu, 'user_type'));
    }

    #[DataProvider('menus')]
    public function test_own_filter_keeps_alternatives_while_other_options_follow_the_selected_major(string $menu): void
    {
        [$first, , $medicine, $nursing, $research] = $this->dataSet($menu);
        $component = Livewire::test($this->page($menu))
            ->set($this->statePath($menu, 'major'), (string) $medicine->id);
        $before = $component->instance()->tableFilters;
        $beforeDeferred = $component->instance()->tableDeferredFilters;

        $this->assertSameKeys([$medicine->id, $nursing->id, $research->id], $this->filterOptions($component, $menu, 'major'));
        $this->assertSameKeys(['form:'.$first->id], $this->filterOptions($component, $menu, $this->formField($menu)));
        $this->assertSameKeys([self::BACHELOR], $this->filterOptions($component, $menu, 'user_type'));
        $this->assertSame([__('candidate_entrance_statistics.statuses.pending')], array_values($this->filterOptions($component, $menu, $this->statusField($menu))));
        $this->assertSame($before, $component->instance()->tableFilters);
        $this->assertSame($beforeDeferred, $component->instance()->tableDeferredFilters);

        $component->set($this->statePath($menu, $this->statusField($menu)), 'pending');
        $this->assertSameKeys([$medicine->id, $nursing->id], $this->filterOptions($component, $menu, 'major'));
    }

    #[DataProvider('menus')]
    public function test_search_limits_options_and_clearing_it_restores_the_table_choices(string $menu): void
    {
        $first = $this->form($menu, 'First search application');
        $second = $this->form($menu, 'Second search application');
        $medicine = $this->major('Medicine');
        $nursing = $this->major('Nursing');
        $this->entry($first, $medicine, name: 'Needle Person');
        $this->entry($second, $nursing, name: 'Other Person');
        $component = Livewire::test($this->page($menu))->set('tableSearch', 'Needle');

        $this->assertSameKeys([$medicine->id], $this->filterOptions($component, $menu, 'major'));
        $this->assertSameKeys(['form:'.$first->id], $this->filterOptions($component, $menu, $this->formField($menu)));

        $component->set('tableSearch', 'No such candidate 93017');
        foreach (['major', 'user_type', $this->formField($menu), $this->statusField($menu)] as $field) {
            $this->assertSame([], $this->filterOptions($component, $menu, $field));
        }

        $component->set('tableSearch', '');
        $this->assertSameKeys([$medicine->id, $nursing->id], $this->filterOptions($component, $menu, 'major'));
    }

    public function test_each_major_in_an_array_is_a_selectable_option_for_the_matching_application(): void
    {
        $form = $this->form('entrance', 'Multiple major application');
        $medicine = $this->major('Medicine');
        $nursing = $this->major('Nursing');
        $research = $this->major('Research');
        $multiple = $this->entry($form, $medicine);
        $multiple->forceFill(['data' => [...$multiple->data, 'major' => [$medicine->id, (string) $nursing->id]]])->saveQuietly();
        $other = $this->entry($form, $research);
        $component = Livewire::test(ListCandidateEntranceStatistics::class);

        $this->assertSameKeys([$medicine->id, $nursing->id, $research->id], $this->filterOptions($component, 'entrance', 'major'));
        foreach ([$medicine->id, $nursing->id] as $id) {
            $component->set($this->statePath('entrance', 'major'), (string) $id)
                ->assertCanSeeTableRecords([$multiple])->assertCanNotSeeTableRecords([$other]);
        }
    }

    public function test_temporary_filter_exclusion_restores_applied_and_deferred_state_after_an_exception(): void
    {
        $component = Livewire::test(ListCandidateEntranceStatistics::class);
        $livewire = $component->instance();
        $livewire->tableFilters = ['application_review_filters' => ['major' => '91', 'review_status' => 'pending']];
        $livewire->tableDeferredFilters = ['application_review_filters' => ['major' => '92', 'review_status' => 'passed']];
        $before = $livewire->tableFilters;
        $beforeDeferred = $livewire->tableDeferredFilters;
        $livewire->getTable()->pushFilters([
            Filter::make('fail_query')->query(function (): never {
                throw new RuntimeException('A failing filter must not change form state.');
            }),
        ]);

        try {
            TableFilterOptions::query($livewire, 'application_review_filters', 'major');
            $this->fail('The query failure should be propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('A failing filter must not change form state.', $exception->getMessage());
        }

        $this->assertSame($before, $livewire->tableFilters);
        $this->assertSame($beforeDeferred, $livewire->tableDeferredFilters);
    }

    private function filterOptions($component, string $menu, string $field): array
    {
        $select = $component->instance()->getTableFiltersForm()->getComponentByStatePath($this->filter($menu).'.'.$field);
        $this->assertInstanceOf(Select::class, $select);

        return $select->getOptions();
    }

    private function assertSameKeys(array $expected, array $actual): void
    {
        $expected = array_map('strval', $expected);
        $actual = array_map('strval', array_keys($actual));
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    private function dataSet(string $menu): array
    {
        $first = $this->form($menu, 'First application');
        $second = $this->form($menu, 'Second application');
        $medicine = $this->major('Medicine');
        $nursing = $this->major('Nursing');
        $research = $this->major('Research');
        $this->entry($first, $medicine);
        $this->entry($first, $nursing);
        $this->entry($second, $research, status: 'passed', type: self::ASSOCIATE);

        return [$first, $second, $medicine, $nursing, $research];
    }

    private function form(string $menu, string $name): CustomForm
    {
        return CustomForm::query()->create([
            'name' => $name, 'slug' => 'facet-form-'.++$this->sequence,
            'is_active' => true, 'requires_payment' => false,
            'statistics_menu' => $menu === 'exit' ? StatisticsMenuOptions::EXIT_EXAM_STATISTICS : StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
        ]);
    }

    private function major(string $name): Major
    {
        return Major::query()->create([
            'key' => 'facet_major_'.++$this->sequence, 'name_en' => $name,
            'abbreviation' => strtoupper(substr($name, 0, 3)), 'is_active' => true,
        ]);
    }

    private function entry(CustomForm $form, Major $major, string $status = 'pending', string $type = self::BACHELOR, string $name = 'Candidate Person'): CustomFormEntry
    {
        $parts = explode(' ', $name, 2);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => auth()->id(), 'review_status' => 'approved',
            'data' => [
                'first_name_en' => $parts[0], 'last_name_en' => $parts[1] ?? '',
                'major' => (string) $major->id, 'candidate_status' => $status,
                'registration_status' => 'approved', CandidateApplicationContext::DATA_KEY => $type,
            ],
        ])->saveQuietly();
        CandidateStatisticsSynchronizer::syncEntry($entry);

        return $entry;
    }

    private function page(string $menu): string
    {
        return $menu === 'exit' ? ListCandidateExitStatistics::class : ListCandidateEntranceStatistics::class;
    }

    private function filter(string $menu): string
    {
        return $menu === 'exit' ? 'candidate_exit_statistics_filters' : 'application_review_filters';
    }

    private function formField(string $menu): string
    {
        return $menu === 'exit' ? 'form_type' : 'form_selection';
    }

    private function statusField(string $menu): string
    {
        return $menu === 'exit' ? 'candidate_status' : 'review_status';
    }

    private function statePath(string $menu, string $field): string
    {
        return 'tableFilters.'.$this->filter($menu).'.'.$field;
    }
}
