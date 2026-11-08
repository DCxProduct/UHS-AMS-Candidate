<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\Major;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\WorkflowCandidateVerifier;
use App\Support\FilamentActionPermissions;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowFlexibleStageOrderTest extends TestCase
{
    use RefreshDatabase;

    private const ROLES = ['review' => 'review_officer', 'verification' => 'receipt_officer', 'payment' => 'cashier_officer', 'awaiting_results' => 'results_officer', 'completed' => 'notification_officer'];

    private array $operators = [];

    private User $unassigned;

    private User $candidate;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config(['services.ams.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        foreach (self::ROLES as $type => $role) {
            $this->operators[$type] = $this->operator($role);
        }
        $this->unassigned = $this->operator('unassigned_officer');
        $this->candidate = $this->user('student');
        $this->major = Major::query()->create(['key' => 'flexible-major', 'abbreviation' => 'FLEX', 'name_en' => 'Flexible workflow major', 'is_active' => true]);
        PaymentType::query()->create(['key' => 'cash', 'name_en' => 'Cash', 'method' => 'cash', 'is_active' => true]);
        $this->actingAs($this->operators['verification']);
    }

    public static function configuredOrders(): array
    {
        return [
            'receipt before review' => [['form_submission', 'verification', 'review', 'payment', 'awaiting_results', 'completed']],
            'review before receipt' => [['form_submission', 'review', 'verification', 'payment', 'awaiting_results', 'completed']],
            'notify first and review after results' => [['completed', 'verification', 'payment', 'awaiting_results', 'review', 'form_submission']],
            'payment then results before receipt' => [['payment', 'awaiting_results', 'completed', 'verification', 'review', 'form_submission']],
            'results first and payment after notify' => [['awaiting_results', 'verification', 'completed', 'payment', 'review', 'form_submission']],
        ];
    }

    #[DataProvider('configuredOrders')]
    public function test_native_actions_follow_the_saved_order_and_only_the_current_responsible_role_can_advance(array $order): void
    {
        [$form] = $this->workflow($order);
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertSame($order, array_column(array_column($entry->workflow_snapshot['stages'], 'data'), 'stage_type'));
        $received = false;
        $passed = false;
        $notified = false;
        foreach (array_values(array_diff($order, ['form_submission'])) as $index => $type) {
            $entry->refresh();
            $this->assertSame(WorkflowStageType::from($type), WorkflowStageProgression::currentType($entry), 'The next action must follow the configured order.');
            $this->assertFalse(WorkflowStageProgression::finished($entry));
            $this->assertSame($passed, data_get($entry->data, 'workflow_progress.result_passed'));
            $this->assertSame($received ? 'FLEX-0001' : null, $entry->candidate_id);
            if (! $passed) {
                $this->assertNotSame('passed', data_get($entry->data, 'candidate_status'));
            }
            if (! $notified) {
                $this->assertFalse($this->candidate->notifications()->get()->contains(fn ($notification): bool => data_get($notification->data, 'body') === 'notice:completed'));
            }
            foreach (array_keys(self::ROLES) as $otherType) {
                $this->actingAs($this->operators[$otherType]);
                [$resource, $action] = $this->action($otherType);
                $this->assertSame($type === $otherType, FilamentActionPermissions::canForWorkflowStage($resource, $action, $entry, WorkflowStageType::from($otherType)));
            }

            [$resource, $action, $page] = $this->action($type);
            $this->actingAs($this->unassigned);
            Livewire::test($page)->assertCanSeeTableRecords([$entry])->assertTableActionHidden($action, $entry);
            $this->actingAs($this->operators[$type]);
            $this->assertFalse(FilamentActionPermissions::canForResource($resource, $action));
            $component = Livewire::test($page)->assertCanSeeTableRecords([$entry])->assertTableActionVisible($action, $entry);
            if ($type === 'payment') {
                MefInvoiceCode::query()->create(['mef_code' => 'FLEX'.$entry->id, 'invoice_code' => '120--'.$entry->id]);
                $component->callTableAction($action, $entry, ['receipt_number' => 'FLEX'.$entry->id, 'payment_method' => 'cash', 'amount_usd' => '10.00'])->assertHasNoTableActionErrors();
            } else {
                $component->callTableAction($action, $entry)->assertHasNoTableActionErrors();
            }
            $received = $received || $type === 'verification';
            $passed = $passed || $type === 'awaiting_results';
            $notified = $notified || $type === 'completed';
            $entry->refresh();
            $this->assertSame($received ? 'FLEX-0001' : null, $entry->candidate_id);
            $this->assertSame($passed, data_get($entry->data, 'workflow_progress.result_passed'));
            $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
            $this->assertSame('Original answer', $entry->data['answer']);
            if ($passed) {
                $this->assertSame('passed', data_get($entry->data, 'candidate_status'));
            }
            if ($type === 'completed' && $index < 4) {
                $this->assertFalse(WorkflowStageProgression::finished($entry));
            }
        }

        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertSame('FLEX-0001', $entry->candidate_id);
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseCount('candidate_id_sequences', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $this->candidate->notifications()->get()->filter(fn ($notification): bool => data_get($notification->data, 'body') === 'notice:completed')->count());
        $this->actingAs($this->operators['verification']);
        $before = $this->row($entry);
        $this->assertSame('FLEX-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($before, $this->row($entry));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_template_reordering_and_role_changes_apply_only_to_new_applications(): void
    {
        [$form, $workflow] = $this->workflow(['verification', 'review']);
        $old = $this->entry($form);
        $oldSnapshot = $old->getRawOriginal('workflow_snapshot');
        $stages = $this->stages(['review', 'verification']);
        $stages[1]['data']['responsible_role'] = ['later_receipt_officer'];
        $workflow->update(['stages' => $stages]);
        $new = $this->entry($form);
        $newSnapshot = $new->getRawOriginal('workflow_snapshot');
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($old));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($new));

        $this->actingAs($this->operators['verification']);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $old)->assertTableActionHidden('verify', $new)
            ->callTableAction('verify', $old)->assertHasNoTableActionErrors();
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($old->refresh()));
        $this->assertNull($new->fresh()->candidate_id);
        $this->actingAs($this->operators['review']);
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $new)->assertHasNoTableActionErrors();
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($new->refresh()));
        $this->actingAs($this->operators['verification']);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $new);
        $lateReceiptOfficer = $this->operator('later_receipt_officer');
        $this->actingAs($lateReceiptOfficer);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $new)->callTableAction('verify', $new)->assertHasNoTableActionErrors();
        $this->assertSame('FLEX-0001', $new->refresh()->candidate_id);
        $this->assertTrue(WorkflowStageProgression::finished($new));
        $this->assertSame($oldSnapshot, $old->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame($newSnapshot, $new->getRawOriginal('workflow_snapshot'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($old->refresh()));
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
    }

    public function test_notify_feedback_keeps_the_clicked_stage_when_a_later_submission_checkpoint_runs(): void
    {
        [$form, $workflow] = $this->workflow(['completed', 'form_submission']);
        $stages = $workflow->stages;
        $stages[0]['data']['alert_message'] = 'Clicked notify feedback';
        $stages[1]['data']['alert_message'] = 'Automatic submission feedback';
        $stages[1]['data']['notification_channels'] = ['system'];
        $stages[1]['data']['submission_messages'] = ['candidate_notification_message' => 'Automatic submission notice'];
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry));
        $this->assertDatabaseCount('notifications', 0);
        $this->actingAs($this->operators['completed']);
        Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors()
            ->assertNotified('Clicked notify feedback')->assertNotNotified('Automatic submission feedback');
        $this->assertTrue(WorkflowStageProgression::finished($entry->refresh()));
        $this->assertSame(WorkflowStageType::FormSubmission, WorkflowStageProgression::finalStageType($entry));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertEqualsCanonicalizing(['notice:completed', 'Automatic submission notice'], $this->candidate->notifications()->get()->pluck('data.body')->all());
    }

    public static function completionAroundPayment(): array
    {
        return ['upstream notification' => [true], 'downstream notification' => [false]];
    }

    #[DataProvider('completionAroundPayment')]
    public function test_payment_reversal_preserves_only_the_completed_checkpoints_before_payment(bool $completedFirst): void
    {
        [$form] = $this->workflow($completedFirst ? ['completed', 'payment', 'review'] : ['payment', 'completed', 'review']);
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $completedIndex = $completedFirst ? 0 : 1;
        if ($completedFirst) {
            $this->actingAs($this->operators['completed']);
            Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        }
        $this->actingAs($this->operators['payment']);
        $payment = Payment::query()->create(['custom_form_entry_id' => $entry->id, 'users_id' => $this->candidate->id, 'form_id' => $form->id, 'receipt_number' => 'REVERSAL-'.$entry->id, 'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now()]);
        $this->assertTrue(WorkflowStageProgression::paid($entry));
        if (! $completedFirst) {
            $this->actingAs($this->operators['completed']);
            Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        }
        $entry->refresh();
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $completedAt = WorkflowStageProgression::completedAt($entry, $completedIndex);
        $notifiedAt = data_get($entry->data, 'workflow_progress.completion_notified_at');
        $this->assertNotNull($completedAt);
        $this->assertNotNull($notifiedAt);
        $this->assertSame(1, $this->candidate->notifications()->where('data->body', 'notice:completed')->count());
        $payment->update(['status_payt' => 'return']);
        $this->assertTrue(WorkflowStageProgression::rollbackUnpaidAutoCompletion($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->refresh()));
        $this->assertSame($completedFirst ? $completedAt : null, WorkflowStageProgression::completedAt($entry, $completedIndex));
        $this->assertSame($completedFirst ? $notifiedAt : null, data_get($entry->data, 'workflow_progress.completion_notified_at'));
        if ($completedFirst) {
            $this->assertSame(1, $this->candidate->notifications()->where('data->body', 'notice:completed')->count());
        }

        $this->travel(1)->minutes();
        $payment->update(['status_payt' => 'paid']);
        $this->assertTrue(WorkflowStageProgression::paid($entry));
        $this->actingAs($this->operators['completed']);
        if ($completedFirst) {
            $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->refresh()));
            $this->assertSame($completedAt, WorkflowStageProgression::completedAt($entry, $completedIndex));
            $this->assertSame(1, $this->candidate->notifications()->where('data->body', 'notice:completed')->count());
        } else {
            $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->refresh()));
            Livewire::test(ListExamResults::class)->assertTableActionVisible('notify_student', $entry)->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
            $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->refresh()));
            $this->assertNotSame($completedAt, WorkflowStageProgression::completedAt($entry, $completedIndex));
        }
        $this->travelBack();
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function action(string $type): array
    {
        return match ($type) {
            'review' => [CustomFormEntryResource::class, 'accepted', ListCustomFormEntries::class],
            'verification' => [CustomFormEntryResource::class, 'verify', ListCustomFormEntries::class],
            'payment' => [PaymentStatusListResource::class, 'pay', ListPaymentStatusLists::class],
            'awaiting_results' => [CandidateEntranceStatisticResource::class, 'passed', ListCandidateEntranceStatistics::class],
            'completed' => [ExamResultResource::class, 'notify_student', ListExamResults::class],
        };
    }

    private function workflow(array $order): array
    {
        $form = CustomForm::query()->create(['name' => 'Flexible application', 'slug' => 'flexible-'.uniqid(), 'is_active' => true, 'requires_payment' => true, 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, 'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS]);
        $workflow = WorkflowNotification::query()->create(['name' => 'Flexible workflow '.uniqid(), 'stages' => $this->stages($order)]);
        $workflow->forms()->attach($form);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();

        return [$form, $workflow];
    }

    private function stages(array $order): array
    {
        return array_map(fn (string $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type, 'stage_name' => 'Custom '.$type, 'responsible_role' => isset(self::ROLES[$type]) ? [self::ROLES[$type]] : [],
            'notification_channels' => $type === 'form_submission' ? [] : ['system'], 'notification_message' => 'notice:'.$type,
            'review_actions' => ['accept' => ['notification_message' => 'notice:review']], 'payment_source' => 'system',
            ...($type === 'awaiting_results' ? ['statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS] : []),
            ...($type === 'completed' ? ['passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS] : []),
        ]], $order);
    }

    private function entry(CustomForm $form): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'submitted_at' => now()->toIso8601String(), 'major' => (string) $this->major->id, 'answer' => 'Original answer']])->fresh();
    }

    private function operator(string $role): User
    {
        $user = $this->user('admin');
        $user->assignRole(Role::findOrCreate($role, 'web'));
        foreach (['ViewAny:CustomFormEntry', 'View:CustomFormEntry', 'ViewAny:PaymentStatusList', 'ViewAny:CandidateEntranceStatistic', 'ViewAny:ExamResult'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function user(string $type): User
    {
        $name = 'flexible-'.uniqid();

        return User::query()->forceCreate(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'test-password', 'registration_type' => $type, 'is_active' => true]);
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }
}
