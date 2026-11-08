<?php

namespace Tests\Feature;

use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\WorkflowCandidateVerifier;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class StaticApplicationReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $candidate;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->staff = $this->user('admin');
        $this->staff->assignRole(Role::findOrCreate('registrar_officer', 'web'));
        foreach (['ViewAny', 'View', 'Accepted', 'Verify', 'ViewPdf'] as $action) {
            $this->staff->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $this->actingAs($this->staff);
        $this->candidate = $this->user('student');
        $this->major = Major::query()->create([
            'key' => 'receipt-nurse', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true,
        ]);
    }

    public static function reviewPaths(): array
    {
        return ['table' => ['row'], 'PDF modal' => ['modal'], 'PDF HTTP approval' => ['http']];
    }

    public static function staleReviewPaths(): array
    {
        return ['table' => ['row'], 'PDF modal footer' => ['modal']];
    }

    #[DataProvider('staleReviewPaths')]
    public function test_stale_checked_callbacks_cannot_overwrite_receipt_confirmed_by_another_request(string $path): void
    {
        $entry = $this->entry($this->form());
        if ($path === 'row') {
            DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
        } elseif (! DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->exists()) {
            DocumentTemplate::query()->create(['name' => 'Stale receipt review', 'type' => 'custom_form_'.$entry->custom_form_id, 'content' => '<p>Application review</p>']);
        }
        $component = Livewire::test(ListCustomFormEntries::class)
            ->mountTableAction($path === 'row' ? 'accepted' : 'view_template_pdf_modal', $entry);
        $action = $component->instance()->getMountedTableAction();
        $this->assertNotNull($action);
        $staleRecord = $action->getRecord();
        if ($path === 'modal') {
            $action = collect($action->getExtraModalFooterActions())->first(fn ($action): bool => $action->getName() === 'approve_from_view');
            $this->assertNotNull($action);
        }
        $callback = $action->getActionFunction();
        $this->assertNotNull($callback);

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $confirmed = CustomFormEntry::query()->findOrFail($entry->id);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($confirmed));
        $this->assertSame('pending', $staleRecord->review_status);
        $before = $this->row($confirmed);
        $audit = $this->rows('audit_logs');
        $notifications = $this->rows('notifications');
        try {
            $path === 'row' ? $callback($staleRecord) : $callback();
            $this->fail('A stale Checked callback must recheck eligibility under the application lock.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($before, $this->row($confirmed));
        $this->assertSame($audit, $this->rows('audit_logs'));
        $this->assertSame($notifications, $this->rows('notifications'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $entry->custom_form_id, 'last_sequence' => 1]);
    }

    #[DataProvider('reviewPaths')]
    public function test_static_checked_application_waits_for_receipt_confirmation_without_changing_workflow_or_payment(string $path): void
    {
        $entry = $this->entry($this->form());
        $this->review($entry, $path);
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertNull($entry->candidate_id);
        $this->assertArrayNotHasKey('application_received_at', $entry->data);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        DB::table('payments')->insert([
            'custom_form_entry_id' => $entry->id, 'form_id' => $entry->custom_form_id, 'users_id' => $entry->created_by,
            'status_payt' => 'paid', 'receipt_number' => 'STATIC-RECEIPT-'.$entry->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $before = $this->row($entry);
        $payments = $this->rows('payments');
        $notifications = $this->rows('notifications');
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->mountTableAction('verify', $entry);
        $this->assertSame(__('workflow_notifications.actions.verify'), $component->instance()->getMountedAction()?->getLabel());
        $this->assertSame(__('workflow_notifications.confirmations.static_receipt_body'), $component->instance()->getMountedAction()?->getModalDescription());
        $this->assertNotNull($component->instance()->getMountedAction()?->getModalSubmitAction());
        $this->assertSame($before, $this->row($entry));
        $component->unmountTableAction();
        $this->assertSame($before, $this->row($entry));
        $this->assertDatabaseCount('candidate_id_sequences', 0);

        $component->mountTableAction('verify', $entry)->callMountedTableAction()->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertSame(now()->year, $entry->candidate_id_year);
        $this->assertNotEmpty($entry->data['application_received_at']);
        $this->assertSame($this->staff->id, $entry->data['application_received_by']);
        $data = $entry->data;
        unset($data['application_received_at'], $data['application_received_by']);
        $this->assertSame(json_decode($before['data'], true), $data);
        $after = $this->row($entry);
        foreach (['candidate_id', 'candidate_id_year', 'updated_at', 'data'] as $column) {
            unset($before[$column], $after[$column]);
        }
        $this->assertSame($before, $after);
        $this->assertArrayNotHasKey('workflow_progress', $entry->data);
        $this->assertFalse($entry->workflow_snapshot['has_workflow']);
        $this->assertSame($payments, $this->rows('payments'));
        $this->assertSame($notifications, $this->rows('notifications'));
        $component->call('$refresh')->assertTableActionHidden('verify', $entry);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_existing_static_id_remains_receivable_and_retry_preserves_every_stored_value(): void
    {
        $entry = $this->entry($this->form(), 'accepted', ['major' => 'unrecognized legacy major']);
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['candidate_id' => 'OLD-4242', 'candidate_id_year' => 2023]);
        $entry->refresh();
        $before = $this->row($entry);
        $notifications = $this->rows('notifications');
        $this->assertTrue(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->callTableAction('verify', $entry)->assertHasNoTableActionErrors();
        $this->assertSame('OLD-4242', $entry->refresh()->candidate_id);
        $this->assertSame(2023, $entry->candidate_id_year);
        $this->assertSame($before['workflow_snapshot'], $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame('accepted', $entry->review_status);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        $received = $this->row($entry);
        $audit = $this->rows('audit_logs');
        $this->travel(1)->minutes();
        $this->assertSame('OLD-4242', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($received, $this->row($entry));
        $this->assertSame($audit, $this->rows('audit_logs'));
        $this->assertSame($notifications, $this->rows('notifications'));
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        $this->staff->revokePermissionTo('Verify:CustomFormEntry')->unsetRelation('permissions');
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->assertSame($received, $this->row($entry));
        $this->assertSame($audit, $this->rows('audit_logs'));
        $this->staff->givePermissionTo('Verify:CustomFormEntry');
        $this->travelBack();
        $confirmedAt = $entry->data['application_received_at'];
        $confirmedBy = $entry->data['application_received_by'];
        $this->actingAs($this->candidate);
        $entry->forceFill(['data' => array_replace($entry->data, [
            'application_received_at' => '2099-01-01T00:00:00+00:00', 'application_received_by' => $this->candidate->id,
        ])])->save();
        $this->assertSame($confirmedAt, $entry->fresh()->data['application_received_at']);
        $this->assertSame($confirmedBy, $entry->fresh()->data['application_received_by']);
        $data = $entry->fresh()->data;
        unset($data['application_received_at'], $data['application_received_by']);
        $data['answer'] = 'Updated answer';
        $entry->forceFill(['data' => $data])->save();
        $this->assertSame($confirmedAt, $entry->fresh()->data['application_received_at']);
        $this->assertSame($confirmedBy, $entry->fresh()->data['application_received_by']);
        $this->assertSame('Updated answer', $entry->fresh()->data['answer']);
    }

    public function test_each_checked_static_sibling_requires_its_own_receipt_but_reuses_the_account_candidate_id(): void
    {
        $form = $this->form();
        $first = $this->entry($form, 'approved');
        $second = $this->entry($form, 'approved');
        $secondBefore = $this->row($second);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($first));
        $this->assertSame($secondBefore, $this->row($second));
        $this->assertNull($second->fresh()->candidate_id);
        $this->assertArrayNotHasKey('application_received_at', $second->data);
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($second));
        $this->assertNotEmpty($second->refresh()->data['application_received_at']);
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseCount('candidate_id_sequences', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
    }

    public function test_pending_returned_draft_inactive_profile_and_missing_major_cannot_be_received(): void
    {
        $service = app(WorkflowCandidateVerifier::class);
        $form = $this->form();
        foreach (['pending', 'draft', 'rejected', 'failed'] as $status) {
            $entry = $this->entry($form, $status);
            $before = $this->row($entry);
            $this->assertFalse($service->canVerify($entry));
            $this->assertBlocked(fn () => $service->verify($entry));
            $this->assertSame($before, $this->row($entry));
        }
        foreach ([
            $this->entry($form, 'approved', ['registration_status' => 'rejected']),
            $this->entry($this->form(['is_active' => false]), 'approved'),
            $this->entry($this->form(['slug' => ' Profile ']), 'approved'),
        ] as $entry) {
            $before = $this->row($entry);
            $this->assertFalse($service->canVerify($entry));
            $this->assertBlocked(fn () => $service->verify($entry));
            $this->assertSame($before, $this->row($entry));
        }
        $missingMajor = $this->entry($form, 'approved', ['major' => 'unknown']);
        $before = $this->row($missingMajor);
        $this->assertNull($service->verify($missingMajor));
        $this->assertSame($before, $this->row($missingMajor));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
    }

    public function test_receipt_rechecks_permissions_and_fresh_status_and_blocks_candidate_forgery(): void
    {
        $this->actingAs($this->candidate);
        $entry = $this->entry($this->form(), 'approved', [
            'application_received_at' => '2099-01-01T00:00:00+00:00', 'application_received_by' => $this->candidate->id,
        ]);
        $this->assertArrayNotHasKey('application_received_at', $entry->data);
        $this->assertArrayNotHasKey('application_received_by', $entry->data);
        $this->actingAs($this->staff);
        $before = $this->row($entry);
        $component = Livewire::test(ListCustomFormEntries::class)->mountTableAction('verify', $entry);
        $this->staff->revokePermissionTo('Verify:CustomFormEntry')->unsetRelation('permissions');
        $component->callMountedTableAction();
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $entry);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 403);
        $this->assertSame($before, $this->row($entry));
        $this->staff->givePermissionTo('Verify:CustomFormEntry');
        $this->staff->revokePermissionTo('View:CustomFormEntry')->unsetRelation('permissions');
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->staff->givePermissionTo('View:CustomFormEntry');
        $this->candidate->givePermissionTo('Verify:CustomFormEntry', 'View:CustomFormEntry', 'ViewAny:CustomFormEntry');
        $this->actingAs($this->candidate);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($before, $this->row($entry));

        $this->actingAs($this->staff);
        $component = Livewire::test(ListCustomFormEntries::class)->mountTableAction('verify', $entry);
        $data = $entry->data;
        $data['registration_status'] = 'failed';
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'failed', 'data' => json_encode($data)]);
        $changed = $this->row($entry);
        $component->callMountedTableAction();
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertSame($changed, $this->row($entry));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    public function test_frozen_static_receipt_works_after_assignment_but_configured_workflow_without_receipt_stage_stays_excluded(): void
    {
        $form = $this->form();
        $static = $this->entry($form, 'passed');
        $snapshot = $static->getRawOriginal('workflow_snapshot');
        $workflow = WorkflowNotification::query()->create(['name' => 'Later received workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_type' => 'review', 'stage_name' => 'Review', 'responsible_role' => ['registrar_officer']]],
            ['type' => 'stage', 'data' => ['stage_type' => 'verification', 'stage_name' => 'Received', 'responsible_role' => ['registrar_officer']]],
        ]]);
        $workflow->forms()->attach($form);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $static)
            ->callTableAction('verify', $static)->assertHasNoTableActionErrors();
        $this->assertSame($snapshot, $static->refresh()->getRawOriginal('workflow_snapshot'));
        $this->assertArrayNotHasKey('workflow_progress', $static->data);
        $this->assertSame('passed', $static->review_status);
        $this->assertNotEmpty($static->data['application_received_at']);

        $workflow->update(['stages' => [$workflow->stages[0], ['type' => 'stage', 'data' => ['stage_type' => 'payment', 'stage_name' => 'Payment', 'payment_source' => 'system']]]]);
        $configured = $this->entry($form, 'approved');
        $before = $this->row($configured);
        $this->assertTrue($configured->workflow_snapshot['has_workflow']);
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($configured));
        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('verify', $configured);
        $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($configured));
        $this->assertSame($before, $this->row($configured));
    }

    private function review(CustomFormEntry $entry, string $path): void
    {
        if ($path === 'row') {
            DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
        } elseif (! DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->exists()) {
            DocumentTemplate::query()->create(['name' => 'Static receipt review', 'type' => 'custom_form_'.$entry->custom_form_id, 'content' => '<p>Application review</p>']);
        }
        if ($path === 'http') {
            $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        } else {
            $component = Livewire::test(ListCustomFormEntries::class)
                ->callTableAction($path === 'modal' ? ['view_template_pdf_modal', 'approve_from_view'] : 'accepted', $entry);
            if ($path === 'modal') {
                $component->assertRedirect();
            } else {
                $component->assertHasNoTableActionErrors();
            }
        }
        $entry->refresh();
    }

    private function assertBlocked(callable $callback, ?int $expectedStatus = null): void
    {
        try {
            $callback();
            $this->fail('Receipt requires eligible application state and authorized staff.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(403, $exception->status() ?? 403);
        } catch (HttpExceptionInterface $exception) {
            if ($expectedStatus !== null) {
                $this->assertSame($expectedStatus, $exception->getStatusCode());
            } else {
                $this->assertContains($exception->getStatusCode(), [403, 409]);
            }
        }
    }

    private function form(array $overrides = []): CustomForm
    {
        return CustomForm::query()->create(array_replace([
            'name' => 'Static receipt application', 'slug' => 'static-receipt-'.uniqid(), 'is_active' => true, 'requires_payment' => true,
        ], $overrides));
    }

    private function entry(CustomForm $form, string $status = 'pending', array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status,
            'data' => array_replace(['registration_status' => $status, 'candidate_status' => 'pending', 'major' => (string) $this->major->id, 'answer' => 'Original answer'], $data),
        ]);
    }

    private function row(CustomFormEntry $entry): array
    {
        return (array) DB::table('custom_form_entries')->where('id', $entry->id)->first();
    }

    private function rows(string $table): array
    {
        return DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    private function user(string $type): User
    {
        $name = 'static-receipt-'.$type.'-'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
