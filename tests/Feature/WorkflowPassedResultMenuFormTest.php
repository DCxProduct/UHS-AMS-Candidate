<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowPassedResultMenuFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Http::fake();
        Mail::fake();
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Workflow menu administrator',
            'username' => 'workflow-menu-admin',
            'email' => 'workflow-menu-admin@example.test',
            'password' => 'password',
            'is_active' => true,
            'date_of_birth' => '2000-01-01',
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public static function configurations(): array
    {
        return [
            'create in English' => ['en', false],
            'create in Khmer' => ['km', false],
            'edit in English' => ['en', true],
            'edit in Khmer' => ['km', true],
        ];
    }

    #[DataProvider('configurations')]
    public function test_correcting_an_appended_completed_stage_menu_clears_its_error_and_preserves_the_selected_routing(string $locale, bool $editing): void
    {
        app()->setLocale($locale);
        $name = 'Appended result menu '.$locale.' '.(int) $editing;
        $baseStages = $this->stagesBeforeCompleted();
        $workflow = $editing ? WorkflowNotification::query()->create(['name' => $name, 'stages' => $baseStages]) : null;
        $component = Livewire::test(
            $editing ? EditWorkflowNotification::class : CreateWorkflowNotification::class,
            $editing ? ['record' => $workflow->getRouteKey()] : [],
        );

        if (! $editing) {
            $component->set('data.stages', [])->fillForm(['name' => $name, 'stages' => $baseStages]);
        }

        $component->callAction(TestAction::make('addStage')->schemaComponent('stages-section', schema: 'form'));
        $this->assertCount(7, $component->get('data.stages'));
        $key = array_key_last($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $appendedState = $component->get($path);
        // Alpine must be able to entangle these paths before a hidden selector is revealed.
        $this->assertArrayHasKey('passed_result_menu', $appendedState);
        $this->assertNull($appendedState['passed_result_menu']);
        $this->assertArrayHasKey('statistics_menu', $appendedState);
        $this->assertNull($appendedState['statistics_menu']);
        $this->assertSame('system', $appendedState['payment_source']);
        $completed = WorkflowStageType::selectionForBehavior('completed');

        $component->set($path.'.stage_type_id', $completed)
            ->assertSet($path.'.stage_type', 'completed')
            ->set($path.'.responsible_role', ['admin'])
            ->set($path.'.status_message', 'Passed')
            ->set($path.'.notification_message', 'Passed result notification')
            ->call($editing ? 'save' : 'create')
            ->assertHasErrors([$path.'.passed_result_menu' => 'required'])
            ->set($path.'.passed_result_menu', PassedResultMenuOptions::EXAM_RESULTS)
            ->assertSet($path.'.passed_result_menu', PassedResultMenuOptions::EXAM_RESULTS)
            ->assertHasNoErrors([$path.'.passed_result_menu'])
            ->call($editing ? 'save' : 'create')
            ->assertHasNoFormErrors();

        $saved = WorkflowNotification::query()->where('name', $name)->sole();
        $this->assertCount(7, $saved->stages);
        $this->assertSame(PassedResultMenuOptions::EXAM_RESULTS, data_get($saved->stages, '6.data.passed_result_menu'));

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $saved->getRouteKey()]);
        $key = array_key_last($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->callAction(TestAction::make('moveUp')
            ->schemaComponent('stages-section.stages', schema: 'form')
            ->arguments(['item' => $key]))
            ->assertSet($path.'.passed_result_menu', PassedResultMenuOptions::EXAM_RESULTS)
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('completed', data_get($saved->fresh()->stages, '5.data.stage_type'));
        $this->assertSame(PassedResultMenuOptions::EXAM_RESULTS, data_get($saved->fresh()->stages, '5.data.passed_result_menu'));

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $saved->getRouteKey()]);
        $key = array_keys($component->get('data.stages'))[5];
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', null)
            ->assertSet($path.'.passed_result_menu', null)
            ->set($path.'.stage_type_id', $completed)
            ->assertSet($path.'.stage_type', 'completed')
            ->set($path.'.passed_result_menu', PassedResultMenuOptions::EXIT_EXAM_RESULTS)
            ->set($path.'.responsible_role', ['admin'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(PassedResultMenuOptions::EXIT_EXAM_RESULTS, data_get($saved->fresh()->stages, '5.data.passed_result_menu'));
    }

    private function stagesBeforeCompleted(): array
    {
        return array_map(fn (string $type): array => ['type' => 'stage', 'data' => [
            'stage_name' => 'Existing '.$type,
            'stage_type' => $type,
            'responsible_role' => ['admin'],
            'notification_channels' => ['system'],
            'payment_source' => 'system',
            ...($type === 'awaiting_results' ? ['statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS] : []),
        ]], ['form_submission', 'review', 'payment', 'candidate_id_generation', 'verification', 'awaiting_results']);
    }
}
