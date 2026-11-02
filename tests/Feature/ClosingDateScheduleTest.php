<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClosingDates\Pages\CreateClosingDate;
use App\Filament\Admin\Resources\ClosingDates\Pages\EditClosingDate;
use App\Models\ClosingDate;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Support\ClosingDateWorkflow;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

class ClosingDateScheduleTest extends TestCase
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
            'registration_type' => 'admin', 'name' => 'Schedule administrator', 'username' => 'schedule_admin',
            'email' => 'schedule_admin@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public function test_the_same_form_can_be_selected_and_created_again_without_replacing_its_previous_schedule(): void
    {
        $form = $this->form('Repeatable application');
        $profile = $this->form('Profile', 'profile');
        $inactive = $this->form('Inactive application');
        $inactive->update(['is_active' => false]);
        $type = ClosingDate::customFormTypeKey($form->id);
        Livewire::test(CreateClosingDate::class)->fillForm([
            'name' => 'First intake', 'type' => [$type], 'status' => 'open',
            'start_date' => '2026-11-01', 'end_date' => '2026-11-10',
        ])->call('create')->assertHasNoFormErrors();
        $original = ClosingDate::query()->sole();

        $options = ClosingDate::selectableTypeOptions();
        $this->assertArrayHasKey($type, $options);
        $this->assertArrayNotHasKey(ClosingDate::customFormTypeKey($profile->id), $options);
        $this->assertArrayNotHasKey(ClosingDate::customFormTypeKey($inactive->id), $options);
        Livewire::test(CreateClosingDate::class)->fillForm([
            'name' => 'Second intake', 'type' => [$type], 'status' => 'open',
            'start_date' => '2026-11-05', 'end_date' => '2026-11-20',
        ])->call('create')->assertHasNoFormErrors();

        $this->assertSame(2, ClosingDate::query()->where('type', $type)->count());
        $this->assertSame('First intake', $original->fresh()->name);
        $this->assertSame('2026-11-10', $original->fresh()->end_date->toDateString());
        $this->assertSame('2026-11-20', ClosingDate::query()->latest('id')->first()->end_date->toDateString());
    }

    public function test_editing_can_reuse_a_form_already_selected_by_another_schedule(): void
    {
        $firstForm = $this->form('First application');
        $secondForm = $this->form('Second application');
        $first = $this->schedule($firstForm, '2026-11-01', '2026-11-10');
        $second = $this->schedule($secondForm, '2026-11-01', '2026-11-20');
        $type = ClosingDate::customFormTypeKey($firstForm->id);

        Livewire::test(EditClosingDate::class, ['record' => $second->getRouteKey()])
            ->set('data.type', $type)->call('save')->assertHasNoFormErrors();
        $this->assertSame($type, $first->fresh()->type);
        $this->assertSame($type, $second->fresh()->type);
        $this->assertSame(2, ClosingDate::query()->where('type', $type)->count());
    }

    public function test_an_expired_schedule_can_be_followed_by_a_new_open_schedule_for_the_same_form(): void
    {
        $form = $this->form('Renewed application');
        $expired = $this->schedule($form, '2026-10-01', '2026-10-31');
        $this->assertSame('closed', $expired->fresh()->status);
        $active = $this->schedule($form, '2026-11-01', '2026-11-30');

        $this->assertSame($active->id, ClosingDate::getDeadlineByCustomFormId($form->id)?->id);
        $this->assertOpen($form, '30 Nov 2026');
        $this->assertSame('closed', $expired->fresh()->status);
        $this->assertSame(2, ClosingDate::query()->count());
    }

    public function test_overlapping_open_schedules_use_the_furthest_end_date_even_when_a_shorter_schedule_was_inserted_later(): void
    {
        $form = $this->form('Overlapping application');
        $long = $this->schedule($form, '2026-10-20', '2026-11-30');
        $short = $this->schedule($form, '2026-11-01', '2026-11-05');
        $this->assertGreaterThan($long->id, $short->id);

        $this->assertSame($long->id, ClosingDate::getDeadlineByCustomFormId($form->id)?->id);
        $this->assertOpen($form, '30 Nov 2026');
        $this->assertSame('30 Nov 2026', ClosingDateWorkflow::check(ClosingDate::customFormTypeKey($form->id))['end_date']);
    }

    public static function competingNonCurrentSchedules(): array
    {
        return [
            'future' => ['open', '2026-12-01', '2026-12-31'],
            'manually closed' => ['closed', '2026-10-01', '2026-12-31'],
            'not open' => ['not_open', '2026-10-01', '2026-12-31'],
            'expired' => ['open', '2026-10-01', '2026-10-31'],
        ];
    }

    #[DataProvider('competingNonCurrentSchedules')]
    public function test_a_current_open_schedule_is_not_masked_by_a_later_non_current_row(string $status, string $start, string $end): void
    {
        $form = $this->form('Active application');
        $active = $this->schedule($form, '2026-11-01', '2026-11-15');
        $this->schedule($form, $start, $end, $status);

        $this->assertSame($active->id, ClosingDate::getDeadlineByCustomFormId($form->id)?->id);
        $this->assertOpen($form, '15 Nov 2026');
    }

    public function test_an_open_schedule_includes_its_entire_start_and_end_dates_then_closes_after_midnight(): void
    {
        $form = $this->form('One day application');
        $this->schedule($form, '2026-11-01', '2026-11-01');
        $this->travelTo(Carbon::parse('2026-11-01 00:00:00'));
        $this->assertOpen($form, '01 Nov 2026');
        $this->travelTo(Carbon::parse('2026-11-01 23:59:59'));
        $this->assertOpen($form, '01 Nov 2026');
        $this->travelTo(Carbon::parse('2026-11-02 00:00:00'));

        $this->assertFalse(ClosingDate::isCustomFormOpen($form->id));
        $this->assertTrue(ClosingDate::isCustomFormClosed($form->id));
        $this->assertSame(ClosingDateWorkflow::STATE_EXPIRED, ClosingDateWorkflow::checkByCustomFormId($form->id)['state']);
        $this->assertFalse(ClosingDateWorkflow::checkByCustomFormId($form->id)['can_submit']);
    }

    public function test_a_gap_between_schedules_keeps_the_latest_future_schedule_hidden_until_its_start_date(): void
    {
        $form = $this->form('Gapped application');
        $this->schedule($form, '2026-10-01', '2026-10-31');
        $future = $this->schedule($form, '2026-11-05', '2026-11-20');
        $state = ClosingDateWorkflow::checkByCustomFormId($form->id);

        $this->assertSame($future->id, ClosingDate::getDeadlineByCustomFormId($form->id)?->id);
        $this->assertSame(ClosingDateWorkflow::STATE_HIDDEN, $state['state']);
        $this->assertFalse($state['can_see_form']);
        $this->assertFalse($state['can_open_form']);
        $this->assertFalse($state['can_submit']);
        $this->travelTo(Carbon::parse('2026-11-05 00:00:00'));
        $this->assertOpen($form, '20 Nov 2026');
    }

    public function test_soft_deleted_schedules_do_not_extend_or_hide_the_effective_open_schedule(): void
    {
        $form = $this->form('Active nondeleted application');
        $active = $this->schedule($form, '2026-11-01', '2026-11-15');
        $deleted = $this->schedule($form, '2026-10-01', '2026-12-31');
        $deleted->delete();

        $this->assertSame($active->id, ClosingDate::getDeadlineByCustomFormId($form->id)?->id);
        $this->assertOpen($form, '15 Nov 2026');
    }

    public function test_workflow_alias_lookup_uses_the_same_longest_current_open_rule(): void
    {
        $form = $this->form('Alias application', 'alias-application');
        $alias = $this->schedule($form, '2026-11-01', '2026-11-30');
        $alias->update(['type' => 'custom_form_'.$form->id]);
        $this->schedule($form, '2026-11-01', '2026-11-05');

        $this->assertSame('30 Nov 2026', ClosingDateWorkflow::checkByCustomFormId($form->id)['end_date']);
        $this->assertSame('30 Nov 2026', ClosingDateWorkflow::check('custom_form_'.$form->id)['end_date']);
        $this->assertSame(ClosingDateWorkflow::STATE_OPEN, ClosingDateWorkflow::checkByCustomFormId($form->id)['state']);
    }

    public function test_major_options_follow_the_effective_schedule_without_merging_other_schedule_settings(): void
    {
        $form = $this->form('Major application');
        $long = $this->schedule($form, '2026-11-01', '2026-11-30');
        $short = $this->schedule($form, '2026-11-01', '2026-11-05');
        $longMajor = Major::query()->create(['key' => 'long', 'abbreviation' => 'LONG', 'name_en' => 'Long schedule major', 'is_active' => true]);
        $shortMajor = Major::query()->create(['key' => 'short', 'abbreviation' => 'SHORT', 'name_en' => 'Short schedule major', 'is_active' => true]);
        $long->majors()->attach($longMajor);
        $short->majors()->attach($shortMajor);

        $this->assertSame([$longMajor->id => 'LONG-Long schedule major'], Major::optionsForCustomFormId($form->id));
        $this->assertSame([$longMajor->id], $long->fresh()->majors()->pluck('majors.id')->all());
        $this->assertSame([$shortMajor->id], $short->fresh()->majors()->pluck('majors.id')->all());
    }

    public function test_profile_and_missing_rule_workflow_behavior_are_unchanged(): void
    {
        $profile = $this->form('Profile', 'profile');
        $this->schedule($profile, '2026-10-01', '2026-10-31', 'closed');
        $this->schedule($profile, '2026-12-01', '2026-12-31', 'not_open');
        $this->assertTrue(ClosingDate::isCustomFormOpen($profile->id));
        $this->assertFalse(ClosingDate::shouldShowContact($profile->id));
        $this->assertSame(ClosingDateWorkflow::STATE_OPEN, ClosingDateWorkflow::checkByCustomFormId($profile->id)['state']);
        $this->assertTrue(ClosingDateWorkflow::checkByCustomFormId($this->form('Unscheduled application')->id)['can_submit']);
    }

    private function assertOpen(CustomForm $form, string $end): void
    {
        $state = ClosingDateWorkflow::checkByCustomFormId($form->id);
        $this->assertSame(ClosingDateWorkflow::STATE_OPEN, $state['state']);
        $this->assertTrue($state['can_see_form']);
        $this->assertTrue($state['can_open_form']);
        $this->assertTrue($state['can_submit']);
        $this->assertFalse($state['show_contact']);
        $this->assertSame($end, $state['end_date']);
        $this->assertTrue(ClosingDate::isCustomFormOpen($form->id));
        $this->assertFalse(ClosingDate::isCustomFormClosed($form->id));
        $this->assertFalse(ClosingDate::shouldShowContact($form->id));
    }

    private function form(string $name, ?string $slug = null): CustomForm
    {
        return CustomForm::query()->create(['name' => $name, 'slug' => $slug ?? 'schedule-'.uniqid(), 'is_active' => true]);
    }

    private function schedule(CustomForm $form, string $start, string $end, string $status = 'open'): ClosingDate
    {
        return ClosingDate::query()->create([
            'name' => 'Intake '.uniqid(), 'type' => ClosingDate::customFormTypeKey($form->id),
            'status' => $status, 'start_date' => $start, 'end_date' => $end,
        ]);
    }
}
