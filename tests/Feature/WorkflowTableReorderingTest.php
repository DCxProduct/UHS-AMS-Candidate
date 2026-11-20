<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\ListWorkflowNotifications;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Services\WorkflowNotificationDuplicator;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowTableReorderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function catalogs(): array
    {
        return [
            'workflow notifications' => [WorkflowNotification::class, ListWorkflowNotifications::class],
            'stage types' => [WorkflowStageType::class, ListWorkflowStageTypes::class],
            'workflow buttons' => [WorkflowButton::class, ListWorkflowButtons::class],
        ];
    }

    #[DataProvider('catalogs')]
    public function test_updater_can_reorder_and_saved_order_survives_exiting_and_reopening_the_list(string $model, string $page): void
    {
        $this->actor($model, ['ViewAny', 'Update']);
        $this->record($model, 'First');
        $this->record($model, 'Second');
        $records = $model::query()->orderBy('sort_order')->orderBy('id')->get();
        $before = $records->mapWithKeys(fn (Model $record): array => [$record->id => $this->configuration($record)])->all();
        $reordered = $records->reverse()->values();
        $order = $reordered->modelKeys();

        $list = Livewire::test($page);
        $this->assertTrue($list->instance()->getTable()->isReorderable());
        $list->call('toggleTableReordering')
            ->assertSet('isTableReordering', true)
            ->call('reorderTable', $order)
            ->assertCanSeeTableRecords($reordered, inOrder: true)
            ->call('toggleTableReordering')
            ->assertSet('isTableReordering', false)
            ->assertCanSeeTableRecords($reordered, inOrder: true);

        $this->assertSame($order, $model::query()->orderBy('sort_order')->pluck('id')->all());
        Livewire::test($page)->assertCanSeeTableRecords($reordered, inOrder: true);
        foreach ($reordered as $index => $record) {
            $fresh = $record->fresh();
            $this->assertSame($index + 1, $fresh->sort_order);
            $this->assertSame($before[$record->id], $this->configuration($fresh));
        }
    }

    #[DataProvider('catalogs')]
    public function test_view_only_user_cannot_reorder_even_by_calling_the_livewire_method(string $model, string $page): void
    {
        $this->actor($model, ['ViewAny']);
        $this->record($model, 'First');
        $this->record($model, 'Second');
        $before = $model::query()->orderBy('id')->pluck('sort_order', 'id')->all();
        $list = Livewire::test($page);

        $this->assertFalse($list->instance()->getTable()->isReorderable());
        $list->call('toggleTableReordering')->call('reorderTable', array_reverse(array_keys($before)));

        $this->assertFalse($list->instance()->isTableReordering());
        $this->assertSame($before, $model::query()->orderBy('id')->pluck('sort_order', 'id')->all());
    }

    #[DataProvider('catalogs')]
    public function test_new_records_append_after_the_saved_order_and_edits_preserve_their_position(string $model, string $page): void
    {
        $first = $this->record($model, 'First');
        $first->update(['sort_order' => 100]);
        $second = $this->record($model, 'Second');

        $this->assertSame(101, $second->fresh()->sort_order);
        $second->update($model === WorkflowNotification::class ? ['name' => 'Renamed'] : ['label_en' => 'Renamed']);
        $this->assertSame(101, $second->fresh()->sort_order);
        $explicit = $this->record($model, 'Explicit', ['sort_order' => 50]);
        $this->assertSame(50, $explicit->fresh()->sort_order);
    }

    public function test_duplicated_workflow_appends_without_copying_the_source_position_or_changing_stages(): void
    {
        $this->actor(WorkflowNotification::class, ['ViewAny', 'Duplicate']);
        $source = $this->record(WorkflowNotification::class, 'Source', ['sort_order' => 10]);
        $this->record(WorkflowNotification::class, 'Last', ['sort_order' => 20]);

        $copy = app(WorkflowNotificationDuplicator::class)->duplicate($source);

        $this->assertSame(21, $copy->fresh()->sort_order);
        $this->assertSame(10, $source->fresh()->sort_order);
        $this->assertSame($source->stages, $copy->stages);
    }

    private function record(string $model, string $label, array $attributes = []): Model
    {
        $defaults = match ($model) {
            WorkflowNotification::class => ['name' => $label, 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']]]],
            WorkflowStageType::class => ['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => 'review'],
            WorkflowButton::class => ['label_en' => $label, 'action_key' => 'accept'],
        };

        return $model::query()->create(array_merge($defaults, $attributes));
    }

    private function configuration(Model $record): array
    {
        return array_diff_key($record->getRawOriginal(), array_flip(['sort_order', 'updated_at']));
    }

    private function actor(string $model, array $actions): User
    {
        $role = Role::findOrCreate('workflow-order-staff', 'web');
        foreach ($actions as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':'.class_basename($model), 'web'));
        }
        $user = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Workflow order staff', 'username' => 'workflow-order-staff',
            'email' => 'workflow-order-staff@example.test', 'password' => 'password',
            'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }
}
