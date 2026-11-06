<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\ClosingDate;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use App\Models\WorkflowNotification;
use App\Support\CandidateApplicationLevels;
use App\Support\UserTypeOptions;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\CreateCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormAuthorization;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateApplicationDraftIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $profile;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        $role = Role::findOrCreate('candidate', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $this->candidate = $this->user('multi-application-candidate');
        $this->candidate->assignRole($role, Role::findOrCreate('draft_applicant', 'web'), Role::findOrCreate('national_entrance_exam_application_bachelor', 'web'));
        $this->actingAs($this->candidate);
        $this->profile = $this->form('profile');
        $this->entry($this->profile, status: 'pending', note: 'Shared profile');
    }

    public function test_automatic_draft_loading_and_saving_keep_two_selections_on_the_same_form_separate(): void
    {
        $form = $this->form('shared-application');
        $bachelor = $this->entry($form, selection: 'bachelor', note: 'Bachelor draft');
        $bachelorBefore = $bachelor->fresh()->getRawOriginal();
        $this->page($form, 'associate')->assertSet('draftEntryId', null)
            ->set('data.data.application_note', 'Associate draft')->callAction('save_draft')->assertHasNoFormErrors();
        $associate = CustomFormEntry::query()->where('custom_form_id', $form->id)->where('data->form_selection', 'associate')->sole();
        $this->assertNotSame($bachelor->id, $associate->id);
        $this->page($form, 'bachelor')->assertSet('draftEntryId', $bachelor->getKey())->assertSet('data.data.application_note', 'Bachelor draft');
        $this->page($form, 'associate')->assertSet('draftEntryId', $associate->getKey())
            ->set('data.data.application_note', 'Updated associate draft')
            ->set('data.data.form_selection', 'bachelor')
            ->callAction('save_draft')->assertHasNoFormErrors();
        $this->assertSame($bachelorBefore, $bachelor->fresh()->getRawOriginal());
        $this->assertSame('Updated associate draft', data_get($associate->fresh()->data, 'application_note'));
        $this->assertSame('associate', data_get($associate->fresh()->data, 'form_selection'));
        $this->assertSame(2, CustomFormEntry::query()->where('custom_form_id', $form->id)->count());
    }

    public function test_explicit_draft_ids_cannot_load_submitted_other_owner_other_form_or_other_selection_entries(): void
    {
        $form = $this->form('draft-id-application');
        $otherForm = $this->form('other-draft-application');
        $otherOwner = $this->user('other-draft-owner');
        $invalid = [
            $this->entry($form, 'pending', 'bachelor', 'Submitted private data'),
            $this->entry($form, 'draft', 'bachelor', 'Other owner private data', $otherOwner),
            $this->entry($otherForm, 'draft', 'bachelor', 'Other form private data'),
            $this->entry($form, 'draft', 'associate', 'Other selection private data'),
        ];
        $staleStatus = $this->entry($form, 'pending', 'bachelor', 'Submitted with stale draft marker');
        $staleStatus->forceFill(['data' => [...$staleStatus->data, 'registration_status' => 'draft']])->save();
        $invalid[] = $staleStatus;
        foreach ($invalid as $entry) {
            $before = $entry->fresh()->getRawOriginal();
            $component = $this->page($form, 'bachelor', $entry->getKey())->assertSet('draftEntryId', null);
            $this->assertNotSame(data_get($entry->data, 'application_note'), $component->get('data.data.application_note'));
            $this->assertSame($before, $entry->fresh()->getRawOriginal());
        }
        $valid = $this->entry($form, selection: 'bachelor');
        $this->page($form, 'bachelor', $valid->getKey())->assertSet('draftEntryId', $valid->getKey());
    }

    public function test_the_loaded_draft_identifier_cannot_be_changed_through_livewire_state(): void
    {
        $form = $this->form('locked-draft-application');
        $draft = $this->entry($form, selection: 'bachelor');
        $victim = $this->entry($form, selection: 'associate', note: 'Protected other draft');
        $before = $victim->fresh()->getRawOriginal();
        $component = $this->page($form, 'bachelor', $draft->getKey());
        $this->withoutExceptionHandling();
        try {
            $component->set('draftEntryId', $victim->getKey());
            $this->fail('The server-selected draft identifier must be locked.');
        } catch (CannotUpdateLockedPropertyException $exception) {
            $this->assertSame('draftEntryId', $exception->property);
        }
        $this->assertSame($before, $victim->fresh()->getRawOriginal());
    }

    public function test_draft_save_and_submit_recheck_status_and_selection_before_writing(): void
    {
        $form = $this->form('rechecked-draft-application');
        $otherForm = $this->form('rechecked-other-application');
        foreach (['save_draft', 'submit'] as $action) {
            foreach (['status', 'selection', 'form'] as $change) {
                $draft = $this->entry($form, selection: 'bachelor', note: $action.' '.$change);
                $component = $this->page($form, 'bachelor', $draft->getKey());
                if ($change === 'status') {
                    $draft->forceFill(['review_status' => 'pending', 'data' => [...$draft->data, 'registration_status' => 'pending']])->save();
                } elseif ($change === 'selection') {
                    $component->set('form_selection', 'associate');
                } else {
                    $component->set('form_id', (string) $otherForm->id);
                }
                $before = $draft->fresh()->getRawOriginal();
                $component->set('data.data.application_note', 'Forbidden overwrite');
                if ($action === 'submit') {
                    $component->mountAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))->call('callMountedAction')->assertNotFound();
                } else {
                    $component->call('mountAction', 'save_draft')->assertNotFound();
                }
                $this->assertSame($before, $draft->fresh()->getRawOriginal());
            }
        }
    }

    public function test_profile_draft_without_a_selection_keeps_the_existing_resume_and_save_flow(): void
    {
        $draft = $this->entry($this->profile, note: 'Profile draft');
        $beforeCount = CustomFormEntry::query()->where('custom_form_id', $this->profile->id)->count();
        $this->page($this->profile, draftId: $draft->getKey())->assertSet('draftEntryId', $draft->getKey())
            ->set('data.data.application_note', 'Updated profile draft')->callAction('save_draft')->assertHasNoFormErrors();
        $this->assertSame('Updated profile draft', data_get($draft->fresh()->data, 'application_note'));
        $this->assertSame('draft', $draft->fresh()->review_status);
        $this->assertSame($beforeCount, CustomFormEntry::query()->where('custom_form_id', $this->profile->id)->count());
    }

    #[DataProvider('unauthorizedTargetWrites')]
    public function test_tampered_target_form_cannot_create_or_save_a_new_draft(string $restriction, string $action): void
    {
        $allowed = $this->form('allowed-application');
        $target = $this->form('unauthorized-application', $restriction === 'unenrolled' ? 'unenrolled_applicant' : 'national_entrance_exam_application_bachelor');

        if ($restriction === 'allowlist') {
            Role::findByName('draft_applicant', 'web')->forceFill([
                'restrict_custom_form_access' => true,
                'allowed_custom_form_ids' => [$this->profile->id, $allowed->id],
            ])->save();
            $this->actingAs($this->candidate->fresh());
        }

        $this->get('/custom-form-entries/create?form_id='.$target->id)->assertForbidden();
        $before = CustomFormEntry::query()->get()->map->getRawOriginal()->all();
        $component = $this->page($allowed)
            ->set('form_id', (string) $target->id)
            ->set('data.data.application_note', 'Unauthorized target data');

        if ($action === 'create') {
            $component->call('create')->assertForbidden();
        } else {
            $component->call('mountAction', 'save_draft')->assertForbidden();
        }

        $this->assertSame($before, CustomFormEntry::query()->get()->map->getRawOriginal()->all());
        $this->assertDatabaseMissing('custom_form_entries', ['custom_form_id' => $target->id]);
    }

    public static function unauthorizedTargetWrites(): array
    {
        return [
            'unenrolled form submission' => ['unenrolled', 'create'],
            'unenrolled form draft' => ['unenrolled', 'save_draft'],
            'excluded form submission' => ['allowlist', 'create'],
            'excluded form draft' => ['allowlist', 'save_draft'],
        ];
    }

    public function test_both_write_paths_recheck_active_state_submission_deadline_and_target_create_override(): void
    {
        $allowed = $this->form('open-write-context');

        foreach (['inactive', 'expired', 'create-denied'] as $restriction) {
            $target = $this->form('write-target-'.$restriction);
            if ($restriction === 'inactive') {
                $target->update(['is_active' => false]);
            } elseif ($restriction === 'expired') {
                ClosingDate::query()->where('type', ClosingDate::customFormTypeKey($target->id))
                    ->update(['start_date' => today()->subWeek(), 'end_date' => today()->subDay()]);
            } else {
                CustomFormAuthorization::query()->create([
                    'custom_form_id' => $target->id,
                    'panel' => 'student',
                    'allowed_users' => [$this->candidate->id],
                    'isolate_user_data' => true,
                    'permissions' => ['view_any', 'view'],
                ]);
            }

            foreach (['create', 'save_draft'] as $action) {
                $component = $this->page($allowed)
                    ->set('form_id', (string) $target->id)
                    ->set('data.data.application_note', 'Forbidden '.$restriction.' '.$action);
                if ($action === 'create') {
                    $component->call('create')->assertForbidden();
                } else {
                    $component->call('mountAction', 'save_draft')->assertForbidden();
                }
                $this->assertDatabaseMissing('custom_form_entries', ['custom_form_id' => $target->id]);
            }
        }
    }

    public function test_one_account_can_submit_bachelor_and_associate_applications_with_independent_data_status_snapshots_and_visibility(): void
    {
        $bachelorType = 'national_entrance_exam_application_bachelor';
        $associateType = 'national_entrance_exam_application_associate';
        foreach (UserTypeOptions::defaultRecords() as $record) {
            if (in_array($record['key'], [$bachelorType, $associateType], true)) {
                UserType::query()->firstOrCreate(['key' => $record['key']], $record);
            }
        }
        $this->candidate->assignRole(Role::findOrCreate($bachelorType, 'web'));
        SystemUser::query()->create(['name' => $this->candidate->name, 'username' => $this->candidate->username, 'password' => 'password', 'roles' => [$bachelorType], 'is_active' => true]);
        $rolesBefore = $this->candidate->getRoleNames()->sort()->values()->all();
        $bachelor = $this->form(str_replace('_', '-', $bachelorType), $bachelorType);
        $associate = $this->form(str_replace('_', '-', $associateType), $associateType);
        $bachelorWorkflow = $this->workflow($bachelor, ['form_submission', 'review', 'payment']);
        $associateWorkflow = $this->workflow($associate, ['form_submission', 'awaiting_results']);
        $this->get('/custom-form-entries/create?form_id='.$associate->id)->assertForbidden();
        $this->assertSame($rolesBefore, $this->candidate->fresh()->getRoleNames()->sort()->values()->all());
        $entries = [];
        foreach ([[$bachelor, $bachelorType, 'Bachelor application data'], [$associate, $associateType, 'Associate application data']] as [$form, $type, $note]) {
            CandidateApplicationLevels::switchTo($this->candidate, $type);
            $this->page($form)->set('data.data.application_note', $note)
                ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))->assertHasNoFormErrors();
            $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->where('created_by', $this->candidate->id)->sole();
            $this->assertSame($type, data_get($entry->data, 'application_candidate_type'));
            $this->assertSame($note, data_get($entry->data, 'application_note'));
            $entries[] = $entry;
        }
        [$first, $second] = $entries;
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($bachelorWorkflow->id, $first->workflow_snapshot['workflow_id']);
        $this->assertSame($associateWorkflow->id, $second->workflow_snapshot['workflow_id']);
        $secondBefore = $second->fresh()->getRawOriginal();
        $snapshot = $first->workflow_snapshot;
        $admin = $this->user('application-reviewer', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin)->post('/admin/custom-form-entries/'.$first->id.'/approve')->assertRedirect();
        $this->assertSame('approved', $first->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($first->fresh()));
        $this->assertSame($snapshot, $first->fresh()->workflow_snapshot);
        $this->assertSame($secondBefore, $second->fresh()->getRawOriginal());
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($second->fresh()));
        $outside = $this->entry($bachelor, status: 'pending', owner: $this->user('outside-application-owner'));
        $this->actingAs($this->candidate);
        CandidateApplicationLevels::switchTo($this->candidate, $bachelorType);
        Livewire::withQueryParams(['form_id' => (string) $bachelor->id])->test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$first])->assertCanNotSeeTableRecords([$second, $outside]);
        CandidateApplicationLevels::switchTo($this->candidate, $associateType);
        Livewire::withQueryParams(['form_id' => (string) $associate->id])->test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$second])->assertCanNotSeeTableRecords([$first, $outside]);
        Role::findByName('draft_applicant', 'web')->forceFill(['restrict_custom_form_access' => true, 'allowed_custom_form_ids' => [$this->profile->id, $bachelor->id]])->save();
        $this->actingAs($this->candidate->fresh())->get('/custom-form-entries?form_id='.$associate->id)->assertForbidden();
        CandidateApplicationLevels::switchTo($this->candidate, $bachelorType);
        $this->get('/custom-form-entries?form_id='.$bachelor->id)->assertOk();
    }

    private function page(CustomForm $form, ?string $selection = null, ?int $draftId = null)
    {
        $parameters = ['form_id' => (string) $form->getKey()];
        if ($selection !== null) {
            $parameters['form_selection'] = $selection;
        }
        if ($draftId !== null) {
            $parameters['draft_id'] = (string) $draftId;
        }

        return Livewire::withQueryParams($parameters)->test(CreateCustomFormEntry::class, $parameters)->assertSuccessful();
    }

    private function form(string $slug, string $role = 'national_entrance_exam_application_bachelor'): CustomForm
    {
        $form = CustomForm::query()->create(['name' => $slug, 'slug' => $slug, 'is_active' => true, 'allowed_roles' => [$role]]);
        $form->fields()->create(['name' => 'application_note', 'label' => 'Application details', 'type' => 'text_input', 'required' => true]);
        $form->fields()->create(['name' => 'form_selection', 'label' => 'Selection', 'type' => 'text_input']);
        ClosingDate::query()->create(['name' => 'Open '.$slug, 'type' => ClosingDate::customFormTypeKey($form->id), 'status' => ClosingDate::STATUS_OPEN, 'start_date' => today(), 'end_date' => today()->addWeek()]);

        return $form;
    }

    private function entry(CustomForm $form, string $status = 'draft', ?string $selection = null, string $note = 'Draft data', ?User $owner = null): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => ($owner ?? $this->candidate)->id, 'review_status' => $status, 'data' => ['registration_status' => $status, 'form_selection' => $selection, 'application_note' => $note]]);
    }

    private function workflow(CustomForm $form, array $types): WorkflowNotification
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Workflow '.$form->slug, 'stages' => array_map(fn (string $type): array => ['type' => 'stage', 'data' => ['stage_name' => $form->slug.' '.$type, 'stage_type' => $type, 'responsible_role' => ['admin']]], $types)]);
        $workflow->forms()->attach($form);

        return $workflow;
    }

    private function user(string $username, string $registrationType = 'student'): User
    {
        return User::query()->create(['name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'registration_type' => $registrationType, 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
    }
}
