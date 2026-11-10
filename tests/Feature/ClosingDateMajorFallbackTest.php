<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClosingDates\Pages\CreateClosingDate;
use App\Filament\Admin\Resources\ClosingDates\Pages\EditClosingDate;
use App\Models\ClosingDate;
use App\Models\DegreeLevel;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Support\StudentDynamicFormSchema;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ClosingDateMajorFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReflectionProperty(CustomForm::class, 'profileFormIdCache'))->setValue(null, []);
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->travelTo(Carbon::parse('2026-11-01 10:00:00'));

        $admin = User::query()->forceCreate([
            'registration_type' => 'admin', 'name' => 'Major administrator', 'username' => 'major_admin',
            'email' => 'major_admin@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public function test_creating_a_schedule_without_major_selections_shows_all_active_majors_in_the_candidate_field(): void
    {
        $form = $this->form();
        $degree = DegreeLevel::query()->create(['key' => 'bachelor', 'label_en' => 'Bachelor', 'is_active' => true]);
        $nursing = $this->major('nursing', 'Nursing', 'NUR', 'គិលានុបដ្ឋាក');
        $medicine = $this->major('medicine', 'Medicine', 'MED', 'វេជ្ជសាស្ត្រ');
        $inactive = $this->major('inactive', 'Dentistry', 'DEN', active: false);
        $degree->majors()->attach([$nursing->id, $medicine->id, $inactive->id]);

        Livewire::test(CreateClosingDate::class)->fillForm([
            'name' => 'All majors intake', 'type' => [ClosingDate::customFormTypeKey($form->id)],
            'degree_level_id' => $degree->id,
            'status' => 'open', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ])->call('create')->assertHasNoFormErrors();

        $this->assertSame([], ClosingDate::query()->sole()->majors()->pluck('majors.id')->all());
        $expected = [$medicine->id => 'MED-Medicine', $nursing->id => 'NUR-Nursing'];
        $this->assertSame($expected, Major::optionsForCustomFormId($form->id));
        $this->assertSame($expected, $this->candidateOptions($form));

        app()->setLocale('km');
        $this->assertSame([
            $medicine->id => 'MED-វេជ្ជសាស្ត្រ',
            $nursing->id => 'NUR-គិលានុបដ្ឋាក',
        ], $this->candidateOptions($form));
    }

    public function test_empty_major_selection_still_respects_the_selected_degree_level(): void
    {
        $form = $this->form();
        $degree = DegreeLevel::query()->create(['key' => 'bachelor', 'label_en' => 'Bachelor']);
        $schedule = $this->schedule($form, ['degree_level_id' => $degree->id]);
        $allowed = $this->major('nursing', 'Nursing', 'NUR');
        $allowed->degreeLevels()->attach($degree);
        $inactive = $this->major('inactive', 'Inactive nursing', 'INACTIVE', active: false);
        $inactive->degreeLevels()->attach($degree);
        $this->major('other_degree', 'Medicine', 'MED');

        $this->assertSame([$allowed->id => 'NUR-Nursing'], Major::optionsForCustomFormId($form->id));

        $emptyDegree = DegreeLevel::query()->create(['key' => 'empty_degree', 'label_en' => 'Empty degree']);
        $schedule->update(['degree_level_id' => $emptyDegree->id]);
        $this->assertSame([], Major::optionsForCustomFormId($form->id));
    }

    public function test_explicit_major_selections_do_not_expand_when_their_available_options_are_empty(): void
    {
        $form = $this->form();
        $schedule = $this->schedule($form);
        $selected = $this->major('nursing', 'Nursing', 'NUR');
        $inactive = $this->major('inactive', 'Inactive nursing', 'INACTIVE', active: false);
        $this->major('unselected', 'Medicine', 'MED');
        $schedule->majors()->sync([$selected->id, $inactive->id]);

        $this->assertSame([$selected->id => 'NUR-Nursing'], Major::optionsForCustomFormId($form->id));

        $schedule->majors()->sync([$inactive->id]);
        $this->assertSame([], Major::optionsForCustomFormId($form->id));

        $schedule->majors()->sync([$selected->id]);
        $degree = DegreeLevel::query()->create(['key' => 'unrelated', 'label_en' => 'Unrelated degree']);
        $schedule->update(['degree_level_id' => $degree->id]);
        $this->assertSame([], Major::optionsForCustomFormId($form->id));
    }

    public function test_clearing_saved_major_selections_switches_the_candidate_field_back_to_all_active_majors(): void
    {
        $form = $this->form();
        $degree = DegreeLevel::query()->create(['key' => 'bachelor', 'label_en' => 'Bachelor', 'is_active' => true]);
        $schedule = $this->schedule($form, ['degree_level_id' => $degree->id]);
        $medicine = $this->major('medicine', 'Medicine', 'MED');
        $nursing = $this->major('nursing', 'Nursing', 'NUR');
        $degree->majors()->attach([$medicine->id, $nursing->id]);
        $schedule->majors()->attach($medicine);
        $this->assertSame([$medicine->id => 'MED-Medicine'], $this->candidateOptions($form));

        Livewire::test(EditClosingDate::class, ['record' => $schedule->getRouteKey()])
            ->fillForm(['major_ids' => []])->call('save')->assertHasNoFormErrors();

        $this->assertSame([], $schedule->majors()->pluck('majors.id')->all());
        $this->assertSame([
            $medicine->id => 'MED-Medicine', $nursing->id => 'NUR-Nursing',
        ], $this->candidateOptions($form));
    }

    public function test_missing_schedules_and_form_ids_keep_their_existing_empty_options(): void
    {
        $this->major('medicine', 'Medicine', 'MED');

        $this->assertSame([], Major::optionsForCustomFormId($this->form()->id));
        $this->assertSame([], Major::optionsForCustomFormId(null));
        $this->assertSame([], Major::optionsForCustomFormId(999));
    }

    public function test_major_bulk_actions_follow_the_selected_degree_and_can_reset_saved_selections(): void
    {
        $form = $this->form();
        $bachelor = DegreeLevel::query()->create(['key' => 'bachelor', 'label_en' => 'Bachelor', 'is_active' => true]);
        $master = DegreeLevel::query()->create(['key' => 'master', 'label_en' => 'Master', 'is_active' => true]);
        $medicine = $this->major('medicine', 'Medicine', 'MED');
        $nursing = $this->major('nursing', 'Nursing', 'NUR');
        $inactive = $this->major('inactive', 'Inactive major', 'INACTIVE', active: false);
        $research = $this->major('research', 'Research', 'RES');
        $this->major('unrelated', 'Unrelated major', 'OTHER');
        $bachelor->majors()->attach([$medicine->id, $nursing->id, $inactive->id]);
        $master->majors()->attach($research);

        $selectAll = TestAction::make('select_all_majors')->schemaComponent('major_ids', schema: 'form');
        $resetAll = TestAction::make('reset_all_majors')->schemaComponent('major_ids', schema: 'form');
        $create = Livewire::test(CreateClosingDate::class)
            ->fillForm(['degree_level_id' => $bachelor->id])
            ->callAction($selectAll)
            ->assertFormSet(['major_ids' => [$medicine->id, $nursing->id]])
            ->callAction($resetAll)
            ->assertFormSet(['major_ids' => []])
            ->set('data.degree_level_id', $master->id)
            ->callAction($selectAll)
            ->assertFormSet(['major_ids' => [$research->id]]);

        $create->fillForm([
            'name' => 'Selected majors intake', 'type' => [ClosingDate::customFormTypeKey($form->id)],
            'status' => 'open', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ])->call('create')->assertHasNoFormErrors();
        $schedule = ClosingDate::query()->sole();
        $this->assertSame([$research->id], $schedule->majors()->pluck('majors.id')->all());

        Livewire::test(EditClosingDate::class, ['record' => $schedule->getRouteKey()])
            ->callAction($resetAll)
            ->assertFormSet(['major_ids' => []])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame([], $schedule->majors()->pluck('majors.id')->all());
        $this->assertSame([$research->id => 'RES-Research'], $this->candidateOptions($form));
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create(['name' => 'Major application', 'slug' => 'major-application', 'is_active' => true]);
    }

    private function schedule(CustomForm $form, array $attributes = []): ClosingDate
    {
        return ClosingDate::query()->create([
            'name' => 'Major intake', 'type' => ClosingDate::customFormTypeKey($form->id),
            'status' => 'open', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
            ...$attributes,
        ]);
    }

    private function major(string $key, string $name, string $abbreviation, ?string $khmer = null, bool $active = true): Major
    {
        return Major::query()->create([
            'key' => $key, 'name_en' => $name, 'name_kh' => $khmer,
            'abbreviation' => $abbreviation, 'is_active' => $active,
        ]);
    }

    private function candidateOptions(CustomForm $form): array
    {
        return (new ReflectionMethod(StudentDynamicFormSchema::class, 'getSelectOptions'))->invoke(
            app(StudentDynamicFormSchema::class),
            (object) ['custom_form_id' => $form->id],
            ['data_source_table' => 'majors'],
            null,
        );
    }
}
