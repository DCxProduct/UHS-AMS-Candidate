<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Models\CandidateExitStatistic;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\CandidateStatisticsSynchronizer;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageProgression;
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
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkflowStatisticsActionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $candidate;

    private Role $role;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->role = Role::findOrCreate('statistics_operator', 'web');
        $this->operator = $this->user('admin');
        $this->operator->assignRole($this->role);
        $this->candidate = $this->user('student');
        $this->actingAs($this->operator);
    }

    public static function menus(): array
    {
        return [['entrance'], ['exit']];
    }

    #[DataProvider('menus')]
    public function test_responsible_role_can_mark_passed_without_a_separate_action_permission(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        [$entry, $row] = $this->entry($menu);
        $snapshot = $entry->workflow_snapshot;

        Livewire::test($page)->assertCanSeeTableRecords([$row])
            ->assertTableActionVisible('passed', $row)
            ->callTableAction('passed', $row)->assertHasNoTableActionErrors();

        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertTrue(data_get($entry->fresh()->data, 'workflow_progress.result_passed'));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertFalse($this->operator->fresh()->can('Passed:'.class_basename($resource::getModel())));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('menus')]
    public function test_mixed_bulk_selection_authorizes_each_frozen_workflow_and_static_row(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        [$allowed, $allowedRow] = $this->entry($menu);
        [$static, $staticRow] = $this->entry($menu, role: null);
        [$wrong, $wrongRow] = $this->entry($menu, role: 'different_statistics_role');

        Livewire::test($page)
            ->callTableBulkAction('bulk_passed', [$allowedRow, $staticRow, $wrongRow])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('passed', data_get($allowed->fresh()->data, 'candidate_status'));
        $this->assertSame('pending', data_get($static->fresh()->data, 'candidate_status'));
        $this->assertSame('pending', data_get($wrong->fresh()->data, 'candidate_status'));

        $this->grant($resource, 'BulkPassed');
        Livewire::test($page)->callTableBulkAction('bulk_passed', [$staticRow, $wrongRow])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame('passed', $this->resultStatus($menu, $static));
        $this->assertSame('pending', data_get($wrong->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_static_row_and_bulk_permissions_remain_distinct(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        [$entry, $row] = $this->entry($menu, role: null);
        Livewire::test($page)->assertTableActionHidden('passed', $row)
            ->assertTableBulkActionHidden('bulk_passed');

        $this->grant($resource, 'Passed');
        Livewire::test($page)->assertTableActionVisible('passed', $row)
            ->assertTableBulkActionHidden('bulk_passed')
            ->callTableAction('passed', $row)->assertHasNoTableActionErrors();
        $this->assertSame('passed', $this->resultStatus($menu, $entry));

        Livewire::test($page)->assertTableActionHidden('edit_result', $row)
            ->assertTableBulkActionHidden('bulk_pending');
        $this->grant($resource, 'EditResult');
        Livewire::test($page)->assertTableActionVisible('edit_result', $row)
            ->assertTableBulkActionHidden('bulk_pending')
            ->callTableAction('edit_result', $row)->assertHasNoTableActionErrors();
        $this->assertSame('pending', $this->resultStatus($menu, $entry));
    }

    #[DataProvider('menus')]
    public function test_action_permissions_cannot_override_a_frozen_wrong_role_or_revoked_role(string $menu): void
    {
        [$resource, $page, $table] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        $this->grant($resource, 'Passed');
        [$entry, $row, $workflow] = $this->entry($menu, role: 'different_statistics_role');
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['statistics_operator'];
        $workflow->update(['stages' => $stages]);
        Livewire::test($page)->assertTableActionHidden('passed', $row);
        $this->assertForbiddenChange($table, $row);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));

        [$allowed, $allowedRow] = $this->entry($menu);
        $staleRow = clone $allowedRow;
        $this->operator->removeRole($this->role);
        $this->operator->givePermissionTo(Permission::findOrCreate('ViewAny:'.class_basename($resource::getModel()), 'web'));
        $this->operator->givePermissionTo(Permission::findOrCreate('Passed:'.class_basename($resource::getModel()), 'web'));
        $this->assertForbiddenChange($table, $staleRow);
        $this->assertSame('pending', data_get($allowed->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_candidate_and_missing_base_access_cannot_mutate_results(string $menu): void
    {
        [$resource, , $table] = $this->menu($menu);
        [$entry, $row] = $this->entry($menu);
        $this->assertForbiddenChange($table, $row);
        $this->grant($resource, 'ViewAny');
        $this->candidate->assignRole($this->role);
        $this->actingAs($this->candidate);
        $this->assertForbiddenChange($table, $row);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
    }

    #[DataProvider('menus')]
    public function test_static_bulk_pending_requires_its_own_permission(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        $this->grant($resource, 'EditResult');
        [$entry] = $this->entry($menu, role: null);
        $row = $this->setPassedResult($menu, $entry);
        Livewire::test($page)->assertTableBulkActionHidden('bulk_pending');

        $this->grant($resource, 'BulkPending');
        Livewire::test($page)->callTableBulkAction('bulk_pending', [$row])
            ->assertHasNoTableBulkActionErrors();
        $this->assertSame('pending', $this->resultStatus($menu, $entry));
    }

    #[DataProvider('menus')]
    public function test_legacy_workflow_role_can_edit_an_unannounced_result_without_action_grants(string $menu): void
    {
        [$resource, $page] = $this->menu($menu);
        $this->grant($resource, 'ViewAny');
        [$entry] = $this->entry($menu, legacy: true);
        $row = $this->setPassedResult($menu, $entry);
        $snapshot = $entry->fresh()->workflow_snapshot;

        Livewire::test($page)->assertTableActionVisible('edit_result', $row)
            ->callTableAction('edit_result', $row)->assertHasNoTableActionErrors();
        $this->assertSame('pending', $this->resultStatus($menu, $entry));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertFalse($this->operator->fresh()->can('EditResult:'.class_basename($resource::getModel())));
    }

    private function setPassedResult(string $menu, CustomFormEntry $entry): mixed
    {
        DB::table($entry->getTable())->where('id', $entry->getKey())->update([
            'review_status' => 'passed',
            'data' => json_encode([...$entry->data, 'registration_status' => 'passed', 'candidate_status' => 'passed'], JSON_THROW_ON_ERROR),
        ]);
        CandidateStatisticsSynchronizer::syncEntry($entry->refresh());

        return $menu === 'exit'
            ? CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->getKey())->firstOrFail()
            : $entry;
    }

    private function assertForbiddenChange(string $table, mixed $row): void
    {
        try {
            (new ReflectionMethod($table, 'changeResult'))->invoke(null, $row, 'passed', true);
            $this->fail('The current actor must not be able to change this result.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function menu(string $menu): array
    {
        return $menu === 'exit'
            ? [CandidateExitStatisticResource::class, ListCandidateExitStatistics::class, CandidateExitStatisticsTable::class]
            : [CandidateEntranceStatisticResource::class, ListCandidateEntranceStatistics::class, CandidateEntranceStatisticsTable::class];
    }

    private function entry(string $menu, ?string $role = 'statistics_operator', bool $legacy = false): array
    {
        $target = $menu === 'exit' ? StatisticsMenuOptions::EXIT_EXAM_STATISTICS : StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS;
        $form = CustomForm::query()->create([
            'name' => 'Result authorization '.++$this->sequence, 'slug' => 'result-auth-'.$this->sequence,
            'is_active' => true, 'requires_payment' => false, 'statistics_menu' => $target,
        ]);
        $workflow = null;
        if ($role !== null) {
            Role::findOrCreate($role, 'web');
            $workflow = WorkflowNotification::query()->create([
                'name' => 'Result authorization workflow '.$this->sequence,
                'stages' => [
                    ['type' => 'stage', 'data' => [
                        'stage_name' => 'Results', 'stage_type' => 'awaiting_results',
                        'statistics_menu' => $target, 'responsible_role' => [$role],
                    ]],
                    ['type' => 'stage', 'data' => [
                        'stage_name' => 'Notify', 'stage_type' => 'completed', 'responsible_role' => [$role],
                    ]],
                ],
            ]);
            $workflow->forms()->attach($form);
        }
        $snapshot = WorkflowEntrySnapshot::forForm($form->getKey());
        if ($legacy) {
            $snapshot['schema_version'] = 1;
        }
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved', 'workflow_snapshot' => $snapshot,
            'data' => ['candidate_status' => 'pending', 'registration_status' => 'approved', 'submitted_at' => now()->toIso8601String()],
        ])->saveQuietly();
        if ($role !== null && ! $legacy) {
            WorkflowStageProgression::initialize($entry);
        }
        CandidateStatisticsSynchronizer::syncEntry($entry->fresh());
        $row = $menu === 'exit'
            ? CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->getKey())->firstOrFail()
            : $entry;

        return [$entry->fresh(), $row, $workflow];
    }

    private function resultStatus(string $menu, CustomFormEntry $entry): string
    {
        return $menu === 'exit'
            ? (string) CandidateExitStatistic::query()->where('custom_form_entry_id', $entry->getKey())->value('candidate_status')
            : (string) data_get($entry->fresh()->data, 'candidate_status');
    }

    private function grant(string $resource, string $prefix): void
    {
        $this->role->givePermissionTo(Permission::findOrCreate($prefix.':'.class_basename($resource::getModel()), 'web'));
        $this->operator->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function user(string $registrationType): User
    {
        $number = ++$this->sequence;

        return User::query()->create([
            'name' => 'Statistics actor '.$number, 'username' => 'statistics_actor_'.$number,
            'email' => 'statistics-actor-'.$number.'@example.test', 'registration_type' => $registrationType,
            'password' => bcrypt('password'), 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
