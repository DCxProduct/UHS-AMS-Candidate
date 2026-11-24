<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\ClosingDates\Pages\ListClosingDates;
use App\Filament\Pages\StudentDynamicFormPage;
use App\Filament\Student\Pages\SubmissionNextSteps as NextStepsPage;
use App\Models\CandidateSubmitPopupSetting;
use App\Models\ClosingDate;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\ClosingDateWorkflow;
use App\Support\SubmissionNextSteps;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\CreateCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SubmissionNextStepsTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        $this->candidate = $this->candidate('next_steps_candidate');
        $this->actingAs($this->candidate);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_closing_date_menu_can_configure_each_form_including_profile_without_changing_schedules(): void
    {
        $form = $this->form();
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $schedule = ClosingDate::query()->create([
            'name' => 'Intake', 'type' => ClosingDate::customFormTypeKey($form->id),
            'status' => 'open', 'start_date' => today(), 'end_date' => today()->addWeek(),
        ]);
        $before = $schedule->fresh()->getAttributes();
        $profileAvailability = ClosingDateWorkflow::checkByCustomFormId($profile->id);
        $admin = $this->user('admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);

        $component = Livewire::test(ListClosingDates::class)->assertActionVisible('submission_next_steps');
        $component->callAction('submission_next_steps', data: ['custom_form_id' => $form->id, ...$this->settings()])
            ->assertHasNoActionErrors();
        $component->callAction('submission_next_steps', data: [
            'custom_form_id' => $profile->id, ...$this->settings(), 'title_km' => 'សារសម្រាប់ប្រវត្តិរូប',
        ])->assertHasNoActionErrors();
        $component->mountAction('submission_next_steps')
            ->set('mountedActions.0.data.custom_form_id', $form->id)
            ->assertActionDataSet([
                'title_km' => $this->settings()['title_km'], 'is_active' => true,
                'heading_en' => $this->settings()['heading_en'], 'heading_km' => $this->settings()['heading_km'],
                'continue_label_en' => $this->settings()['continue_label_en'], 'continue_label_km' => $this->settings()['continue_label_km'],
            ]);

        $this->assertSame($this->settings()['title_km'], $form->fresh()->submission_next_steps['title_km']);
        $this->assertSame($this->settings()['heading_km'], $form->fresh()->submission_next_steps['heading_km']);
        $this->assertSame($this->settings()['continue_label_en'], $form->fresh()->submission_next_steps['continue_label_en']);
        $this->assertSame('សារសម្រាប់ប្រវត្តិរូប', $profile->fresh()->submission_next_steps['title_km']);
        $this->assertSame($before, $schedule->fresh()->getAttributes());
        $this->assertSame($profileAvailability, ClosingDateWorkflow::checkByCustomFormId($profile->id));
        $this->assertDatabaseCount('closing_dates', 1);
        $this->assertDatabaseCount('candidate_submit_popup_settings', 0);
    }

    public function test_read_only_closing_date_user_cannot_change_next_steps(): void
    {
        $form = $this->form();
        $viewer = $this->user('admin');
        $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:ClosingDate', 'web'));
        $this->actingAs($viewer);

        Livewire::test(ListClosingDates::class)->assertActionHidden('submission_next_steps')
            ->call('mountAction', 'submission_next_steps')->assertActionNotMounted();
        $this->assertNull($form->fresh()->submission_next_steps);
    }

    public function test_closing_date_table_shows_the_next_page_configuration_for_its_linked_form(): void
    {
        $admin = $this->user('admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $rows = [];
        foreach ([
            'enabled' => [true, $this->settings()],
            'disabled' => [true, [...$this->settings(), 'is_active' => false]],
            'not_configured' => [true, null],
            'inactive_form' => [false, $this->settings()],
            'blank_content' => [true, ['is_active' => true, 'title_en' => ' ', 'message_km' => ' ']],
        ] as $state => [$active, $settings]) {
            $form = CustomForm::query()->create([
                'name' => 'Form '.$state, 'slug' => 'next-page-'.$state,
                'is_active' => $active, 'submission_next_steps' => $settings,
            ]);
            $rows[$state] = ClosingDate::query()->create([
                'name' => 'Intake '.$state,
                'type' => $state === 'disabled' ? 'custom_form_'.$form->id : ClosingDate::customFormTypeKey($form->id),
                'status' => 'open',
            ]);
        }
        $unknown = ClosingDate::query()->create(['name' => 'Legacy intake', 'type' => 'unknown-form', 'status' => 'closed']);

        $component = Livewire::test(ListClosingDates::class)->assertSuccessful()
            ->assertSee('Next Steps Page')->assertDontSee('Review your details');
        foreach ($rows as $state => $row) {
            $component->assertTableColumnStateSet('submission_next_steps_status', $state === 'enabled' ? 'enabled' : 'not_open', $row);
        }
        $component->assertTableColumnStateSet('submission_next_steps_status', 'not_open', $unknown);
    }

    public static function formTypes(): array
    {
        return ['custom form' => [false, false], 'workflow form' => [false, true], 'profile' => [true, false]];
    }

    #[DataProvider('formTypes')]
    public function test_successful_submission_opens_configured_next_steps(bool $profile, bool $workflow): void
    {
        $form = $profile ? CustomForm::query()->where('slug', 'profile')->firstOrFail() : $this->form();
        if ($profile) {
            DB::table('custom_form_entries')->where('custom_form_id', $form->id)->delete();
            $this->addField($form);
        }
        if ($workflow) {
            $this->attachWorkflow($form);
        }
        $form->update(['submission_next_steps' => $this->settings()]);

        $component = $this->createPage($form)->set('data.data.answer', 'Submitted answer')->call('create')->assertHasNoFormErrors();
        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $component->assertRedirect(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'));
        $before = $entry->getAttributes();
        app()->setLocale('km');
        $this->get(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'))
            ->assertOk()->assertSee($this->settings()['title_km'])->assertSee($this->settings()['message_km'])
            ->assertSee($this->settings()['heading_km'])->assertSee($this->settings()['continue_label_km'])
            ->assertSee(SubmissionNextSteps::continueUrl($entry), false);
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertSame('pending', $entry->review_status);
        $this->assertSame('Submitted answer', $entry->data['answer']);
    }

    public function test_returned_application_resubmission_keeps_its_workflow_and_opens_next_steps(): void
    {
        $form = $this->form();
        $this->attachWorkflow($form);
        $form->update(['submission_next_steps' => $this->settings()]);
        $entry = $this->entry($form, 'rejected');
        $snapshot = $entry->workflow_snapshot;

        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])->assertSuccessful()
            ->set('data.data.answer', 'Corrected answer')->call('save')->assertHasNoFormErrors()
            ->assertRedirect(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'));
        $entry->refresh();
        $this->assertSame('pending', $entry->review_status);
        $this->assertSame('pending', $entry->data['registration_status']);
        $this->assertSame($snapshot, $entry->workflow_snapshot);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $form->id)->count());
    }

    public function test_validation_errors_and_drafts_do_not_open_the_next_steps_page_but_submitting_a_draft_does(): void
    {
        $form = $this->form();
        $form->update(['submission_next_steps' => $this->settings()]);
        $this->createPage($form)->call('create')->assertHasFormErrors(['data.answer' => 'required'])->assertNoRedirect();
        $this->assertSame(0, CustomFormEntry::query()->where('custom_form_id', $form->id)->count());

        $this->createPage($form)->set('data.data.answer', 'Draft answer')->callAction('save_draft');
        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $this->assertSame('draft', $entry->review_status);
        $this->assertNull(SubmissionNextSteps::redirectUrl($entry));
        Livewire::test(NextStepsPage::class, ['record' => $entry->id])->assertNotFound();

        CandidateSubmitPopupSetting::forCustomFormId($form->id)->update(['is_active' => false]);
        $parameters = ['form_id' => (string) $form->id, 'draft_id' => (string) $entry->id];
        $action = TestAction::make('submit')->schemaComponent('form-actions', schema: 'content');
        Livewire::withQueryParams($parameters)->test(CreateCustomFormEntry::class, $parameters)
            ->callAction($action)->assertHasNoFormErrors()
            ->assertRedirect(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $form->id)->count());
    }

    public function test_disabled_empty_or_unconfigured_messages_preserve_existing_redirects(): void
    {
        $form = $this->form();
        $entry = $this->entry($form);
        $this->assertNull(SubmissionNextSteps::redirectUrl($entry));
        $form->update(['submission_next_steps' => [...$this->settings(), 'is_active' => false]]);
        $this->assertNull(SubmissionNextSteps::redirectUrl($entry->fresh()));
        $form->update(['submission_next_steps' => ['is_active' => true, 'title_en' => ' ', 'message_km' => ' ']]);
        $this->assertNull(SubmissionNextSteps::redirectUrl($entry->fresh()));
        Livewire::test(NextStepsPage::class, ['record' => $entry->id])->assertRedirect(SubmissionNextSteps::continueUrl($entry));
    }

    public function test_next_steps_use_language_fallback_escape_text_and_protect_other_candidates_entries(): void
    {
        $form = $this->form();
        $form->update(['submission_next_steps' => ['is_active' => true, 'title_km' => 'សូមពិនិត្យឡើងវិញ', 'message_km' => '<script>alert("message")</script>']]);
        $entry = $this->entry($form);
        Livewire::test(NextStepsPage::class, ['record' => $entry->id])->assertSuccessful()
            ->assertSee('សូមពិនិត្យឡើងវិញ')->assertSee('<script>alert("message")</script>')
            ->assertSee('Submitted successfully')->assertSee('Continue')
            ->assertDontSee('<script>alert("message")</script>', false);
        $other = $this->candidate('other_candidate');
        $this->actingAs($other);
        Livewire::test(NextStepsPage::class, ['record' => $entry->id])->assertNotFound();
        $this->actingAs($this->user('admin'));
        Livewire::test(NextStepsPage::class, ['record' => $entry->id])->assertForbidden();
    }

    public function test_submitting_a_saved_profile_draft_opens_next_steps(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $this->addField($profile);
        $profile->update(['submission_next_steps' => $this->settings()]);
        $entry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();
        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'review_status' => 'draft', 'data' => json_encode(['registration_status' => 'draft', 'answer' => 'Draft profile']),
        ]);

        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])->assertSuccessful()
            ->set('data.data.answer', 'Completed profile')->call('save')->assertHasNoFormErrors()
            ->assertRedirect(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame('Completed profile', $entry->fresh()->data['answer']);
    }

    public function test_completed_profile_opens_next_steps_then_continue_returns_to_the_saved_profile_without_a_loop(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $this->addField($profile);
        $profile->update(['submission_next_steps' => $this->settings()]);
        $entry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();
        $nextStepsUrl = NextStepsPage::getUrl(['record' => $entry->id], panel: 'app');

        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertRedirect($nextStepsUrl);
        $continueUrl = SubmissionNextSteps::continueUrl($entry);
        $response = $this->get($continueUrl);
        $this->assertSame(200, $response->status(), 'Continue redirected to '.$response->headers->get('Location').' from '.$continueUrl);
        $response->assertSee('Edit Profile');
        $component = Livewire::withQueryParams(['view_submitted' => '1'])
            ->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertSuccessful()->assertNoRedirect()->assertSet('viewSubmitted', true)
            ->assertActionVisible('edit_profile');
        $this->assertTrue($component->instance()->isLockedForEditing());
        $component->callAction('edit_profile')->assertSet('isEditingProfile', true)->assertNoRedirect();
        $this->assertFalse($component->instance()->isLockedForEditing());
        $component->set('data.data.answer', 'Updated completed profile')->call('save')->assertHasNoFormErrors()
            ->assertRedirect($nextStepsUrl);
        $this->assertSame('Updated completed profile', $entry->fresh()->data['answer']);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $profile->id)->count());
    }

    public function test_completed_profile_with_next_steps_off_keeps_the_existing_profile_page(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $profile->update(['submission_next_steps' => [...$this->settings(), 'is_active' => false]]);
        $entry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();

        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertSuccessful()->assertNoRedirect()->assertActionVisible('edit_profile');
    }

    public function test_continue_does_not_unlock_a_permanently_locked_profile(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $profile->update(['submission_next_steps' => $this->settings()]);
        $entry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'approved']);

        $component = Livewire::withQueryParams(['view_submitted' => '1'])
            ->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertSuccessful()->assertNoRedirect()->assertActionDoesNotExist('edit_profile');
        $this->assertTrue($component->instance()->isLockedForEditing());
    }

    public function test_completed_profile_keeps_its_saved_page_after_settings_are_edited_and_disabled(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $profile->update(['submission_next_steps' => $this->settings()]);
        $entry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();
        $this->assertSame($this->settings()['title_en'], SubmissionNextSteps::contentForEntry($entry)['title']);
        $snapshot = $entry->fresh()->submission_next_steps_snapshot;
        SubmissionNextSteps::updateSettings($profile->id, [
            ...$this->settings(), 'is_active' => false,
            'heading_en' => 'New page heading', 'title_en' => 'New title',
            'message_en' => 'New instructions', 'continue_label_en' => 'New button label',
        ]);

        $pageUrl = NextStepsPage::getUrl(['record' => $entry->id], panel: 'app');
        Livewire::test(EditCustomFormEntry::class, ['record' => $entry->id])->assertRedirect($pageUrl);
        $this->get($pageUrl)->assertOk()
            ->assertSee($this->settings()['heading_en'])->assertSee($this->settings()['title_en'])
            ->assertSee($this->settings()['message_en'])->assertSee($this->settings()['continue_label_en'])
            ->assertDontSee('New page heading')->assertDontSee('New title')->assertDontSee('New button label');
        $this->get(SubmissionNextSteps::continueUrl($entry))->assertOk();
        $this->assertSame($snapshot, $entry->fresh()->submission_next_steps_snapshot);
    }

    public function test_next_steps_page_highlights_only_the_menu_of_its_own_form(): void
    {
        $profile = CustomForm::query()->where('slug', 'profile')->firstOrFail();
        $form = $this->form();
        $profile->update(['submission_next_steps' => $this->settings()]);
        $form->update(['submission_next_steps' => $this->settings()]);
        $profileEntry = CustomFormEntry::query()->where('custom_form_id', $profile->id)->sole();
        $applicationEntry = $this->entry($form);
        $profileUrl = CustomFormEntryResource::getUrl('index', ['tableFilters' => ['custom_form_id' => ['value' => $profile->id]]]);
        $applicationUrl = CustomFormEntryResource::getUrl('index', ['tableFilters' => ['custom_form_id' => ['value' => $form->id]]]);

        foreach ([[$profileEntry, $profileUrl, $applicationUrl], [$applicationEntry, $applicationUrl, $profileUrl]] as [$entry, $activeUrl, $inactiveUrl]) {
            $response = $this->get(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'))->assertOk();
            $document = new \DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $activeUrls = [];
            foreach ((new \DOMXPath($document))->query('//a[@aria-current="page"]/@href') as $href) {
                $activeUrls[] = $href->value;
            }
            $this->assertContains($activeUrl, $activeUrls);
            $this->assertNotContains($inactiveUrl, $activeUrls);
        }
    }

    public static function dynamicSubmitMethods(): array
    {
        return [['save'], ['saveAndCreateAnother']];
    }

    #[DataProvider('dynamicSubmitMethods')]
    public function test_dynamic_form_submission_also_opens_next_steps(string $method): void
    {
        $form = $this->form();
        $form->update(['submission_next_steps' => $this->settings()]);
        $component = Livewire::test(StudentDynamicFormPage::class, ['slug' => $form->slug])->assertSuccessful()
            ->set('data.answer', 'Dynamic answer')->call($method)->assertHasNoFormErrors();
        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $component->assertRedirect(NextStepsPage::getUrl(['record' => $entry->id], panel: 'app'));
    }

    private function settings(): array
    {
        return ['is_active' => true, 'heading_en' => 'Application received', 'heading_km' => 'បានទទួលពាក្យរបស់អ្នក',
            'continue_label_en' => 'Review application', 'continue_label_km' => 'ពិនិត្យពាក្យស្នើសុំ',
            'title_en' => 'Review your details', 'title_km' => 'សូមពិនិត្យ និងផ្ទៀងផ្ទាត់ព័ត៌មានឡើងវិញ',
            'message_en' => "Please check your documents.\nThen continue with your application.", 'message_km' => 'សូមពិនិត្យឯកសាររបស់អ្នក រួចបន្តជំហានបន្ទាប់។'];
    }

    private function form(): CustomForm
    {
        $form = CustomForm::query()->create(['name' => 'Admission form', 'slug' => 'admission-next-steps', 'is_active' => true, 'allowed_roles' => ['submission_applicant'], 'requires_payment' => false]);
        $this->addField($form);

        return $form;
    }

    private function addField(CustomForm $form): void
    {
        $section = $form->fields()->create(['name' => 'answers', 'label' => 'Application', 'type' => 'section', 'sort' => 1]);
        $form->fields()->create(['parent_id' => $section->id, 'name' => 'answer', 'label' => 'Answer', 'type' => 'text_input', 'required' => true]);
    }

    private function attachWorkflow(CustomForm $form): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Admission workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
            ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => 'admin']],
        ]]);
        $workflow->forms()->attach($form);
    }

    private function entry(CustomForm $form, string $status = 'pending'): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->create(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'data' => ['registration_status' => 'pending', 'answer' => 'Answer']]);
        if ($status !== 'pending') {
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $status, 'data' => json_encode(['registration_status' => $status, 'answer' => 'Answer'])]);
        }

        return $entry->refresh();
    }

    private function createPage(CustomForm $form)
    {
        $parameters = ['form_id' => (string) $form->id];

        return Livewire::withQueryParams($parameters)->test(CreateCustomFormEntry::class, $parameters)->assertSuccessful();
    }

    private function candidate(string $name): User
    {
        $user = $this->user('student', $name);
        $user->assignRole(Role::findOrCreate('candidate', 'web'), Role::findOrCreate('submission_applicant', 'web'));
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $profile = CustomForm::query()->firstOrCreate(['slug' => 'profile'], ['name' => 'Profile', 'is_active' => true]);
        // Historical profile: no frozen page yet, as on an installation before the snapshot migration.
        DB::table('custom_form_entries')->insert([
            'custom_form_id' => $profile->id, 'created_by' => $user->id,
            'review_status' => 'pending', 'data' => json_encode(['registration_status' => 'pending']),
        ]);

        return $user;
    }

    private function user(string $type, ?string $name = null): User
    {
        $name ??= $type.'_'.str()->random(10);

        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true, 'locale' => 'en']);
    }
}
