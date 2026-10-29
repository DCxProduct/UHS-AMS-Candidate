<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowPaymentStatusLabelFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Payment workflow administrator',
            'username' => 'payment_workflow_admin',
            'email' => 'payment_workflow_admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('cashier_officer', 'web');
        $this->actingAs($admin);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_payment_stage_clearly_labels_paid_and_unpaid_statuses_without_changing_the_existing_paid_path(string $locale): void
    {
        app()->setLocale($locale);
        $workflow = $this->workflow();
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";
        $fields = collect($component->instance()->form->getFlatFields())
            ->keyBy(fn ($field): string => $field->getStatePath());
        $paid = $fields->get($path.'.status_message');
        $unpaid = $fields->get($path.'.payment_statuses.unpaid_status_message');

        $this->assertNotNull($paid);
        $this->assertNotNull($unpaid);
        $this->assertSame(__('workflow_notifications.fields.paid_status_message'), $paid->getLabel());
        $this->assertSame(__('workflow_notifications.fields.unpaid_status_message'), $unpaid->getLabel());
        $this->assertSame(__('workflow_notifications.placeholders.unpaid_status_message'), (string) $unpaid->getPlaceholder());
        $this->assertSame(__('workflow_notifications.placeholders.stage_audiences.payment.status_message'), (string) $paid->getPlaceholder());
        $this->assertFalse($paid->isRequired());
        $this->assertFalse($unpaid->isRequired());
        $component->assertSet($path.'.status_message', 'Existing paid status')
            ->assertSee(__('workflow_notifications.helpers.paid_status_message'))
            ->assertSee(__('workflow_notifications.helpers.unpaid_status_message'));
        $this->assertEmpty(data_get($workflow->stages, '0.data.payment_statuses.unpaid_status_message'));
    }

    public function test_unpaid_status_field_is_visible_only_for_the_payment_stage(): void
    {
        $stages = [];
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed'] as $type) {
            $stages[] = ['type' => 'stage', 'data' => [
                'stage_name' => $type,
                'stage_type' => $type,
                'responsible_role' => $type === 'form_submission' ? null : ['cashier_officer'],
            ]];
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Payment-specific statuses', 'stages' => $stages]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields())
            ->keyBy(fn ($field): string => $field->getStatePath());
        $unpaidFields = $fields->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.payment_statuses.unpaid_status_message'));
        $this->assertCount(1, $unpaidFields);
        foreach ($component->get('data.stages') as $key => $stage) {
            $this->assertSame($stage['data']['stage_type'] === 'payment', $fields->has("data.stages.{$key}.data.payment_statuses.unpaid_status_message"));
        }
    }

    public function test_both_status_labels_save_and_reopen_independently_while_legacy_settings_are_preserved(): void
    {
        $workflow = $this->workflow();
        $original = $workflow->stages[0]['data'];
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";
        $component->set($path.'.payment_statuses.unpaid_status_message', 'មិនទាន់បង់ប្រាក់')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $workflow->fresh()->stages[0]['data'];
        foreach ($original as $field => $value) {
            $this->assertEquals($value, $saved[$field] ?? null);
        }
        $this->assertSame('មិនទាន់បង់ប្រាក់', data_get($saved, 'payment_statuses.unpaid_status_message'));

        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $reopenedKey = array_key_first($reopened->get('data.stages'));
        $reopenedPath = "data.stages.{$reopenedKey}.data";
        $reopened->assertSet($reopenedPath.'.status_message', 'Existing paid status')
            ->assertSet($reopenedPath.'.payment_statuses.unpaid_status_message', 'មិនទាន់បង់ប្រាក់')
            ->set($reopenedPath.'.status_message', 'បានបង់ប្រាក់រួចរាល់')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', data_get($workflow->fresh()->stages, '0.data.status_message'));
        $this->assertSame('មិនទាន់បង់ប្រាក់', data_get($workflow->fresh()->stages, '0.data.payment_statuses.unpaid_status_message'));
        $this->assertSame($original['notification_message'], data_get($workflow->stages, '0.data.notification_message'));
        $this->assertSame($original['responsible_role'], data_get($workflow->stages, '0.data.responsible_role'));
        $this->assertSame($original['notification_channels'], data_get($workflow->stages, '0.data.notification_channels'));
    }

    public function test_new_and_existing_payment_status_labels_can_be_left_blank(): void
    {
        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Optional payment labels',
                'stages' => ['payment' => ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment',
                    'stage_type' => 'payment',
                    'payment_source' => 'system',
                    'responsible_role' => ['cashier_officer'],
                    'notification_channels' => ['system'],
                ]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->sole();
        $this->assertEmpty(data_get($workflow->stages, '0.data.status_message'));
        $this->assertEmpty(data_get($workflow->stages, '0.data.payment_statuses.unpaid_status_message'));
        $this->assertSame(['cashier_officer'], data_get($workflow->stages, '0.data.responsible_role'));
    }

    public function test_unpaid_label_accepts_255_characters_and_rejects_longer_values(): void
    {
        $workflow = $this->workflow();
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "stages.{$key}.data.payment_statuses.unpaid_status_message";
        $component->set('data.'.$path, str_repeat('a', 256))
            ->call('save')
            ->assertHasFormErrors([$path => 'max']);
        $this->assertEmpty(data_get($workflow->fresh()->stages, '0.data.payment_statuses.unpaid_status_message'));
        $component->set('data.'.$path, str_repeat('a', 255))
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(str_repeat('a', 255), data_get($workflow->fresh()->stages, '0.data.payment_statuses.unpaid_status_message'));
    }

    private function workflow(): WorkflowNotification
    {
        return WorkflowNotification::query()->create([
            'name' => 'Payment status labels',
            'stages' => [['type' => 'stage', 'data' => [
                'stage_name' => 'Payment',
                'stage_type' => 'payment',
                'responsible_role' => ['cashier_officer'],
                'notification_channels' => ['system'],
                'status_message' => 'Existing paid status',
                'notification_message' => 'Existing candidate notification',
            ]]],
        ]);
    }
}
