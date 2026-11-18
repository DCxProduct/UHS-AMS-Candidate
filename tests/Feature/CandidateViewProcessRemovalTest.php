<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateViewProcessRemovalTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_PERMISSION = 'ViewApplicationStage:CustomFormEntry';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_sync_retires_only_the_web_view_process_permission_and_its_grants_idempotently(): void
    {
        config()->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $candidate = $this->user('retired-process-candidate', 'student');
        $role = Role::findOrCreate('retired-process-reader', 'web');
        $retired = Permission::findOrCreate(self::REMOVED_PERMISSION, 'web');
        $keptNames = ['ViewAny:CustomFormEntry', 'View:CustomFormEntry', 'ViewPdf:CustomFormEntry', 'Create:CustomFormEntry', 'Update:CustomFormEntry'];
        $kept = collect($keptNames)->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));
        $role->givePermissionTo([$retired, ...$kept->all()]);
        $candidate->assignRole($role);
        $candidate->givePermissionTo([$retired, ...$kept->all()]);
        $apiRole = Role::findOrCreate('retired-process-api-reader', 'api');
        $apiPermission = Permission::findOrCreate(self::REMOVED_PERMISSION, 'api');
        $apiRole->givePermissionTo($apiPermission);
        DB::table(config('permission.table_names.model_has_permissions'))->insert([
            'permission_id' => $apiPermission->id, 'model_type' => $candidate->getMorphClass(), 'model_id' => $candidate->id,
        ]);

        FilamentActionPermissions::sync();
        FilamentActionPermissions::sync();

        $this->assertDatabaseMissing('permissions', ['name' => self::REMOVED_PERMISSION, 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['id' => $apiPermission->id, 'name' => self::REMOVED_PERMISSION, 'guard_name' => 'api']);
        $this->assertDatabaseMissing(config('permission.table_names.role_has_permissions'), ['permission_id' => $retired->id]);
        $this->assertDatabaseMissing(config('permission.table_names.model_has_permissions'), ['permission_id' => $retired->id]);
        $this->assertEqualsCanonicalizing($keptNames, $role->fresh()->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing([...$kept->pluck('id')->all(), $apiPermission->id], $candidate->fresh()->permissions->pluck('id')->all());
        $this->assertSame([$apiPermission->id], $apiRole->fresh()->permissions->pluck('id')->all());
        $this->assertNotContains(self::REMOVED_PERMISSION, FilamentActionPermissions::all());
        $this->assertNotContains('view_application_stage', FilamentActionPermissions::resourceActions()[CustomFormEntryResource::class]);
    }

    public function test_role_form_omits_view_process_in_both_languages_while_retaining_existing_application_rights(): void
    {
        $admin = $this->user('process-role-admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $target = Role::findOrCreate('process-role-target', 'web');
        $target->givePermissionTo(Permission::findOrCreate(self::REMOVED_PERMISSION, 'web'));
        FilamentActionPermissions::sync();

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditRole::class, ['record' => $target->getRouteKey()]);
            $field = collect($component->instance()->form->getFlatFields(withHidden: true))
                ->first(fn ($field): bool => $field instanceof CheckboxList && $field->getName() === CustomFormEntryResource::class);
            $this->assertNotNull($field);
            $this->assertArrayNotHasKey(self::REMOVED_PERMISSION, $field->getOptions());
            $this->assertSame(__('candidate_entrance_statistics.actions.view_data'), (string) $field->getOptions()['View:CustomFormEntry']);
            foreach (['ViewAny', 'View', 'ViewPdf', 'Create', 'Update'] as $action) {
                $this->assertArrayHasKey($action.':CustomFormEntry', $field->getOptions());
            }
        }
    }

    public function test_stale_candidate_grant_cannot_mount_removed_action_and_progress_remains_unchanged(): void
    {
        $candidate = $this->user('process-button-candidate', 'student');
        $role = Role::findOrCreate('candidate', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'),
            Permission::findOrCreate('View:CustomFormEntry', 'web'),
            Permission::findOrCreate(self::REMOVED_PERMISSION, 'web'),
        ]);
        $candidate->assignRole($role);
        $this->actingAs($candidate);
        $form = CustomForm::query()->create(['name' => 'Process replacement', 'slug' => 'process-replacement', 'is_active' => true, 'schema' => []]);
        $stages = [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submitted', 'stage_type' => 'form_submission']],
            ['type' => 'stage', 'data' => ['stage_name' => 'Reviewing application', 'stage_type' => 'review']],
        ];
        $entry = CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id, 'candidate_id' => 'MED260002', 'review_status' => 'pending',
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => true, 'stages' => $stages],
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'workflow_progress' => [
                'version' => 1, 'submitted' => true, 'reviewed' => false, 'current_index' => 1, 'current_type' => 'review',
                'completed_at' => ['0' => '2026-11-01T09:00:00+00:00'], 'finished' => false,
            ]],
        ]));
        $before = $entry->fresh()->getRawOriginal();
        $notifications = DB::table('notifications')->count();
        $component = Livewire::test(ListCustomFormEntries::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionDoesNotExist('view_application_stage', record: $entry);
        $columns = $component->instance()->getTable()->getColumns();
        $this->assertSame(['row_number', 'workflow_progress', 'candidate_id'], array_slice(array_keys($columns), 0, 3));
        $progress = $columns['workflow_progress']->record($entry)->getState();
        $this->assertSame(2, $progress['total']);
        $this->assertSame(1, $progress['completed']);
        $this->assertSame('Reviewing application', $progress['label']);
        $component->call('mountAction', 'view_application_stage', [], ['table' => true, 'recordKey' => $entry->id])
            ->assertSet('mountedActions', [])->call('callMountedAction');
        $this->assertSame($progress, $columns['workflow_progress']->record($entry->fresh())->getState());
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame($notifications, DB::table('notifications')->count());
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function user(string $username, string $type): User
    {
        return User::query()->create([
            'registration_type' => $type, 'name' => $username, 'username' => $username, 'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
