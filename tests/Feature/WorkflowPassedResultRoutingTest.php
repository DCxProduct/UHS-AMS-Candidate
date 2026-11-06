<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExamResults\Tables\ExamResultsTable;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowPassedResultRouting;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowStatisticsRouting;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\CreateCustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowPassedResultRoutingTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $admin = $this->user('admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('student');
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    public static function menus(): array
    {
        return [
            'entrance overrides exit' => [PassedResultMenuOptions::EXAM_RESULTS, PassedResultMenuOptions::EXIT_EXAM_RESULTS],
            'exit overrides entrance' => [PassedResultMenuOptions::EXIT_EXAM_RESULTS, PassedResultMenuOptions::EXAM_RESULTS],
        ];
    }

    #[DataProvider('locales')]
    public function test_completed_selector_is_required_and_validated_without_changing_the_static_fallback(string $locale): void
    {
        app()->setLocale($locale);
        [$form, $workflow] = $this->workflow(null);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $keys = array_keys($component->get('data.stages'));
        $path = 'data.stages.'.$keys[2].'.data.passed_result_menu';
        $fields = collect($component->instance()->form->getFlatFields(withHidden: true));
        $field = $fields->first(fn ($field): bool => $field->getStatePath() === $path);
        $this->assertNotNull($field);
        $this->assertTrue($field->isVisible());
        $this->assertTrue($field->isRequired());
        $this->assertSame(PassedResultMenuOptions::options(), $field->getOptions());
        foreach (array_slice($keys, 0, 2) as $key) {
            $other = $fields->first(fn ($field): bool => $field->getStatePath() === 'data.stages.'.$key.'.data.passed_result_menu');
            $this->assertNotNull($other);
            $this->assertFalse($other->isVisible());
            $this->assertFalse($other->isRequired());
        }

        $component->set($path, PassedResultMenuOptions::EXIT_EXAM_RESULTS)->call('save')->assertHasNoFormErrors();
        $saved = $workflow->fresh()->getRawOriginal('stages');
        $this->assertSame(PassedResultMenuOptions::EXIT_EXAM_RESULTS, data_get($workflow->fresh()->stages, '2.data.passed_result_menu'));
        $this->assertSame(PassedResultMenuOptions::EXAM_RESULTS, $form->fresh()->passed_result_menu);
        $this->assertSame(PassedResultMenuOptions::EXIT_EXAM_RESULTS, WorkflowPassedResultRouting::forForm($form->fresh()));
        $component->set($path, null)->call('save')->assertHasErrors([$path => 'required']);
        $this->assertSame($saved, $workflow->fresh()->getRawOriginal('stages'));
        $component->set($path, 'unrecognized_results')->call('save')->assertHasErrors([$path]);
        $this->assertSame($saved, $workflow->fresh()->getRawOriginal('stages'));

        $formComponent = Livewire::test(CreateCustomForm::class);
        $staticField = collect($formComponent->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.passed_result_menu');
        $this->assertTrue($staticField->isVisible());
        $formComponent->set('data.workflow_notification_id', $workflow->id);
        $staticField = collect($formComponent->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.passed_result_menu');
        $this->assertFalse($staticField->isVisible());
        $this->assertFalse($staticField->isDehydrated());
        $this->assertNull(WorkflowPassedResultRouting::forStages([
            ['type' => 'stage', 'data' => ['stage_type' => 'awaiting_results', 'passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS]],
        ]));
    }

    #[DataProvider('menus')]
    public function test_selected_result_menu_waits_for_all_frozen_gates_and_is_independent_of_statistics(string $menu, string $opposite): void
    {
        [$form] = $this->workflow($menu, $opposite, [
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults,
            WorkflowStageType::Review, WorkflowStageType::Completed,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertSame($menu, $entry->fresh()->workflow_snapshot['passed_result_menu']);
        $this->assertSame(StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, WorkflowStatisticsRouting::forEntry($entry->fresh()));
        $this->assertTrue(CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertRoutes($entry, null);

        Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertRoutes($entry, null);

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertRoutes($entry, $menu);
        $page = $menu === PassedResultMenuOptions::EXAM_RESULTS ? ListExamResults::class : ListExitExamResults::class;
        Livewire::test($page)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertRoutes($entry, $menu);
        $before = $this->storedEntry($entry);
        $this->assertTrue(WorkflowPassedResultRouting::apply(CustomFormEntry::query(), $menu)->whereKey($entry->id)->exists());
        $this->assertFalse(WorkflowPassedResultRouting::apply(CustomFormEntry::query(), $opposite)->whereKey($entry->id)->exists());
        $this->assertSame($before, $this->storedEntry($entry));
    }

    public function test_explicit_snapshot_route_survives_template_form_reassignment_and_removal(): void
    {
        [$form, $workflow] = $this->workflow(PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $old = $this->readyEntry($form);
        $oldRow = $this->storedEntry($old);
        $stages = $workflow->stages;
        $stages[2]['data']['passed_result_menu'] = PassedResultMenuOptions::EXAM_RESULTS;
        $workflow->update(['stages' => $stages]);
        $new = $this->readyEntry($form);
        $newRow = $this->storedEntry($new);
        $this->assertRoutes($old, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $this->assertRoutes($new, PassedResultMenuOptions::EXAM_RESULTS);

        $form->update(['passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS]);
        unset($stages[2]['data']['passed_result_menu']);
        $workflow->update(['stages' => $stages]);
        $this->assertArrayNotHasKey('passed_result_menu', WorkflowEntrySnapshot::forForm($form->id));
        $replacement = WorkflowNotification::query()->create(['name' => 'Replacement results workflow', 'stages' => $stages]);
        $workflow->forms()->detach($form);
        $replacement->forms()->attach($form);
        $workflow->delete();

        $this->assertRoutes($old, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $this->assertRoutes($new, PassedResultMenuOptions::EXAM_RESULTS);
        $this->assertSame($oldRow, $this->storedEntry($old));
        $this->assertSame($newRow, $this->storedEntry($new));
    }

    public function test_old_null_static_and_blank_snapshots_keep_the_live_form_fallback(): void
    {
        [$form, $workflow] = $this->workflow(null);
        $original = WorkflowEntrySnapshot::forForm($form->id);
        $this->assertArrayNotHasKey('passed_result_menu', $original);
        $snapshots = [
            'old workflow' => array_replace($original, ['schema_version' => 1]),
            'null snapshot' => null,
            'static' => ['schema_version' => 1, 'has_workflow' => false, 'stages' => []],
            'blank' => array_replace($original, ['schema_version' => 1, 'passed_result_menu' => '']),
            'invalid' => array_replace($original, ['schema_version' => 1, 'passed_result_menu' => 'obsolete']),
            'static ignores injected choice' => ['schema_version' => 1, 'has_workflow' => false, 'stages' => [], 'passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS],
        ];
        $entries = [];
        foreach ($snapshots as $label => $snapshot) {
            $id = DB::table('custom_form_entries')->insertGetId([
                'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'passed',
                'workflow_snapshot' => $snapshot === null ? null : json_encode($snapshot),
                'data' => json_encode(['candidate_status' => 'passed', 'registration_status' => 'passed']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $entries[$label] = CustomFormEntry::query()->findOrFail($id);
            $this->assertRoutes($entries[$label], PassedResultMenuOptions::EXAM_RESULTS);
        }
        $before = collect($entries)->map(fn ($entry): array => $this->storedEntry($entry))->all();
        $stages = $workflow->stages;
        $stages[2]['data']['passed_result_menu'] = PassedResultMenuOptions::EXAM_RESULTS;
        $workflow->update(['stages' => $stages]);
        $form->update(['passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS]);
        foreach ($entries as $label => $entry) {
            $entry->refresh()->unsetRelation('customForm');
            $this->assertSame(PassedResultMenuOptions::EXIT_EXAM_RESULTS, WorkflowPassedResultRouting::forEntry($entry), $label);
            $this->assertRoutes($entry, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
            $this->assertSame($before[$label], $this->storedEntry($entry), $label);
        }
    }

    public function test_explicit_routing_preserves_passed_release_active_profile_and_hidden_filters(): void
    {
        [$form] = $this->workflow(PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $entry = $this->readyEntry($form);
        $this->assertRoutes($entry, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $original = $entry->fresh()->data;
        foreach ([
            array_replace_recursive($original, ['candidate_status' => 'pending']),
            array_replace_recursive($original, ['workflow_progress' => ['ready_for_final' => false, 'finished' => false]]),
        ] as $blocked) {
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['data' => json_encode($blocked)]);
            $this->assertRoutes($entry, null);
        }
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['data' => json_encode($original)]);
        $form->update(['is_active' => false]);
        $this->assertRoutes($entry, null);
        $form->update(['is_active' => true, 'slug' => 'profile']);
        $this->assertRoutes($entry, null);
        $form->update(['slug' => 'restored-results-form']);
        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'data' => json_encode(array_replace($original, [ExitExamResultResource::HIDDEN_FLAG => true])),
        ]);
        $this->assertFalse(ExitExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(ExamResultsTable::applyPassedResultMenuFilter(CustomFormEntry::query(), PassedResultMenuOptions::EXIT_EXAM_RESULTS, ExitExamResultResource::HIDDEN_FLAG)->whereKey($entry->id)->exists());
        $this->assertTrue(ExamResultsTable::applyPassedResultMenuFilter(CustomFormEntry::query(), PassedResultMenuOptions::EXIT_EXAM_RESULTS)->whereKey($entry->id)->exists());
    }

    private function workflow(?string $menu, string $formMenu = PassedResultMenuOptions::EXAM_RESULTS, ?array $order = null): array
    {
        $form = CustomForm::query()->create([
            'name' => 'Result routing', 'slug' => 'result-routing-'.uniqid(), 'is_active' => true,
            'passed_result_menu' => $formMenu, 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'requires_payment' => false, 'allowed_roles' => ['admin'],
        ]);
        $order ??= [WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed];
        $stages = array_map(fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['admin'],
            'notification_channels' => $type === WorkflowStageType::Completed ? ['system'] : [],
            'notification_message' => $type === WorkflowStageType::Completed ? 'Final result release' : '',
            ...($type === WorkflowStageType::AwaitingResults ? ['statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS] : []),
            ...($type === WorkflowStageType::Completed && $menu !== null ? ['passed_result_menu' => $menu] : []),
        ]], $order);
        $workflow = WorkflowNotification::query()->create(['name' => 'Result stages', 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function readyEntry(CustomForm $form): CustomFormEntry
    {
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        WorkflowStageProgression::passed($entry);

        return $entry->fresh();
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending'],
        ]);
    }

    private function assertRoutes(CustomFormEntry $entry, ?string $menu): void
    {
        $this->assertSame($menu === PassedResultMenuOptions::EXAM_RESULTS, ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertSame($menu === PassedResultMenuOptions::EXIT_EXAM_RESULTS, ExitExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        foreach (array_keys(PassedResultMenuOptions::options()) as $target) {
            $this->assertSame($menu === $target, ExamResultsTable::applyPassedResultMenuFilter(CustomFormEntry::query(), $target)->whereKey($entry->id)->exists());
        }
    }

    private function storedEntry(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function user(string $type): User
    {
        $name = 'results_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
