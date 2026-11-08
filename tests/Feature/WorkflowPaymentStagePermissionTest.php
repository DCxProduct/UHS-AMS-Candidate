<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidatePaymentRecorder;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowPaymentStagePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        config(['services.ams.enabled' => false]);
        $this->candidate = $this->user('student');
        $this->cashier = $this->user('admin', 'cashier_officer');
        $this->grant($this->cashier, 'ViewAny:PaymentStatusList');
        $this->actingAs($this->cashier);
        PaymentType::query()->create([
            'key' => 'cash', 'name_en' => 'Cash', 'method' => 'cash', 'is_active' => true,
        ]);
    }

    public function test_frozen_responsible_cashier_can_record_workflow_payment_without_a_pay_grant(): void
    {
        [$form, $workflow] = $this->workflow();
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $stages = $workflow->stages;
        $stages[0]['data']['responsible_role'] = ['cashier_manager'];
        $workflow->update(['stages' => $stages]);
        $newEntry = $this->entry($form);
        $this->assertFalse($this->cashier->can('Pay:PaymentStatusList'));
        $this->assertFalse($this->cashier->can('View:PaymentStatusList'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry));
        MefInvoiceCode::query()->create(['mef_code' => 'MEF701', 'invoice_code' => '120--701']);

        Livewire::test(ListPaymentStatusLists::class)
            ->assertCanSeeTableRecords([$entry, $newEntry])
            ->assertTableActionVisible('pay', $entry)
            ->assertTableActionHidden('pay', $newEntry)
            ->callTableAction('pay', $entry, data: $this->modalData('MEF701'))
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('payments', [
            'custom_form_entry_id' => $entry->id, 'users_id' => $this->candidate->id,
            'form_id' => $form->id, 'received_by' => $this->cashier->id,
            'receipt_number' => '120--701', 'status_payt' => 'paid',
        ]);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $newEntry->id]);
    }

    public function test_existing_static_application_still_requires_pay_after_a_workflow_is_attached(): void
    {
        $form = $this->form();
        $static = $this->entry($form);
        $this->workflow(form: $form);
        $configured = $this->entry($form);
        $this->assertFalse(WorkflowStageMessages::hasConfiguredWorkflow($static));

        Livewire::test(ListPaymentStatusLists::class)
            ->assertTableActionHidden('pay', $static)
            ->assertTableActionVisible('pay', $configured);
        $this->assertForbiddenPayment($static);
        $this->grant($this->cashier, 'Pay:PaymentStatusList');
        Livewire::test(ListPaymentStatusLists::class)->assertTableActionVisible('pay', $static);
        $payment = app(CandidatePaymentRecorder::class)->record($this->paymentData($static));

        $this->assertSame($static->id, $payment->custom_form_entry_id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertFalse(WorkflowStageMessages::hasConfiguredWorkflow($static->fresh()));
    }

    public function test_pay_grant_cannot_override_wrong_missing_or_noncurrent_workflow_roles(): void
    {
        $this->grant($this->cashier, 'Pay:PaymentStatusList');
        foreach ([['registrar_officer'], []] as $roles) {
            [$form] = $this->workflow($roles);
            $entry = $this->entry($form);
            Livewire::test(ListPaymentStatusLists::class)->assertTableActionHidden('pay', $entry);
            $this->assertForbiddenPayment($entry);
        }
        [$form] = $this->workflow(reviewFirst: true);
        $review = $this->entry($form, 'pending');
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($review));
        $this->assertFalse(PaymentStatusListResource::getEloquentQuery()->whereKey($review->id)->exists());
        $this->assertForbiddenPayment($review);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_base_menu_access_and_staff_account_are_still_required(): void
    {
        [$form] = $this->workflow();
        $entry = $this->entry($form);
        $withoutMenu = $this->user('admin', 'cashier_officer');
        $this->grant($withoutMenu, 'Pay:PaymentStatusList');
        $this->actingAs($withoutMenu);
        Livewire::test(ListPaymentStatusLists::class)->assertForbidden();
        $this->assertForbiddenPayment($entry);

        $this->candidate->assignRole(Role::findOrCreate('cashier_officer', 'web'));
        $this->grant($this->candidate, 'ViewAny:PaymentStatusList', 'Pay:PaymentStatusList');
        $this->actingAs($this->candidate);
        $this->assertForbiddenPayment($entry);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_role_is_rechecked_after_mount_and_inside_the_locked_recorder(): void
    {
        [$form] = $this->workflow();
        $entry = $this->entry($form);
        $before = $entry->getAttributes();
        MefInvoiceCode::query()->create(['mef_code' => 'MEF702', 'invoice_code' => '120--702']);
        $component = Livewire::test(ListPaymentStatusLists::class)->mountTableAction('pay', $entry);
        $this->cashier->syncRoles([Role::findOrCreate('registrar_officer', 'web')]);
        $this->actingAs($this->cashier->fresh());

        $component->setTableActionData($this->modalData('MEF702'))->callMountedTableAction();
        $this->assertForbiddenPayment($entry);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($before, $entry->fresh()->getAttributes());
    }

    public function test_workflow_authorization_does_not_relax_ams_ownership_or_duplicate_guards(): void
    {
        [$amsForm] = $this->workflow(source: 'ams');
        $ams = $this->entry($amsForm);
        $amsRequests = DB::table('ams_payment_requests')->get()->map(fn ($row): array => (array) $row)->all();
        $this->assertFalse(PaymentStatusListResource::getEloquentQuery()->whereKey($ams->id)->exists());
        try {
            app(CandidatePaymentRecorder::class)->record($this->paymentData($ams));
            $this->fail('An AMS application was manually collected.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('workflow_notifications.validation.ams_payment_only')], $exception->errors()['receipt_number']);
        }

        [$form] = $this->workflow();
        $entry = $this->entry($form);
        foreach ([['users_id' => $this->cashier->id], ['form_id' => $amsForm->id]] as $forgery) {
            try {
                app(CandidatePaymentRecorder::class)->record([...$this->paymentData($entry), ...$forgery]);
                $this->fail('Payment ownership was changed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('receipt_number', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payments', 0);
        $payment = app(CandidatePaymentRecorder::class)->record($this->paymentData($entry));
        $this->assertSame($this->candidate->id, $payment->users_id);
        $this->assertForbiddenPayment($entry->fresh());
        $this->assertSame(1, Payment::query()->where('custom_form_entry_id', $entry->id)->count());
        $this->assertSame($amsRequests, DB::table('ams_payment_requests')->get()->map(fn ($row): array => (array) $row)->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_standalone_legacy_receipts_are_unchanged(): void
    {
        auth()->logout();
        $payment = app(CandidatePaymentRecorder::class)->record([
            'receipt_number' => 'STANDALONE-UNCHANGED', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);

        $this->assertNull($payment->custom_form_entry_id);
        $this->assertDatabaseCount('payments', 1);
    }

    private function assertForbiddenPayment(PaymentStatusList $entry): void
    {
        try {
            app(CandidatePaymentRecorder::class)->record($this->paymentData($entry));
            $this->fail('An unauthorized payment was recorded.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function workflow(array $roles = ['cashier_officer'], string $source = 'system', bool $reviewFirst = false, ?CustomForm $form = null): array
    {
        $form ??= $this->form();
        $stages = [];
        if ($reviewFirst) {
            $stages[] = ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer']]];
        }
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Payment', 'stage_type' => 'payment', 'responsible_role' => $roles,
            'payment_source' => $source, 'notification_channels' => [],
        ]];
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Results', 'stage_type' => 'awaiting_results', 'responsible_role' => ['registrar_officer'],
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, 'notification_channels' => [],
        ]];
        $workflow = WorkflowNotification::query()->create(['name' => 'Payment roles '.uniqid(), 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create(['name' => 'Payment authorization form', 'slug' => 'payment-authorization-'.uniqid(), 'is_active' => true, 'requires_payment' => true]);
    }

    private function entry(CustomForm $form, string $status = 'approved'): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status,
            'data' => ['registration_status' => $status, 'candidate_status' => 'pending'],
        ])->fresh();
    }

    private function paymentData(PaymentStatusList $entry): array
    {
        return ['custom_form_entry_id' => $entry->id, 'receipt_number' => 'PAYMENT-'.$entry->id, 'amount_usd' => 5, 'datetime_pay' => now()];
    }

    private function modalData(string $receipt): array
    {
        return ['receipt_number' => $receipt, 'payment_method' => 'cash', 'amount_usd' => '5.00'];
    }

    private function user(string $type, ?string $role = null): User
    {
        $name = 'payment-role-'.uniqid();
        $user = User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name,
            'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user;
    }

    private function grant(User $user, string ...$permissions): void
    {
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
    }
}
