<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\CandidateExitStatistic;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidatePaymentRecorder;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
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

class WorkflowStatisticsRoutingTest extends TestCase
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
            'entrance overrides exit' => [StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, StatisticsMenuOptions::EXIT_EXAM_STATISTICS],
            'exit overrides entrance' => [StatisticsMenuOptions::EXIT_EXAM_STATISTICS, StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS],
        ];
    }

    #[DataProvider('locales')]
    public function test_native_results_selector_is_required_validated_and_replaces_only_the_workflow_form_control(string $locale): void
    {
        app()->setLocale($locale);
        [$form, $workflow] = $this->workflow(null);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $keys = array_keys($component->get('data.stages'));
        $path = 'data.stages.'.$keys[1].'.data.statistics_menu';
        $field = collect($component->instance()->form->getFlatFields())
            ->first(fn ($field): bool => $field->getStatePath() === $path);
        $this->assertNotNull($field);
        $this->assertTrue($field->isVisible());
        $this->assertTrue($field->isRequired());
        $this->assertSame(StatisticsMenuOptions::options(), $field->getOptions());
        $submissionField = collect($component->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.stages.'.$keys[0].'.data.statistics_menu');
        $this->assertNotNull($submissionField);
        $this->assertFalse($submissionField->isVisible());
        $this->assertFalse($submissionField->isRequired());

        $component->set($path, StatisticsMenuOptions::EXIT_EXAM_STATISTICS)->call('save')->assertHasNoFormErrors();
        $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, data_get($workflow->fresh()->stages, '1.data.statistics_menu'));
        $this->assertSame(StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, $form->fresh()->statistics_menu);
        $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, WorkflowStatisticsRouting::forForm($form->fresh()));

        $component->set($path, 'unrecognized_statistics')->call('save')->assertHasErrors([$path]);
        $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, data_get($workflow->fresh()->stages, '1.data.statistics_menu'));
        $component->set($path, null)->call('save')->assertHasErrors([$path => 'required']);
        $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, data_get($workflow->fresh()->stages, '1.data.statistics_menu'));
        $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, WorkflowEntrySnapshot::forForm($form->id)['statistics_menu']);

        $formComponent = Livewire::test(CreateCustomForm::class);
        $staticField = collect($formComponent->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.statistics_menu');
        $this->assertTrue($staticField->isVisible());
        $formComponent->set('data.workflow_notification_id', $workflow->id);
        $staticField = collect($formComponent->instance()->form->getFlatFields(withHidden: true))
            ->first(fn ($field): bool => $field->getStatePath() === 'data.statistics_menu');
        $this->assertFalse($staticField->isVisible());
        $this->assertFalse($staticField->isDehydrated());

        $this->assertNull(WorkflowStatisticsRouting::forStages([
            ['type' => 'stage', 'data' => ['stage_type' => 'review', 'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS]],
        ]));
    }

    #[DataProvider('menus')]
    public function test_stage_route_overrides_the_form_without_bypassing_review_or_payment(string $menu, string $opposite): void
    {
        [$form] = $this->workflow($menu, $opposite, [
            WorkflowStageType::FormSubmission, WorkflowStageType::Review, WorkflowStageType::Payment,
            WorkflowStageType::AwaitingResults, WorkflowStageType::Completed,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertSame($menu, $entry->fresh()->workflow_snapshot['statistics_menu']);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertRoutes($entry, null);

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertRoutes($entry, null);

        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'ROUTING-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertRoutes($entry, $menu);
        $before = $this->storedEntry($entry);
        CandidateStatisticsSynchronizer::syncEntry($entry->fresh());
        CandidateStatisticsSynchronizer::syncEntry($entry->fresh());
        $this->assertSame($before, $this->storedEntry($entry));
        $this->assertSame($menu === StatisticsMenuOptions::EXIT_EXAM_STATISTICS ? 1 : 0,
            CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->id)->count());
        $this->assertTrue(WorkflowStatisticsRouting::apply(CustomFormEntry::query(), $menu)->whereKey($entry->id)->exists());
        $this->assertFalse(WorkflowStatisticsRouting::apply(CustomFormEntry::query(), $opposite)->whereKey($entry->id)->exists());
    }

    public function test_results_first_order_still_enters_selected_statistics_before_review_and_payment(): void
    {
        [$form] = $this->workflow(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, order: [
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults,
            WorkflowStageType::Review, WorkflowStageType::Payment,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertRoutes($entry, StatisticsMenuOptions::EXIT_EXAM_STATISTICS);
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_new_explicit_routes_are_frozen_across_template_form_and_assignment_changes(): void
    {
        [$form, $workflow] = $this->workflow(StatisticsMenuOptions::EXIT_EXAM_STATISTICS);
        $old = $this->entry($form);
        WorkflowStageProgression::submitted($old);
        $oldRow = $this->storedEntry($old);
        $exitRowId = CandidateExitStatistic::query()->where('custom_form_entry_id', $old->id)->value('id');
        $this->assertNotNull($exitRowId);

        $stages = $workflow->stages;
        $stages[1]['data']['statistics_menu'] = StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS;
        $workflow->update(['stages' => $stages]);
        $new = $this->entry($form);
        WorkflowStageProgression::submitted($new);
        $newRow = $this->storedEntry($new);
        $this->assertRoutes($old, StatisticsMenuOptions::EXIT_EXAM_STATISTICS);
        $this->assertRoutes($new, StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS);

        $form->update(['statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS]);
        unset($stages[1]['data']['statistics_menu']);
        $workflow->update(['stages' => $stages]);
        $this->assertArrayNotHasKey('statistics_menu', WorkflowEntrySnapshot::forForm($form->id));
        $replacement = WorkflowNotification::query()->create(['name' => 'Replacement workflow', 'stages' => $stages]);
        $workflow->forms()->detach($form);
        $replacement->forms()->attach($form);
        $workflow->delete();
        CandidateStatisticsSynchronizer::syncForm($form->fresh());

        $this->assertRoutes($old, StatisticsMenuOptions::EXIT_EXAM_STATISTICS);
        $this->assertRoutes($new, StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS);
        $this->assertSame($exitRowId, CandidateExitStatistic::query()->where('custom_form_entry_id', $old->id)->value('id'));
        $this->assertSame($oldRow, $this->storedEntry($old));
        $this->assertSame($newRow, $this->storedEntry($new));
    }

    public function test_older_and_blank_selector_applications_keep_the_existing_live_form_fallback(): void
    {
        [$form, $workflow] = $this->workflow(null);
        $original = WorkflowEntrySnapshot::forForm($form->id);
        $this->assertArrayNotHasKey('statistics_menu', $original);
        $snapshots = [
            'older snapshot' => array_replace($original, ['schema_version' => 1]),
            'no snapshot' => null,
            'static snapshot' => ['schema_version' => 1, 'has_workflow' => false, 'requires_payment' => false, 'stages' => []],
            'blank choice' => array_replace($original, ['schema_version' => 1, 'statistics_menu' => '']),
            'invalid old choice' => array_replace($original, ['schema_version' => 1, 'statistics_menu' => 'obsolete']),
            'static ignores injected route' => ['schema_version' => 1, 'has_workflow' => false, 'requires_payment' => false, 'stages' => [], 'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS],
        ];
        $entries = [];
        foreach ($snapshots as $label => $snapshot) {
            $id = DB::table('custom_form_entries')->insertGetId([
                'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'approved',
                'workflow_snapshot' => $snapshot === null ? null : json_encode($snapshot),
                'data' => json_encode(['candidate_status' => 'pending', 'registration_status' => 'approved']),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $entries[$label] = CustomFormEntry::query()->findOrFail($id);
            $this->assertRoutes($entries[$label], StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS);
        }
        $before = collect($entries)->map(fn ($entry): array => $this->storedEntry($entry))->all();

        $stages = $workflow->stages;
        $stages[1]['data']['statistics_menu'] = StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS;
        $workflow->update(['stages' => $stages]);
        $form->update(['statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS]);
        foreach ($entries as $label => $entry) {
            $entry->refresh()->unsetRelation('customForm');
            $this->assertSame(StatisticsMenuOptions::EXIT_EXAM_STATISTICS, WorkflowStatisticsRouting::forEntry($entry), $label);
            $this->assertRoutes($entry, StatisticsMenuOptions::EXIT_EXAM_STATISTICS);
            $this->assertSame($before[$label], $this->storedEntry($entry), $label);
        }
    }

    private function workflow(?string $menu, string $formMenu = StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, ?array $order = null): array
    {
        $form = CustomForm::query()->create([
            'name' => 'Statistics routing', 'slug' => 'statistics-routing-'.uniqid(), 'is_active' => true,
            'statistics_menu' => $formMenu, 'requires_payment' => false, 'allowed_roles' => ['admin'],
        ]);
        $order ??= [WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed];
        $stages = array_map(fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['admin'],
            'payment_source' => 'system', 'notification_channels' => [],
            ...($type === WorkflowStageType::AwaitingResults && $menu !== null ? ['statistics_menu' => $menu] : []),
            ...($type === WorkflowStageType::Completed ? ['passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS] : []),
        ]], $order);
        $workflow = WorkflowNotification::query()->create(['name' => 'Statistics stages', 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'major' => 'Medicine'],
        ]);
    }

    private function assertRoutes(CustomFormEntry $entry, ?string $menu): void
    {
        $this->assertSame($menu === StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            CandidateEntranceStatisticResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertSame($menu === StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
            CandidateExitStatisticResource::getEloquentQuery()->where('custom_form_entry_id', $entry->id)->exists());
    }

    private function storedEntry(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function user(string $type): User
    {
        $name = 'statistics_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
