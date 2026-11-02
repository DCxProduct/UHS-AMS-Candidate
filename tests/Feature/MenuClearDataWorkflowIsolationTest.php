<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\AmsPaymentRequest;
use App\Models\CandidateExitStatistic;
use App\Models\Major;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidateIdGenerator;
use App\Services\CandidatePaymentRecorder;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MenuClearDataWorkflowIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        (new ReflectionProperty(CustomForm::class, 'profileFormIdCache'))->setValue(null, []);
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        config(['services.plasgate.private_key' => null, 'services.plasgate.secret' => null]);
        $this->staff = $this->user('admin', 'Menu administrator');
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->candidate = $this->user('student', 'Menu candidate');
        $this->actingAs($this->staff);
    }

    public static function menus(): array
    {
        return array_map(fn (string $menu): array => [$menu], [
            'entrance_statistics', 'exit_statistics', 'passed_entrance', 'passed_exit', 'payment_status', 'applications',
        ]);
    }

    #[DataProvider('menus')]
    public function test_clear_only_hides_the_clicked_menu_and_preserves_all_application_payment_workflow_and_other_menu_data(string $menu): void
    {
        [$form, $entry, $payment] = $this->fixture($menu, 'CLEAR');
        [, $other, $otherPayment] = $this->fixture($menu, 'KEEP', $form);
        $before = $this->capture($entry, $payment);
        $otherBefore = $this->capture($other, $otherPayment);
        $dashboardBefore = $this->dashboardTotals();
        $archiveBefore = DB::table('cashier_dashboard_totals')->get()->map(fn (object $row): array => (array) $row)->all();
        $record = $this->menuRecord($menu, $entry);
        $this->assertOtherMenuVisibility($menu, $entry, $payment, true);
        $component = $this->list($menu, $form)->assertCanSeeTableRecords([$record]);
        $this->assertTrue($component->instance()->getAction('clear_data')->isConfirmationRequired());
        $component->set('selectedTableRecords', [$record->id])->call('clearDataFromTableSelection', [$record->id])
            ->assertCanNotSeeTableRecords([$record])->assertCanSeeTableRecords([$this->menuRecord($menu, $other)])
            ->assertSet('selectedTableRecords', [])->assertSet('deselectedTableRecords', [])->assertSet('isTrackingDeselectedTableRecords', false);
        $this->assertPreserved($menu, $entry, $payment, $before);
        $this->assertSame($otherBefore, $this->capture($other, $otherPayment));
        $this->assertOtherMenuVisibility($menu, $entry, $payment, false);
        $expectedDashboard = $dashboardBefore;
        if ($menu === 'payment_status') {
            $expectedDashboard[0] = (string) ((int) $expectedDashboard[0] - 1);
        }
        $this->assertSame($expectedDashboard, $this->dashboardTotals());
        $this->assertSame($archiveBefore, DB::table('cashier_dashboard_totals')->get()->map(fn (object $row): array => (array) $row)->all());
        $this->assertSame('Successful result', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('menus')]
    public function test_selected_all_minus_exclusions_and_filtered_clears_do_not_change_outside_records_or_their_business_data(string $menu): void
    {
        [$form, $first, $firstPayment] = $this->fixture($menu, 'MATCH_ONE');
        [, $excluded, $excludedPayment] = $this->fixture($menu, 'MATCH_TWO', $form);
        [, $outside, $outsidePayment] = $this->fixture($menu, 'OUTSIDE', $form);
        $filterValue = null;
        if ($menu === 'applications') {
            $filterValue = 2025;
            foreach ([$first, $excluded] as $entry) {
                $entry->forceFill(['created_at' => '2025-01-01 12:00:00'])->saveQuietly();
            }
        }
        $firstRecord = $this->menuRecord($menu, $first);
        $excludedRecord = $this->menuRecord($menu, $excluded);
        $outsideRecord = $this->menuRecord($menu, $outside);
        $firstBefore = $this->capture($first, $firstPayment);
        $excludedBefore = $this->capture($excluded, $excludedPayment);
        $outsideBefore = $this->capture($outside, $outsidePayment);
        $component = $this->list($menu, $form);
        $this->filterList($component, $menu, true, $filterValue)->assertCanSeeTableRecords([$firstRecord, $excludedRecord])->assertCanNotSeeTableRecords([$outsideRecord]);
        $component->call('clearDataFromTableSelection', [$outsideRecord->id]);
        $this->assertSame($outsideBefore, $this->capture($outside, $outsidePayment));
        $component->call('clearDataFromTableSelection', [], true, [$excludedRecord->id]);
        $this->assertPreserved($menu, $first, $firstPayment, $firstBefore);
        $this->assertSame($excludedBefore, $this->capture($excluded, $excludedPayment));
        $this->assertSame($outsideBefore, $this->capture($outside, $outsidePayment));
        $this->filterList($component, $menu, true, $filterValue)->call('clearDataFromTableSelection');
        $this->assertPreserved($menu, $excluded, $excludedPayment, $excludedBefore);
        $this->assertSame($outsideBefore, $this->capture($outside, $outsidePayment));
        $this->assertSame(3, Payment::query()->where('status_payt', 'paid')->count());
    }

    #[DataProvider('menus')]
    public function test_direct_clear_calls_require_the_own_menu_permission_and_preserve_business_records(string $menu): void
    {
        [$form, $entry, $payment] = $this->fixture($menu, 'PERMISSION');
        $before = $this->capture($entry, $payment);
        $viewer = $this->user('admin', 'View only staff');
        $role = Role::findOrCreate('registrar_officer', 'web');
        foreach (['CustomFormEntry', 'CandidateEntranceStatistic', 'CandidateExitStatistic', 'ExamResult', 'ExitExamResult', 'PaymentStatusList'] as $model) {
            $role->givePermissionTo(Permission::findOrCreate('ViewAny:'.$model, 'web'));
        }
        $viewer->assignRole($role);
        $this->actingAs($viewer);
        $this->list($menu, $form)->assertActionHidden('clear_data')->call('clearDataFromTableSelection', [$this->menuRecord($menu, $entry)->id])->assertForbidden();
        $this->assertSame($before, $this->capture($entry, $payment));
    }

    #[DataProvider('menus')]
    public function test_repeating_clear_all_never_deletes_or_retimestamps_records_already_hidden_from_that_menu(string $menu): void
    {
        [$form, $entry, $payment] = $this->fixture($menu, 'REPEAT');
        $record = $this->menuRecord($menu, $entry);
        $component = $this->list($menu, $form)->call('clearDataFromTableSelection', [$record->id]);
        $after = $this->capture($entry, $payment);
        $this->travel(1)->hour();
        $component->call('clearDataFromTableSelection')->call('clearDataFromTableSelection', [$record->id]);
        $this->assertSame($after, $this->capture($entry, $payment));
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status_payt' => 'paid']);
    }

    public function test_stale_application_edits_preserve_each_menu_deletion_timestamp_and_workflow(): void
    {
        [$form, $entry, $payment] = $this->fixture('applications', 'STALE');
        $stale = $entry->fresh();
        $snapshot = $stale->workflow_snapshot;
        $progress = data_get($stale->data, 'workflow_progress');
        foreach (['entrance_statistics', 'passed_entrance', 'payment_status', 'applications'] as $menu) {
            $this->list($menu, $form)->call('clearDataFromTableSelection', [$entry->id]);
        }
        $stale->update(['data' => [...$stale->data, 'full_name' => 'Edited answer']]);
        $entry->refresh();
        foreach (['entrance_statistics_deleted_at', 'passed_entrance_deleted_at', 'payment_status_lists_deleted_at', 'form_entries_deleted_at'] as $column) {
            $this->assertNotNull(DB::table('custom_form_entries')->where('id', $entry->id)->value($column));
        }
        $this->assertSame($snapshot, $entry->workflow_snapshot);
        $this->assertSame($progress, data_get($entry->data, 'workflow_progress'));
        $this->assertSame('Edited answer', data_get($entry->data, 'full_name'));
        $this->assertSame('paid', $payment->fresh()->status_payt);
        $this->assertNotNull(CustomFormEntryResource::resolveRecordRouteBinding($entry->id));
    }

    public function test_clearing_a_form_menu_does_not_reset_candidate_number_sequences_or_assignments(): void
    {
        [$form, $entry, $payment] = $this->fixture('applications', 'NUMBERED');
        $major = Major::query()->create(['key' => 'clear_id', 'abbreviation' => 'BNM', 'name_en' => 'Nursing', 'is_active' => true]);
        $entry->update(['data' => [...$entry->data, 'major' => $major->id]]);
        $generator = app(CandidateIdGenerator::class);
        $this->assertSame('BNM-0001', $generator->assignToEntry($entry));
        $sequenceBefore = DB::table('candidate_id_sequences')->where('custom_form_id', $form->id)->get()->map(fn (object $row): array => (array) $row)->all();
        $accountIdsBefore = DB::table('candidate_account_ids')->where('custom_form_id', $form->id)->get()->map(fn (object $row): array => (array) $row)->all();
        $this->list('applications', $form)->call('clearDataFromTableSelection', [$entry->id]);
        $this->assertSame($sequenceBefore, DB::table('candidate_id_sequences')->where('custom_form_id', $form->id)->get()->map(fn (object $row): array => (array) $row)->all());
        $this->assertSame($accountIdsBefore, DB::table('candidate_account_ids')->where('custom_form_id', $form->id)->get()->map(fn (object $row): array => (array) $row)->all());
        $this->assertSame('BNM-0001', $entry->fresh()->candidate_id);
        $this->assertSame('BNM-0002', $generator->next($major->id, now()->year, $form->id));
        $this->assertSame('paid', $payment->fresh()->status_payt);
    }

    private function fixture(string $menu, string $name, ?CustomForm $form = null): array
    {
        $isExit = in_array($menu, ['exit_statistics', 'passed_exit'], true);
        if ($form === null) {
            $form = CustomForm::query()->create([
                'name' => 'Clear isolation application', 'slug' => 'clear-isolation-'.uniqid(), 'is_active' => true, 'requires_payment' => true,
                'allowed_roles' => ['candidate', 'admin', 'registrar_officer'],
                'statistics_menu' => $isExit ? StatisticsMenuOptions::EXIT_EXAM_STATISTICS : StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
                'passed_result_menu' => $isExit ? PassedResultMenuOptions::EXIT_EXAM_RESULTS : PassedResultMenuOptions::EXAM_RESULTS,
            ]);
            $form->fields()->create(['name' => 'full_name', 'label' => 'Full name', 'type' => 'text_input']);
            $workflow = WorkflowNotification::query()->create(['name' => 'Menu isolation workflow', 'stages' => collect([
                WorkflowStageType::Payment, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed,
            ])->map(fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
                'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['admin'],
                'payment_source' => 'system', 'notification_channels' => ['system'],
                'status_message' => $type === WorkflowStageType::Completed ? 'Successful result' : 'Configured stage status',
                'notification_message' => $type === WorkflowStageType::Completed ? 'Released successful candidate result' : null,
            ]])->all()]);
            $workflow->forms()->attach($form);
        }
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'full_name' => $name,
                'first_name_kh' => $name, 'last_name_kh' => 'Candidate', 'first_name_en' => $name, 'last_name_en' => 'CANDIDATE', 'date_of_birth' => '2000-01-01'],
        ]);
        $payment = app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'MENU-PAID-'.$entry->id, 'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertTrue(WorkflowStageProgression::passed($entry));
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        CandidateStatisticsSynchronizer::syncEntry($entry->fresh());
        AmsPaymentRequest::query()->create(['custom_form_entry_id' => $entry->id, 'ams_request_id' => 'CLEAR-AMS-'.$entry->id, 'sync_status' => 'synced', 'local_payment_id' => $payment->id]);

        return [$form, $entry->fresh(), $payment->fresh()];
    }

    private function list(string $menu, CustomForm $form)
    {
        $parameters = $menu === 'applications' ? ['form_id' => $form->id] : [];

        return Livewire::withQueryParams($parameters)->test($this->listClass($menu));
    }

    private function listClass(string $menu): string
    {
        return match ($menu) {
            'entrance_statistics' => ListCandidateEntranceStatistics::class,
            'exit_statistics' => ListCandidateExitStatistics::class,
            'passed_entrance' => ListExamResults::class,
            'passed_exit' => ListExitExamResults::class,
            'payment_status' => ListPaymentStatusLists::class,
            'applications' => ListCustomFormEntries::class,
        };
    }

    private function filterList($component, string $menu, bool $enabled, ?int $majorId)
    {
        return $menu === 'applications'
            ? $component->set('tableFilters.application_review_filters.reviewed_year', $enabled ? (string) $majorId : null)
            : $component->searchTable($enabled ? 'MATCH' : '');
    }

    private function menuRecord(string $menu, CustomFormEntry $entry)
    {
        return $menu === 'exit_statistics' ? CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->id)->sole() : $entry;
    }

    private function capture(CustomFormEntry $entry, Payment $payment): array
    {
        return [
            'entry' => $entry->fresh()->getAttributes(), 'data' => $entry->fresh()->data, 'payment' => $payment->fresh()->getAttributes(),
            'projection' => CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->id)->first()?->getAttributes(),
            'notifications' => $this->candidate->notifications()->where('data->viewData->workflow_entry_id', (string) $entry->id)->orderBy('id')->get()->map->getAttributes()->all(),
            'ams' => AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->id)->first()?->getAttributes(),
            'deliveries' => DB::table('workflow_notification_deliveries')->where('custom_form_entry_id', $entry->id)->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
    }

    private function assertPreserved(string $menu, CustomFormEntry $entry, Payment $payment, array $before): void
    {
        $after = $this->capture($entry, $payment);
        $key = $menu === 'exit_statistics' ? 'projection' : 'entry';
        $column = $this->deletedColumn($menu);
        $this->assertNotNull($after[$key][$column]);
        $this->assertNotNull(DB::table($this->menuRecord($menu, $entry)->getTable())->where('id', $this->menuRecord($menu, $entry)->id)->value($column));
        unset($after[$key][$column], $before[$key][$column]);
        $this->assertSame($before, $after);
    }

    private function assertOtherMenuVisibility(string $clearedMenu, CustomFormEntry $entry, Payment $payment, bool $before): void
    {
        $exit = $entry->customForm->statistics_menu === StatisticsMenuOptions::EXIT_EXAM_STATISTICS;
        $resources = [
            $exit ? 'exit_statistics' : 'entrance_statistics' => $exit ? CandidateExitStatisticResource::class : CandidateEntranceStatisticResource::class,
            $exit ? 'passed_exit' : 'passed_entrance' => $exit ? ExitExamResultResource::class : ExamResultResource::class,
            'payment_status' => PaymentStatusListResource::class,
        ];
        foreach ($resources as $menu => $resource) {
            $record = $this->menuRecord($menu, $entry);
            $this->assertSame($before || $menu !== $clearedMenu, $resource::getEloquentQuery()->whereKey($record->id)->exists(), $menu.' after clearing '.$clearedMenu);
        }
        $this->assertTrue(PaymentResource::getEloquentQuery()->whereKey($payment->id)->exists());
        $list = $this->list('applications', $entry->customForm);
        ! $before && $clearedMenu === 'applications' ? $list->assertCanNotSeeTableRecords([$entry]) : $list->assertCanSeeTableRecords([$entry]);
    }

    private function deletedColumn(?string $menu): ?string
    {
        return match ($menu) {
            'applications' => 'form_entries_deleted_at',
            'payment_status' => 'payment_status_lists_deleted_at',
            'entrance_statistics' => 'entrance_statistics_deleted_at',
            'passed_entrance' => 'passed_entrance_deleted_at',
            'passed_exit' => 'passed_exit_deleted_at',
            'exit_statistics' => 'statistics_deleted_at',
            default => null,
        };
    }

    private function dashboardTotals(): array
    {
        $stats = (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);

        return array_map(fn ($stat): string => (string) $stat->getValue(), $stats);
    }

    private function user(string $type, string $name): User
    {
        $unique = 'menu_clear_'.uniqid();

        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $unique, 'email' => $unique.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }
}
