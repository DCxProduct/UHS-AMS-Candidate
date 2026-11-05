<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CandidateMenuOverview;
use App\Models\ClosingDate;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use App\Models\WorkflowNotification;
use App\Providers\Filament\AppPanelProvider;
use App\Support\CandidateApplicationContext;
use App\Support\CandidateApplicationLevels;
use App\Support\CandidateTypeResolver;
use App\Support\DashboardMetrics;
use App\Support\UserTypeOptions;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\CreateCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas\CustomFormEntryForm;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CandidateApplicationTypeSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const BACHELOR = 'national_entrance_exam_application_bachelor';

    private const ASSOCIATE = 'national_entrance_exam_application_associate';

    private const MASTER = 'national_exit_exam_application_master_of_science';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        CandidateApplicationLevels::options();
    }

    public function test_failed_bachelor_candidate_switches_and_submits_an_independent_associate_application_with_the_same_login(): void
    {
        $user = $this->candidate('failed-level-switch');
        $profile = $this->form('profile', 'candidate');
        $profileEntry = $this->entry($user, $profile, 'pending', ['registration_status' => 'pending', 'personal' => 'Shared profile']);
        $bachelor = $this->form(self::BACHELOR, self::BACHELOR);
        $associate = $this->form(self::ASSOCIATE, self::ASSOCIATE);
        $bachelorWorkflow = $this->workflow($bachelor, ['form_submission', 'review', 'payment', 'awaiting_results']);
        $associateWorkflow = $this->workflow($associate, ['form_submission', 'review']);
        $failed = $this->entry($user, $bachelor, 'failed', ['registration_status' => 'pending', 'application_note' => 'Original failed Bachelor application']);
        $paymentId = DB::table('payments')->insertGetId([
            'users_id' => $user->id, 'form_id' => $bachelor->id, 'custom_form_entry_id' => $failed->id,
            'status_payt' => 'paid', 'amount_kh' => 120000, 'datetime_pay' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $loginBefore = $user->getRawOriginal();
        $this->assertFalse(Schema::hasColumn('users', 'candidate_application_types'));
        $systemBefore = $user->linkedSystemUser()->getRawOriginal();
        $rolesBefore = $user->getRoleNames()->sort()->values()->all();
        $permissionsBefore = $user->getAllPermissions()->pluck('name')->sort()->values()->all();
        $profileBefore = $profileEntry->fresh()->getRawOriginal();
        $failedBefore = $failed->fresh()->getRawOriginal();
        $paymentBefore = (array) DB::table('payments')->find($paymentId);

        Livewire::test(Dashboard::class)->assertActionVisible('switch_application_level')
            ->callAction('switch_application_level', ['type' => self::ASSOCIATE])->assertHasNoActionErrors();

        $user->refresh();
        $this->assertSame($loginBefore, $user->getRawOriginal());
        $this->assertSame(Arr::except($systemBefore, ['roles', 'updated_at']), Arr::except($user->linkedSystemUser()->getRawOriginal(), ['roles', 'updated_at']));
        $this->assertSame([self::ASSOCIATE], $user->linkedSystemUser()->roles);
        $this->assertSame(self::ASSOCIATE, CandidateApplicationLevels::currentType($user));
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::resolve($user));
        $this->assertArrayHasKey(self::BACHELOR, CandidateApplicationLevels::switchOptions($user));
        $this->assertArrayNotHasKey(self::ASSOCIATE, CandidateApplicationLevels::switchOptions($user));
        $this->assertSame($rolesBefore, $user->getRoleNames()->sort()->values()->all());
        $this->assertSame($permissionsBefore, $user->getAllPermissions()->pluck('name')->sort()->values()->all());
        $this->assertSame($profileBefore, $profileEntry->fresh()->getRawOriginal());
        $this->assertSame($failedBefore, $failed->fresh()->getRawOriginal());
        $this->assertSame($paymentBefore, (array) DB::table('payments')->find($paymentId));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::forEntry($failed->fresh()));
        $this->assertSame($bachelorWorkflow->id, $failed->workflow_snapshot['workflow_id']);
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($bachelor));
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($associate));
        $this->get('/custom-form-entries?form_id='.$bachelor->id)->assertForbidden();
        $this->assertSame(UserTypeOptions::formatLabel(self::ASSOCIATE), (new ReflectionMethod(CandidateMenuOverview::class, 'getViewData'))->invoke(new CandidateMenuOverview)['eyebrow']);

        Livewire::withQueryParams(['form_id' => (string) $associate->id])->test(CreateCustomFormEntry::class, ['form_id' => (string) $associate->id])
            ->set('data.data.application_note', 'New Associate application')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))->assertHasNoFormErrors();
        $new = CustomFormEntry::query()->where('custom_form_id', $associate->id)->sole();
        $this->assertNotSame($failed->id, $new->id);
        $this->assertSame('pending', $new->review_status);
        $this->assertSame('New Associate application', $new->data['application_note']);
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::forEntry($new));
        $this->assertSame($associateWorkflow->id, $new->workflow_snapshot['workflow_id']);
        $newBefore = $new->fresh()->getRawOriginal();

        CandidateApplicationLevels::switchTo($user, self::BACHELOR);
        $this->assertSame(self::BACHELOR, CandidateApplicationLevels::currentType($user));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::resolve($user));
        $this->assertSame($failedBefore, $failed->fresh()->getRawOriginal());
        $this->assertSame($newBefore, $new->fresh()->getRawOriginal());
        $this->assertSame($profileBefore, $profileEntry->fresh()->getRawOriginal());
        $this->assertSame($paymentBefore, (array) DB::table('payments')->find($paymentId));
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($associate));
        $this->get('/custom-form-entries?form_id='.$bachelor->id)->assertOk();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('system_users', 1);
    }

    public function test_switch_freezes_legacy_generic_history_before_account_classification_changes(): void
    {
        $user = $this->candidate('legacy-level-switch');
        $user->linkedSystemUser()->update(['roles' => [self::BACHELOR, 'legacy-registration-marker']]);
        $generic = $this->form('legacy-generic', 'candidate');
        $originalData = ['application_note' => 'Old generic application', 'user_type' => self::ASSOCIATE];
        $id = DB::table('custom_form_entries')->insertGetId([
            'custom_form_id' => $generic->id, 'created_by' => $user->id, 'review_status' => 'failed',
            'data' => json_encode($originalData), 'created_at' => now()->subMonth(), 'updated_at' => now()->subWeek(),
        ]);
        $legacy = CustomFormEntry::query()->findOrFail($id);
        $before = $legacy->getRawOriginal();
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::forEntry($legacy));

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        $legacy->refresh();
        $this->assertSame([...$originalData, CandidateApplicationContext::DATA_KEY => self::BACHELOR], $legacy->data);
        $this->assertSame(Arr::except($before, ['data']), Arr::except($legacy->getRawOriginal(), ['data']));
        $this->assertSame(self::BACHELOR, CandidateTypeResolver::forEntry($legacy));
        $this->assertSame(self::ASSOCIATE, CandidateTypeResolver::resolve($user));
        $this->assertEqualsCanonicalizing([self::ASSOCIATE, 'legacy-registration-marker'], $user->linkedSystemUser()->roles);
        $this->assertSame(self::ASSOCIATE, $user->linkedSystemUser()->roles[0]);
        $frozen = $legacy->getRawOriginal();
        CandidateApplicationLevels::switchTo($user, self::BACHELOR);
        $this->assertSame($frozen, $legacy->fresh()->getRawOriginal());
    }

    public function test_navigation_dashboard_and_form_writes_only_allow_the_current_type_until_switching_back(): void
    {
        $user = $this->candidate('current-form-visibility');
        $profile = $this->form('profile', 'candidate');
        $this->entry($user, $profile, 'pending', ['registration_status' => 'pending', 'application_note' => 'Shared profile']);
        ClosingDate::query()->where('type', ClosingDate::customFormTypeKey($profile->id))->update(['status' => ClosingDate::STATUS_CLOSED]);
        $bachelor = $this->form(self::BACHELOR, self::BACHELOR);
        $associate = $this->form(self::ASSOCIATE, self::ASSOCIATE);
        $continuing = $this->form('continuing_bachelors_degree', 'continuing_bachelors_degree');
        $master = $this->form(self::MASTER, self::MASTER);
        $failed = $this->entry($user, $bachelor, 'failed', ['registration_status' => 'pending', 'application_note' => 'Failed Bachelor history']);
        $draft = $this->entry($user, $bachelor, 'draft', ['registration_status' => 'draft', 'application_note' => 'Existing Bachelor draft']);
        $failedBefore = $failed->fresh()->getRawOriginal();
        $draftBefore = $draft->fresh()->getRawOriginal();
        $rolesBefore = $user->getRoleNames()->sort()->values()->all();
        $this->assertSame([$bachelor->id], array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));

        CandidateApplicationLevels::switchTo($user, self::MASTER);

        $provider = new AppPanelProvider(app());
        $navAccess = new ReflectionMethod(AppPanelProvider::class, 'currentUserCanAccessDynamicForm');
        $this->assertSame([$master->id], array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));
        $this->assertSame($rolesBefore, $user->getRoleNames()->sort()->values()->all());
        $this->assertSame([self::MASTER], CandidateApplicationLevels::formRoleKeys($user));
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($profile));
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($master));
        $this->assertTrue($navAccess->invoke($provider, $master->id));
        $visibleUrls = array_map(fn ($item): string => $item->getUrl(), CustomFormEntryResource::getNavigationItems());
        $this->assertContains(DashboardMetrics::customFormEntryUrl($master->id), $visibleUrls);
        $this->assertContains(DashboardMetrics::customFormEntryUrl($profile->id), $visibleUrls);
        $dashboard = $this->get('/dashboard')->assertOk()->assertSee(DashboardMetrics::customFormEntryUrl($master->id));
        $this->get('/custom-form-entries/create?form_id='.$master->id)->assertOk();
        $this->get('/custom-form-entries/create?form_id='.$profile->id)->assertOk();
        Livewire::withQueryParams(['form_id' => (string) $master->id])->test(CreateCustomFormEntry::class, ['form_id' => (string) $master->id])
            ->set('data.data.application_note', 'Current Master draft')->callAction('save_draft')->assertHasNoFormErrors();
        $beforeWrites = CustomFormEntry::query()->orderBy('id')->get()->map->getRawOriginal()->all();

        foreach ([$bachelor, $associate, $continuing] as $oldForm) {
            $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($oldForm));
            $this->assertFalse($navAccess->invoke($provider, $oldForm->id));
            $this->assertNotContains(DashboardMetrics::customFormEntryUrl($oldForm->id), $visibleUrls);
            $dashboard->assertDontSee(DashboardMetrics::customFormEntryUrl($oldForm->id));
            $this->get('/custom-form-entries?form_id='.$oldForm->id)->assertForbidden();
            $this->get('/custom-form-entries/create?form_id='.$oldForm->id)->assertForbidden();
            foreach (['create', 'save_draft'] as $action) {
                $component = Livewire::withQueryParams(['form_id' => (string) $master->id])->test(CreateCustomFormEntry::class, ['form_id' => (string) $master->id])
                    ->set('form_id', (string) $oldForm->id)->set('data.data.application_note', 'Blocked historical form write');
                if ($action === 'create') {
                    $component->call('create')->assertForbidden();
                } else {
                    $component->call('mountAction', 'save_draft')->assertForbidden();
                }
            }
        }
        $this->assertSame($beforeWrites, CustomFormEntry::query()->orderBy('id')->get()->map->getRawOriginal()->all());

        CandidateApplicationLevels::switchTo($user, self::BACHELOR);

        $this->assertSame([$bachelor->id], array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($master));
        $this->assertSame($failedBefore, $failed->fresh()->getRawOriginal());
        $this->assertSame($draftBefore, $draft->fresh()->getRawOriginal());
        Livewire::withQueryParams(['form_id' => (string) $bachelor->id])->test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$failed, $draft]);
        Livewire::withQueryParams(['form_id' => (string) $bachelor->id, 'draft_id' => (string) $draft->id])->test(CreateCustomFormEntry::class, ['form_id' => (string) $bachelor->id, 'draft_id' => (string) $draft->id])
            ->assertSet('draftEntryId', $draft->id)->assertSet('data.data.application_note', 'Existing Bachelor draft');
    }

    public function test_unknown_staff_and_inactive_target_types_cannot_change_the_account(): void
    {
        $user = $this->candidate('invalid-level-switch');
        UserType::query()->where('key', self::ASSOCIATE)->update(['is_active' => false]);
        UserType::query()->firstOrCreate(['key' => 'admin'], ['label_en' => 'Staff', 'label_kh' => 'មន្ត្រី', 'is_active' => true]);
        $loginBefore = $user->getRawOriginal();
        $systemBefore = $user->linkedSystemUser()->getRawOriginal();

        foreach ([self::ASSOCIATE, 'admin', 'not-a-real-type'] as $type) {
            try {
                CandidateApplicationLevels::switchTo($user, $type);
                $this->fail('Invalid account type was accepted: '.$type);
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame($loginBefore, $user->fresh()->getRawOriginal());
            $this->assertSame($systemBefore, $user->linkedSystemUser()->fresh()->getRawOriginal());
        }
    }

    public function test_profile_options_follow_the_current_category_while_old_authorization_roles_remain(): void
    {
        $user = $this->candidate('profile-level-switch');
        $choices = ['high_school_diploma' => 'High school diploma', 'bachelor' => 'Bachelor'];
        $options = ['choices' => $choices];
        $field = (object) ['name' => 'culture_level'];
        $resolve = new ReflectionMethod(CustomFormEntryForm::class, 'profileSelectChoices');
        $this->assertSame(['high_school_diploma' => 'High school diploma'], $resolve->invoke(null, $field, $options, true));

        CandidateApplicationLevels::switchTo($user, 'national_exit_exam_application_master_of_science');

        $this->assertTrue($user->hasRole(self::BACHELOR));
        $this->assertSame($choices, $resolve->invoke(null, $field, $options, true));
        CandidateApplicationLevels::switchTo($user, self::BACHELOR);
        $this->assertSame(['high_school_diploma' => 'High school diploma'], $resolve->invoke(null, $field, $options, true));
    }

    public function test_inactive_candidates_and_staff_cannot_switch_candidate_type(): void
    {
        foreach (['inactive', 'staff', 'student-staff'] as $kind) {
            $user = $this->candidate('blocked-level-'.$kind);
            if ($kind === 'inactive') {
                $user->update(['is_active' => false]);
            } else {
                $user->assignRole(Role::findOrCreate('cashier', 'web'));
                if ($kind === 'staff') {
                    $user->update(['registration_type' => 'admin']);
                }
            }
            $this->actingAs($user->fresh());
            $before = $user->linkedSystemUser()->getRawOriginal();
            Livewire::test(Dashboard::class)->assertActionHidden('switch_application_level');
            try {
                CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);
                $this->fail('A staff or inactive account changed candidate type.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame($before, $user->linkedSystemUser()->fresh()->getRawOriginal());
        }
    }

    public function test_missing_or_inactive_linked_accounts_cannot_be_recreated_or_modified_by_switching(): void
    {
        foreach (['missing', 'inactive'] as $kind) {
            $user = $this->candidate('unavailable-linked-'.$kind);
            $linkedId = $user->linkedSystemUser()->getKey();
            if ($kind === 'missing') {
                DB::table('system_users')->where('id', $linkedId)->delete();
            } else {
                DB::table('system_users')->where('id', $linkedId)->update(['is_active' => false]);
            }
            $loginBefore = $user->fresh()->getRawOriginal();
            $systemsBefore = DB::table('system_users')->orderBy('id')->get()->toArray();
            $rolesBefore = $user->getRoleNames()->all();
            try {
                CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);
                $this->fail('Switching requires the existing active linked account.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('type', $exception->errors());
            }
            $this->assertSame($loginBefore, $user->fresh()->getRawOriginal());
            $this->assertEquals($systemsBefore, DB::table('system_users')->orderBy('id')->get()->toArray());
            $this->assertSame($rolesBefore, $user->fresh()->getRoleNames()->all());
        }
    }

    public function test_models_loaded_before_switch_preserve_frozen_known_and_unknown_history_on_later_saves(): void
    {
        $user = $this->candidate('stale-history-switch');
        $form = $this->form('generic-stale-history', 'candidate');
        $models = [];
        foreach (['unstamped', 'frozen-unknown'] as $kind) {
            $data = ['application_note' => $kind];
            if ($kind === 'frozen-unknown') {
                $data[CandidateApplicationContext::DATA_KEY] = null;
            }
            $id = DB::table('custom_form_entries')->insertGetId([
                'custom_form_id' => $form->id, 'created_by' => $user->id, 'review_status' => 'failed',
                'data' => json_encode($data), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $models[$kind] = CustomFormEntry::query()->findOrFail($id);
        }

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        foreach ($models as $kind => $stale) {
            $stale->data = [...$stale->data, 'application_note' => 'Later legitimate edit', CandidateApplicationContext::DATA_KEY => self::ASSOCIATE];
            $stale->save();
            $saved = $stale->fresh();
            $expected = $kind === 'unstamped' ? self::BACHELOR : null;
            $this->assertArrayHasKey(CandidateApplicationContext::DATA_KEY, $saved->data);
            $this->assertSame($expected, $saved->data[CandidateApplicationContext::DATA_KEY]);
            $this->assertSame($expected, CandidateTypeResolver::forEntry($saved));
            $this->assertSame('failed', $saved->review_status);
        }
    }

    #[DataProvider('editableHistoryWrites')]
    public function test_old_edit_urls_cannot_write_an_ineligible_type_and_switchback_restores_the_existing_flow(string $status, string $action): void
    {
        $user = $this->candidate('old-edit-'.$status.'-'.$action);
        $profile = $this->form('profile', 'candidate');
        $this->entry($user, $profile, 'pending', ['registration_status' => 'pending', 'application_note' => 'Profile']);
        $bachelor = $this->form(self::BACHELOR, self::BACHELOR);
        $entry = $this->entry($user, $bachelor, $status, ['registration_status' => $status, 'application_note' => 'Historical Bachelor data']);
        $before = $entry->fresh()->getRawOriginal();
        CandidateApplicationLevels::switchTo($user, self::MASTER);
        $component = Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertSuccessful()->set('data.data.application_note', 'Forbidden historical overwrite');
        if ($action === 'save') {
            $component->call('save')->assertForbidden();
        } else {
            $component->call('mountAction', 'save_draft')->assertForbidden();
        }
        $this->assertSame($before, $entry->fresh()->getRawOriginal());

        CandidateApplicationLevels::switchTo($user, self::BACHELOR);
        // Existing resubmissions keep their old deadline behavior.
        ClosingDate::query()->where('type', ClosingDate::customFormTypeKey($bachelor->id))->update(['status' => ClosingDate::STATUS_CLOSED]);
        $component = Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->set('data.data.application_note', 'Allowed Bachelor edit');
        if ($action === 'save') {
            $component->call('save')->assertHasNoFormErrors();
            $this->assertSame('pending', $entry->fresh()->review_status);
        } else {
            $component->callAction('save_draft')->assertHasNoFormErrors();
            $this->assertSame('draft', $entry->fresh()->review_status);
        }
        $this->assertSame('Allowed Bachelor edit', $entry->fresh()->data['application_note']);
        $this->assertSame($before['workflow_snapshot'], $entry->fresh()->getRawOriginal('workflow_snapshot'));
    }

    public static function editableHistoryWrites(): array
    {
        return [
            'failed save' => ['failed', 'save'],
            'draft save' => ['draft', 'save'],
            'draft save as draft' => ['draft', 'save_draft'],
        ];
    }

    public function test_current_type_edit_save_and_draft_save_cannot_reassign_the_entry_form_or_owner(): void
    {
        $user = $this->candidate('current-edit-owner');
        $otherOwner = User::query()->create(['registration_type' => 'student', 'name' => 'Other owner', 'username' => 'other-edit-owner', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $profile = $this->form('profile', 'candidate');
        $this->entry($user, $profile, 'pending', ['registration_status' => 'pending']);
        $master = $this->form(self::MASTER, self::MASTER);
        $bachelor = $this->form(self::BACHELOR, self::BACHELOR);
        CandidateApplicationLevels::switchTo($user, self::MASTER);
        foreach (['save', 'save_draft'] as $action) {
            $entry = $this->entry($user, $master, 'draft', ['registration_status' => 'draft', 'application_note' => 'Own Master draft']);
            $snapshot = $entry->getRawOriginal('workflow_snapshot');
            $component = Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $entry->id])
                ->set('data.custom_form_id', $bachelor->id)
                ->set('data.created_by', $otherOwner->id)
                ->set('data.data.application_note', 'Updated Master '.$action);
            if ($action === 'save') {
                $component->call('save')->assertHasNoFormErrors();
            } else {
                $component->callAction('save_draft')->assertHasNoFormErrors();
            }
            $saved = $entry->fresh();
            $this->assertSame($master->id, $saved->custom_form_id);
            $this->assertSame($user->id, $saved->created_by);
            $this->assertSame('Updated Master '.$action, $saved->data['application_note']);
            $this->assertSame(self::MASTER, CandidateTypeResolver::forEntry($saved));
            $this->assertSame($snapshot, $saved->getRawOriginal('workflow_snapshot'));
        }
    }

    private function candidate(string $username): User
    {
        $user = User::query()->create(['registration_type' => 'student', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $role = Role::findOrCreate('candidate', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $user->assignRole($role, Role::findOrCreate(self::BACHELOR, 'web'));
        SystemUser::query()->create(['name' => $username, 'username' => $username, 'email' => $user->email, 'password' => 'system-password', 'roles' => [self::BACHELOR], 'is_active' => true]);
        $user->refresh();
        $this->actingAs($user);

        return $user;
    }

    private function form(string $slug, string $role): CustomForm
    {
        $form = CustomForm::query()->create(['name' => $slug, 'slug' => str_replace('_', '-', $slug), 'is_active' => true, 'allowed_roles' => [$role]]);
        $form->fields()->create(['name' => 'application_note', 'label' => 'Application details', 'type' => 'text_input', 'required' => true]);
        ClosingDate::query()->create(['name' => 'Open '.$slug, 'type' => ClosingDate::customFormTypeKey($form->id), 'status' => ClosingDate::STATUS_OPEN, 'start_date' => today(), 'end_date' => today()->addWeek()]);

        return $form;
    }

    private function entry(User $user, CustomForm $form, string $status, array $data): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $user->id, 'review_status' => $status, 'data' => $data]);
    }

    private function workflow(CustomForm $form, array $types): WorkflowNotification
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Workflow '.$form->id, 'stages' => array_map(fn (string $type): array => ['type' => 'stage', 'data' => ['stage_name' => $type, 'stage_type' => $type, 'responsible_role' => ['admin']]], $types)]);
        $workflow->forms()->attach($form);

        return $workflow;
    }
}
