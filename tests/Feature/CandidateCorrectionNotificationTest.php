<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Livewire\DatabaseNotifications;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\StaticWorkflowMessages;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CandidateCorrectionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        $this->candidate = $this->candidate('candidate');
        $this->actingAs($this->candidate);
        $this->form = CustomForm::query()->create([
            'name' => 'Admission Form', 'slug' => 'correction-admission',
            'is_active' => true, 'allowed_roles' => ['submission_applicant', 'admin'], 'requires_payment' => false,
        ]);
        $section = $this->form->fields()->create(['name' => 'answers', 'label' => 'Application', 'type' => 'section', 'sort' => 1]);
        $this->form->fields()->create(['parent_id' => $section->id, 'name' => 'answer', 'label' => 'Answer', 'type' => 'text_input', 'required' => true]);
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Admission review',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => 'admin',
                    'notification_channels' => ['system'],
                    'review_actions' => [
                        'send_back' => ['notification_message' => 'Please correct your application.'],
                        'reject' => ['notification_message' => 'Your application was rejected.'],
                    ],
                ]],
            ],
        ]);
        $workflow->forms()->attach($this->form);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function notificationVersions(): array
    {
        return ['new notification' => [false], 'existing send-back notification' => [true]];
    }

    #[DataProvider('notificationVersions')]
    public function test_candidate_acknowledges_the_message_edits_and_resubmits_the_same_application(bool $legacy): void
    {
        $entry = $this->returnedEntry();
        $snapshot = $entry->workflow_snapshot;
        $notification = $this->sendBackNotification($entry);
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($entry->customForm), 'Candidate must have form access');
        $this->assertTrue(Gate::allows('update', $entry), 'Candidate must have update permission');
        $this->assertSame((string) $entry->id, data_get($notification->data, 'viewData.candidate_correction_entry_id'));
        if ($legacy) {
            $data = $notification->data;
            unset($data['viewData']['candidate_correction_entry_id']);
            $notification->update(['data' => $data]);
        }
        $component = Livewire::test(DatabaseNotifications::class)
            ->assertSee('openCandidateCorrection', false)
            ->call('openCandidateCorrection', (string) $notification->id)
            ->assertSuccessful()
            ->assertDispatched('close-modal', id: 'database-notifications')
            ->assertDispatched('open-modal', id: 'candidate-correction-note-modal')
            ->assertSet('candidateCorrectionMessage', $notification->data['body'])
            ->assertSee('Please correct your application.')
            ->assertSee('Correct the attached documents.')
            ->assertNoRedirect();
        $this->assertNull($notification->fresh()->read_at);
        $this->assertSame('rejected', $entry->fresh()->review_status);
        $component->call('confirmCandidateCorrection')
            ->assertRedirect(CustomFormEntryResource::getUrl('edit', ['record' => $entry], panel: 'app'));
        $this->assertNotNull($notification->fresh()->read_at);
        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->set('data.data', ['registration_status' => 'rejected', 'answer' => 'Corrected answer'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Corrected answer', $entry->fresh()->data['answer']);
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame('pending', $entry->fresh()->data['registration_status']);
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $this->form->id)->count());
    }

    public function test_another_candidates_notification_and_entry_cannot_be_opened(): void
    {
        $other = $this->candidate('other');
        $entry = $this->returnedEntry($other);
        $notification = $this->sendBackNotification($entry, $other);
        Livewire::test(DatabaseNotifications::class)->call('openCandidateCorrection', (string) $notification->id)->assertNotFound();
        $forged = $this->candidate->notifications()->create([
            'id' => (string) str()->uuid(), 'type' => 'test', 'data' => $notification->data,
        ]);
        Livewire::test(DatabaseNotifications::class)->call('openCandidateCorrection', (string) $forged->id)->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
        $this->assertNull($forged->fresh()->read_at);
    }

    public function test_confirmation_rechecks_that_the_form_is_still_returned(): void
    {
        $entry = $this->returnedEntry();
        $notification = $this->sendBackNotification($entry);
        $component = Livewire::test(DatabaseNotifications::class)->call('openCandidateCorrection', (string) $notification->id);
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'approved']);
        $component->call('confirmCandidateCorrection')->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_final_rejection_is_not_a_correction_link(): void
    {
        $entry = $this->returnedEntry();
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'failed']);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($entry->refresh(), 'reject', 'Final decision'));
        $notification = $this->candidate->notifications()->where('data->viewData->workflow_event', 'review_action:reject')->sole();
        $this->assertArrayNotHasKey('candidate_correction_entry_id', $notification->data['viewData']);
        Livewire::test(DatabaseNotifications::class)->call('openCandidateCorrection', (string) $notification->id)->assertNotFound();
    }

    public function test_non_template_form_keeps_its_existing_edit_action(): void
    {
        WorkflowNotification::query()->firstOrFail()->forms()->detach($this->form);
        $entry = $this->returnedEntry();
        StaticWorkflowMessages::notifyApplicationResult($entry, 'rejected', 'Static correction');
        $notification = $this->candidate->notifications()->latest()->first();
        $action = collect($notification->data['actions'])->firstWhere('name', 'edit_form');
        $this->assertSame(CustomFormEntryResource::getUrl('edit', ['record' => $entry], panel: 'app'), $action['url']);
    }

    public function test_fallback_return_stage_includes_the_review_note_and_correction_link(): void
    {
        $workflow = WorkflowNotification::query()->firstOrFail();
        $stages = $workflow->stages;
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Returned', 'stage_type' => 'rejected',
            'notification_message' => 'Update the missing details.', 'notification_channels' => ['system'],
        ]];
        $workflow->update(['stages' => $stages]);
        $entry = $this->returnedEntry();

        $this->assertTrue(WorkflowStageMessages::notify($entry, WorkflowStageType::Rejected));
        $notification = $this->candidate->notifications()->latest()->firstOrFail();
        $this->assertSame((string) $entry->id, data_get($notification->data, 'viewData.candidate_correction_entry_id'));
        Livewire::test(DatabaseNotifications::class)
            ->call('openCandidateCorrection', (string) $notification->id)
            ->assertSuccessful()
            ->assertSee('Update the missing details.')
            ->assertSee('Correct the attached documents.');
    }

    public function test_review_note_is_rendered_as_text_in_the_message_box(): void
    {
        $entry = $this->returnedEntry();
        $notification = $this->sendBackNotification($entry);
        $data = $notification->data;
        $data['body'] = '<script>alert("review-note")</script>';
        $notification->update(['data' => $data]);

        Livewire::test(DatabaseNotifications::class)
            ->call('openCandidateCorrection', (string) $notification->id)
            ->assertSuccessful()
            ->assertSee($data['body'])
            ->assertDontSee($data['body'], false);
    }

    private function candidate(string $username): User
    {
        $user = User::query()->forceCreate([
            'registration_type' => 'student', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'test-password', 'is_active' => true, 'locale' => 'en',
        ]);
        $user->assignRole(Role::findOrCreate('candidate', 'web'), Role::findOrCreate('submission_applicant', 'web'));
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $profile = CustomForm::query()->firstOrCreate(['slug' => 'profile'], ['name' => 'Profile', 'is_active' => true]);
        CustomFormEntry::query()->create(['custom_form_id' => $profile->id, 'created_by' => $user->id, 'data' => ['registration_status' => 'pending']]);

        return $user;
    }

    private function returnedEntry(?User $owner = null): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $this->form->id, 'created_by' => ($owner ?? $this->candidate)->id,
            'data' => ['registration_status' => 'pending', 'answer' => 'Original answer'],
        ]);
        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'review_status' => 'rejected', 'review_note' => 'Correct the attached documents.',
            'data' => json_encode(['registration_status' => 'rejected', 'answer' => 'Original answer']),
        ]);

        return $entry->refresh();
    }

    private function sendBackNotification(CustomFormEntry $entry, ?User $owner = null)
    {
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($entry, 'send_back', $entry->review_note));

        return ($owner ?? $this->candidate)->notifications()->where('data->viewData->workflow_event', 'review_action:send_back')->latest()->firstOrFail();
    }
}
