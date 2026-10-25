<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\DegreeLevels\Pages\CreateDegreeLevel;
use App\Filament\Admin\Resources\DegreeLevels\Pages\EditDegreeLevel;
use App\Filament\Livewire\DatabaseNotifications;
use App\Models\DegreeLevel;
use App\Models\Payment;
use App\Models\User;
use App\Services\CandidatePaymentRecorder;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Wizard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Tests\TestCase;

class UatWorkflowRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    private function user(string $role = 'admin'): User
    {
        $id = User::count() + 1;
        $u = User::create(['name' => 'QA '.$role, 'registration_type' => $role === 'candidate' ? 'student' : 'admin', 'username' => 'qa_'.$id, 'email' => 'qa-'.$id.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'test-only-password', 'is_active' => true]);
        $u->assignRole(Role::findOrCreate($role, 'web'));
        if ($role === 'candidate') {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
                $u->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
            }
            $profile = CustomForm::firstOrCreate(['slug' => 'profile'], ['name' => 'Profile', 'is_active' => true]);
            CustomFormEntry::create(['custom_form_id' => $profile->id, 'created_by' => $u->id, 'data' => ['registration_status' => 'pending']]);
        }

        return $u;
    }

    private function entry(User $candidate, int $steps = 1, string $status = 'rejected'): CustomFormEntry
    {
        $form = CustomForm::create(['name' => 'QA Form', 'slug' => 'qa-form-'.CustomForm::count(), 'is_active' => true, 'allowed_roles' => ['candidate', 'admin'], 'requires_payment' => true]);
        $wizard = $form->fields()->create(['name' => 'qa_wizard', 'label' => 'Application', 'type' => 'wizard', 'sort' => 1]);
        for ($i = 0; $i < $steps; $i++) {
            $section = $form->fields()->create(['parent_id' => $wizard->id, 'name' => 'step_'.$i, 'label' => 'Step '.($i + 1), 'type' => 'section', 'sort' => $i]);
            $form->fields()->create(['parent_id' => $section->id, 'name' => 'name_'.$i, 'label' => 'Name '.($i + 1), 'type' => 'text_input', 'required' => true, 'sort' => 1]);
        }
        $entry = CustomFormEntry::create(['custom_form_id' => $form->id, 'created_by' => $candidate->id, 'data' => ['registration_status' => $status]]);
        $entry->forceFill(['review_status' => $status])->save();

        return $entry;
    }

    private function reviewableEntry(User $candidate): CustomFormEntry
    {
        $entry = $this->entry($candidate, status: 'pending');
        DocumentTemplate::create([
            'name' => 'QA Review Template',
            'type' => 'custom_form_'.$entry->custom_form_id,
            'custom_form_id' => $entry->custom_form_id,
            'content' => '<p>Application review</p>',
        ]);

        return $entry;
    }

    public static function wizardSizes(): array
    {
        return [[1], [2], [4]];
    }

    #[DataProvider('wizardSizes')]
    public function test_returned_application_can_validate_save_and_reenter_review(int $steps): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, $steps, 'pending');
        $admin = $this->user();
        $this->actingAs($admin)->post('/admin/custom-form-entries/'.$entry->id.'/reject', ['review_note' => 'Correct QA names'])->assertRedirect();
        $this->assertSame('rejected', $entry->fresh()->review_status);
        $component = Livewire::actingAs($candidate)->test(EditCustomFormEntry::class, ['record' => $entry->id])->assertSuccessful();
        $this->assertNotNull($component->instance());
        if ($steps === 1) {
            $component->assertActionVisible(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'));
        } else {
            $this->assertTrue((new ReflectionMethod(EditCustomFormEntry::class, 'hasWizardBeforeFinalStep'))->invoke($component->instance()));
            $wizard = $component->instance()->form->getComponent(fn ($c) => $c instanceof Wizard);
            $last = array_values($wizard->getChildSchema()->getComponents())[$steps - 1];
            $component->call('updateWizardStep', $last->getId())->assertActionVisible(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'));
        }
        $component->callAction(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))->assertHasFormErrors(['data.name_0' => 'required']);
        $state = ['registration_status' => 'rejected'];
        for ($i = 0; $i < $steps; $i++) {
            $state['name_'.$i] = 'CORRECTED QA '.$i;
        }
        $component->set('data.data', $state)->callAction(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))->assertHasNoFormErrors();
        $entry->refresh();
        $this->assertSame('CORRECTED QA 0', $entry->data['name_0']);
        $this->assertSame('pending', $entry->review_status);
        $this->assertSame('pending', $entry->data['registration_status']);
        $this->assertSame($candidate->id, $entry->created_by);
        $this->actingAs($admin)->post('/admin/custom-form-entries/'.$entry->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $entry->fresh()->review_status);
    }

    public function test_the_table_keeps_the_original_pdf_review_action(): void
    {
        $candidate = $this->user('candidate');
        $admin = $this->user('admin');
        $admin->givePermissionTo(
            Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'),
            Permission::findOrCreate('ViewPdf:CustomFormEntry', 'web'),
        );
        $entry = $this->reviewableEntry($candidate);

        $component = Livewire::actingAs($admin)->test(ListCustomFormEntries::class)
            ->call('mountTableAction', 'view_template_pdf_modal', (string) $entry->getKey());

        $this->assertSame(
            'view_template_pdf_modal',
            $component->instance()->getMountedTableAction()?->getName(),
        );
    }

    public function test_checking_a_new_application_without_a_comment_opens_review_directly(): void
    {
        $candidate = $this->user('candidate');
        $admin = $this->user('admin');
        $admin->givePermissionTo(Permission::findOrCreate('ViewPdf:CustomFormEntry', 'web'));
        $entry = $this->reviewableEntry($candidate);

        Livewire::actingAs($admin)->test(DatabaseNotifications::class)
            ->call('openResubmissionReviewForEntry', (string) $entry->getKey())
            ->assertActionMounted('applicationReview')
            ->assertNoRedirect()
            ->assertNotDispatched('open-modal');
    }

    public function test_checking_an_application_with_a_comment_requires_acknowledgement_before_review(): void
    {
        $candidate = $this->user('candidate');
        $admin = $this->user('admin');
        $admin->givePermissionTo(Permission::findOrCreate('ViewPdf:CustomFormEntry', 'web'));
        $entry = $this->reviewableEntry($candidate);
        $entry->forceFill(['review_note' => 'Please correct the supporting documents.'])->save();

        $component = Livewire::actingAs($admin)->test(DatabaseNotifications::class)
            ->call('openResubmissionReviewForEntry', (string) $entry->getKey())
            ->assertSet('resubmissionReviewNote', 'Please correct the supporting documents.')
            ->assertDispatched('open-modal', id: 'resubmission-review-note-modal')
            ->assertActionNotMounted('applicationReview');

        $component->call('confirmResubmissionReview')
            ->assertActionMounted('applicationReview')
            ->assertNoRedirect();
    }

    public static function notificationReviewOrigins(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'settings page' => ['settings'],
            'another form' => ['another-form'],
        ];
    }

    #[DataProvider('notificationReviewOrigins')]
    public function test_notification_confirmation_opens_the_original_review_from_any_page(string $origin): void
    {
        $candidate = $this->user('candidate');
        $admin = $this->user('admin');
        foreach (['ViewAny', 'ViewPdf', 'Accepted', 'Rejected', 'Reject'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $entry = $this->reviewableEntry($candidate);
        $otherEntry = $this->reviewableEntry($candidate);
        $query = $origin === 'another-form' ? ['form_id' => $otherEntry->custom_form_id] : [];
        $referer = match ($origin) {
            'another-form' => '/custom-form-entries?form_id='.$otherEntry->custom_form_id,
            'settings' => '/system-users',
            default => '/dashboard',
        };
        $notification = $admin->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => 'test-resubmission',
            'data' => [
                'format' => 'filament',
                'viewData' => [
                    'resubmission_entry_id' => (string) $entry->getKey(),
                    'registrar_review_note' => 'Correct the supporting documents.',
                ],
            ],
        ]);

        $component = Livewire::actingAs($admin)->withQueryParams($query)
            ->withHeaders(['Referer' => url($referer)])
            ->test(DatabaseNotifications::class)
            ->call('openResubmissionReview', (string) $notification->getKey())
            ->assertDispatched('open-modal', id: 'resubmission-review-note-modal')
            ->call('confirmResubmissionReview')
            ->assertActionMounted('applicationReview')
            ->assertNoRedirect()
            ->assertDispatched('close-modal', id: 'database-notifications')
            ->assertDispatched('close-modal', id: 'resubmission-review-note-modal');

        $action = $component->instance()->getMountedAction();
        $modalHtml = $component->effects['partials']['action-modals'] ?? $component->html();
        $this->assertStringContainsString(
            route('admin.custom-form-entries.pdf-inline', ['entry' => $entry->getKey()]),
            $modalHtml,
        );
        $this->assertTrue($action->getRecord()->is($entry));
        $this->assertSame([
            'approve_from_view', 'reject_from_view', 'reject_final_from_view',
        ], array_keys($action->getVisibleModalFooterActions()));
        $this->assertSame(['success', 'warning', 'danger'], array_map(
            fn ($button) => $button->getColor(),
            array_values($action->getVisibleModalFooterActions()),
        ));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public static function globalReviewDecisions(): array
    {
        return [
            'approve' => ['approve_from_view', 'Accepted', 'approved', 'approved'],
            'send back' => ['reject_from_view', 'Rejected', 'rejected', 'rejected'],
            'reject' => ['reject_final_from_view', 'Reject', 'failed', 'final_rejected'],
        ];
    }

    #[DataProvider('globalReviewDecisions')]
    public function test_global_review_buttons_keep_their_original_logic_and_target_the_selected_entry(
        string $button,
        string $permission,
        string $expectedStatus,
        string $auditAction,
    ): void {
        $candidate = $this->user('candidate');
        $admin = $this->user('admin');
        foreach (['ViewAny', 'ViewPdf', $permission] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $firstEntry = $this->reviewableEntry($candidate);
        $selectedEntry = $this->reviewableEntry($candidate);

        Livewire::actingAs($admin)->test(DatabaseNotifications::class)
            ->call('openEntryReviewModal', (string) $firstEntry->getKey())
            ->call('openEntryReviewModal', (string) $selectedEntry->getKey())
            ->assertActionMounted('applicationReview')
            ->callAction($button, ['review_note' => 'QA decision comment'])
            ->assertHasNoErrors();

        $selectedEntry->refresh();
        $this->assertSame($expectedStatus, $selectedEntry->review_status);
        $this->assertSame($expectedStatus, $selectedEntry->data['registration_status']);
        $this->assertSame($admin->id, (int) $selectedEntry->reviewed_by);
        $this->assertSame($expectedStatus === 'approved' ? null : 'QA decision comment', $selectedEntry->review_note);
        $this->assertSame('pending', $firstEntry->fresh()->review_status);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => $auditAction,
            'module' => 'Custom Form Entries',
            'new_values->review_status' => $expectedStatus,
        ]);
    }

    public function test_global_review_preserves_role_form_restrictions(): void
    {
        $candidate = $this->user('candidate');
        $reviewer = $this->user('limited_reviewer');
        foreach (['ViewAny', 'ViewPdf'] as $action) {
            $reviewer->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $allowedEntry = $this->reviewableEntry($candidate);
        $deniedEntry = $this->reviewableEntry($candidate);
        \App\Models\Role::where('name', 'limited_reviewer')->firstOrFail()->forceFill([
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => [$allowedEntry->custom_form_id],
        ])->save();

        Livewire::actingAs($reviewer)->test(DatabaseNotifications::class)
            ->call('openEntryReviewModal', (string) $allowedEntry->getKey())
            ->assertActionMounted('applicationReview')
            ->call('openEntryReviewModal', (string) $deniedEntry->getKey())
            ->assertNotFound();
    }

    public function test_global_review_still_requires_pdf_permission(): void
    {
        $candidate = $this->user('candidate');
        $reviewer = $this->user('reviewer');
        $reviewer->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $entry = $this->reviewableEntry($candidate);

        Livewire::actingAs($reviewer)->test(DatabaseNotifications::class)
            ->call('openEntryReviewModal', (string) $entry->getKey())
            ->assertForbidden();
    }

    public function test_candidate_cannot_edit_another_candidates_returned_entry(): void
    {
        $owner = $this->user('candidate');
        $entry = $this->entry($owner);
        $this->actingAs($this->user('candidate'))->get('/custom-form-entries/'.$entry->id.'/edit')->assertNotFound();
    }

    public function test_approved_application_is_locked_against_candidate_save(): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, status: 'approved');
        $component = Livewire::actingAs($candidate)->test(EditCustomFormEntry::class, ['record' => $entry->id]);
        $component->assertRedirect();
        $this->assertSame('approved', $entry->fresh()->review_status);
    }

    public function test_degree_create_populates_order_edit_and_validation_work(): void
    {
        $this->actingAs($this->user());
        Livewire::test(CreateDegreeLevel::class)->fillForm(['key' => 'qa_degree', 'label_en' => 'QA Degree', 'label_kh' => 'QA', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $degree = DegreeLevel::where('key', 'qa_degree')->sole();
        $this->assertNotNull($degree->sort_order);
        Livewire::test(EditDegreeLevel::class, ['record' => $degree->id])->fillForm(['label_en' => 'QA Updated'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('QA Updated', $degree->fresh()->label_en);
        Livewire::test(CreateDegreeLevel::class)->fillForm(['key' => 'qa_degree', 'label_en' => 'Duplicate'])->call('create')->assertHasFormErrors(['key' => 'unique']);
        Livewire::test(CreateDegreeLevel::class)->fillForm(['key' => '', 'label_en' => ''])->call('create')->assertHasFormErrors(['key' => 'required', 'label_en' => 'required']);
    }

    public function test_candidate_cannot_create_degree(): void
    {
        Livewire::actingAs($this->user('candidate'))->test(CreateDegreeLevel::class)->assertForbidden();
    }

    public function test_degree_order_is_sequential_and_explicit_order_is_preserved(): void
    {
        $first = DegreeLevel::create(['key' => 'qa_first', 'label_en' => 'First']);
        $second = DegreeLevel::create(['key' => 'qa_second', 'label_en' => 'Second']);
        $explicit = DegreeLevel::create(['key' => 'qa_explicit', 'label_en' => 'Explicit', 'sort_order' => 20]);
        $this->assertSame(1, (int) $first->sort_order);
        $this->assertSame(2, (int) $second->sort_order);
        $this->assertSame(20, (int) $explicit->sort_order);
    }

    private function paymentData(CustomFormEntry $entry, string $receipt = 'QA-TEST-001'): array
    {
        return ['custom_form_entry_id' => $entry->id, 'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id, 'receipt_number' => $receipt, 'status_payt' => 'paid', 'amount_kh' => 4100, 'amount_usd' => 1, 'status' => true];
    }

    public static function paymentEligibleStatuses(): array
    {
        return [['approved'], ['accepted'], ['passed']];
    }

    #[DataProvider('paymentEligibleStatuses')]
    public function test_payment_is_linked_and_duplicate_attempt_with_different_receipt_is_rejected(string $status): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, status: $status);
        $cashier = $this->user('cashier_manager');
        $this->actingAs($cashier);
        $recorder = app(CandidatePaymentRecorder::class);
        $payment = $recorder->record($this->paymentData($entry));
        $this->assertSame($candidate->id, $payment->users_id);
        $this->assertSame($cashier->id, $payment->received_by);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $cashier->id, 'actor_role' => 'cashier_manager', 'action' => 'created', 'module' => 'Payment']);
        try {
            $recorder->record($this->paymentData($entry, 'QA-DUPLICATE-002'));
            $this->fail('Duplicate accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('receipt_number', $e->errors());
        }
        $this->assertSame(1, Payment::where('custom_form_entry_id', $entry->id)->count());
    }

    public function test_payment_failure_rolls_back_record_and_audit_event(): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, status: 'approved');
        $this->actingAs($this->user('cashier_manager'));
        $before = DB::table('audit_logs')->count();
        try {
            DB::transaction(function () use ($entry): void {
                app(CandidatePaymentRecorder::class)->record($this->paymentData($entry));
                throw new \RuntimeException('QA injected rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('QA injected rollback', $e->getMessage());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($before, DB::table('audit_logs')->count());
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_payment_rejects_ineligible_application_and_wrong_owner(): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, status: 'pending');
        $this->actingAs($this->user('cashier_manager'));
        foreach (['pending', 'approved'] as $status) {
            $entry->forceFill(['review_status' => $status, 'data' => ['registration_status' => $status]])->save();
            try {
                app(CandidatePaymentRecorder::class)->record([...$this->paymentData($entry), 'users_id' => 999]);
                $this->fail('Invalid linkage accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('receipt_number', $e->errors());
            }
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_database_constraint_blocks_duplicate_application_payments(): void
    {
        $candidate = $this->user('candidate');
        $entry = $this->entry($candidate, status: 'approved');
        Payment::create($this->paymentData($entry));
        $this->expectException(QueryException::class);
        Payment::create($this->paymentData($entry, 'QA-SECOND-002'));
    }
}
