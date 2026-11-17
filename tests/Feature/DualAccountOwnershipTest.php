<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Models\AccountUser;
use App\Models\AuditLog;
use App\Models\NewStudentAccount;
use App\Models\Payment;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsStudentAccountTransfer;
use App\Support\DashboardMetrics;
use App\Support\SystemUsername;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Notifications\Notification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class DualAccountOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.plasgate.private_key' => null, 'services.plasgate.secret' => null]);
        Http::fake();
        Mail::fake();
    }

    public function test_transfer_keeps_application_payment_and_candidate_id_ownership_with_a_different_student_record_id(): void
    {
        NewStudentAccount::query()->create([
            'student_id' => 'UNRELATED', 'first_name_latin' => 'OTHER', 'last_name_latin' => 'RECORD',
        ]);
        [$user, $entry] = $this->application(73);
        $payment = Payment::query()->create([
            'users_id' => $user->id, 'received_by' => $user->id, 'form_id' => $entry->custom_form_id,
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'OWNERSHIP-001',
            'amount_usd' => 10, 'status_payt' => 'paid', 'datetime_pay' => now(),
        ])->fresh();
        DB::table('candidate_account_ids')->insert([
            'custom_form_id' => $entry->custom_form_id, 'user_id' => $user->id,
            'major_abbreviation' => 'MED', 'year_suffix' => 26, 'candidate_id' => 'MED-0001',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $beforeEntry = $entry->getRawOriginal();
        $beforePayment = $payment->getRawOriginal();
        $beforeMapping = (array) DB::table('candidate_account_ids')->where('user_id', $user->id)->sole();
        $audit = AuditLog::query()->create(['actor_type' => User::class, 'actor_id' => $user->id, 'actor_name' => $user->name, 'action' => 'submitted', 'module' => 'Application'])->fresh();
        $beforeAudit = $audit->getRawOriginal();

        $account = $this->promote();

        $this->assertNotEquals($user->id, $account->id);
        $this->assertSame($user->id, $account->auth_id);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame($beforeEntry, $entry->fresh()->getRawOriginal());
        $this->assertSame($beforePayment, $payment->fresh()->getRawOriginal());
        $this->assertSame($beforeMapping, (array) DB::table('candidate_account_ids')->where('user_id', $user->id)->sole());
        $this->assertInstanceOf(StudentLogin::class, $entry->fresh()->creator);
        $this->assertSame($user->id, $entry->fresh()->creator->getKey());
        $this->assertSame($user->id, $payment->fresh()->user->getKey());
        $this->assertSame($user->id, $payment->fresh()->receivedBy->getKey());
        $this->assertTrue(Payment::query()->whereHas('user', fn ($query) => $query->where('email', 'owner@example.test'))->exists());
        $this->assertTrue($entry->fresh()->creator->hasRole('candidate'));
        $this->assertSame($beforeAudit, $audit->fresh()->getRawOriginal());
        $this->assertInstanceOf(StudentLogin::class, $audit->fresh()->actor);
        $this->assertSame($user->id, $audit->fresh()->actor->getKey());
        $this->assertInstanceOf(StudentLogin::class, AuditLog::query()->with('actor')->findOrFail($audit->id)->actor);

        // Eager-loading only selected profile fields must retain the account store.
        $partial = CustomFormEntry::query()->with('creator:id,name,email')->findOrFail($entry->id)->creator;
        $this->assertInstanceOf(StudentLogin::class, $partial);
        $this->assertSame($user->id, $partial->fresh()->getKey());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_existing_and_new_workflow_notifications_keep_the_same_logical_owner_and_deduplicate(): void
    {
        [$user, $entry] = $this->application();
        $snapshot = $entry->workflow_snapshot;
        $snapshot['schema_version'] = 1;
        $snapshot['stages'][0]['data']['stage_type'] = 'awaiting_results';
        $entry->forceFill(['workflow_snapshot' => $snapshot, 'review_status' => 'passed'])->saveQuietly();
        Notification::make()->title('Existing notification')->body('Earlier application update')->sendToDatabase($user);
        $this->assertSame(1, $user->notifications()->count());

        $this->promote();
        $owner = AccountUser::query()->findOrFail($user->id);

        $this->assertInstanceOf(StudentLogin::class, $owner);
        $this->assertSame(User::class, $owner->getMorphClass());
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertSame(2, $owner->notifications()->count());
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::AwaitingResults));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertSame(2, $owner->notifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class, 'notifiable_id' => (string) $user->id,
        ]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_promoted_accounts_remain_in_student_totals_and_reserve_their_login_username(): void
    {
        [$user] = $this->application();
        $before = [DashboardMetrics::totalStudents(), DashboardMetrics::activeStudents(), DashboardMetrics::inactiveStudents()];

        $this->promote();

        $this->assertSame($before, [DashboardMetrics::totalStudents(), DashboardMetrics::activeStudents(), DashboardMetrics::inactiveStudents()]);
        $this->assertSame('owner_1', SystemUsername::generate('owner@different.example', null));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertSame(1, AccountUser::query()->where('registration_type', 'student')->count());
    }

    public function test_dashboard_keeps_legacy_candidate_ownership_separate_from_student_record_ids(): void
    {
        Schema::table('custom_form_entries', fn (Blueprint $table) => $table->unsignedBigInteger('system_user_id')->nullable());
        [$user, $entry] = $this->application();
        $user->forceFill(['source_candidate_id' => 989898])->saveQuietly();
        NewStudentAccount::query()->create([
            'student_id' => 'UNRELATED', 'first_name_latin' => 'OTHER', 'last_name_latin' => 'RECORD',
        ]);
        $account = $this->promote();

        foreach ([$account->source_candidate_id, $account->id] as $owner) {
            DB::table('custom_form_entries')->insert([
                'custom_form_id' => $entry->custom_form_id, 'system_user_id' => $owner,
                'review_status' => 'pending', 'data' => json_encode(['submitted_at' => now()->toIso8601String()]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->assertSame(989898, $account->source_candidate_id);
        $this->assertSame(2, DashboardMetrics::studentSubmittedApplicationsCount($user->id));
    }

    public function test_manual_payment_selector_includes_both_account_stores_under_their_logical_ids(): void
    {
        [$candidate] = $this->application();
        $student = NewStudentAccount::query()->create([
            'student_id' => 'OTHER-STUDENT', 'email' => 'student@example.test', 'phone' => '098765432',
            'first_name_latin' => 'OTHER', 'last_name_latin' => 'STUDENT',
        ]);
        $staff = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Staff', 'username' => 'selector-staff',
            'password' => 'staff-password', 'date_of_birth' => '1990-01-01',
        ]);

        $options = (new ReflectionMethod(PaymentForm::class, 'candidateUserOptions'))->invoke(null);

        $this->assertSame('SOK DARA', $options[$candidate->id]);
        $this->assertSame('OTHER STUDENT', $options[$student->auth_id]);
        $this->assertArrayNotHasKey($staff->id, $options);
        $this->assertCount(2, $options);
    }

    private function application(?int $logicalId = null): array
    {
        $user = new User;
        $user->forceFill([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'username' => 'owner', 'email' => 'owner@example.test', 'phone' => '012345678',
            'date_of_birth' => '2000-01-01', 'password' => 'original-password', 'is_active' => true,
            'candidate_roles' => ['candidate'],
        ]);
        if ($logicalId !== null) {
            $user->id = $logicalId;
        }
        $user->save();
        $user->assignRole(Role::findOrCreate('candidate', 'web'));
        $form = CustomForm::query()->create(['name' => 'Preserved application', 'slug' => 'preserved-application', 'is_active' => true]);
        $workflow = WorkflowNotification::query()->create(['name' => 'Preserved workflow', 'stages' => [
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'],
                'notification_channels' => ['system'], 'notification_message' => 'Your documents are under review.',
            ]],
        ]]);
        $workflow->forms()->attach($form);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $user->id, 'review_status' => 'pending',
            'candidate_id' => 'MED-0001', 'workflow_snapshot' => WorkflowEntrySnapshot::forForm($form->id),
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'submitted_at' => now()->toIso8601String()],
        ])->saveQuietly();

        return [$user->fresh(), $entry->fresh()];
    }

    private function promote(): NewStudentAccount
    {
        return app(AmsStudentAccountTransfer::class)->receive([
            'student_id' => 'STU-OWNERSHIP-001', 'email' => 'owner@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA',
        ]);
    }
}
