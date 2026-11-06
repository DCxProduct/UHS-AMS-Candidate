<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\FilamentActionPermissions;
use App\Support\WorkflowStageMessages;
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

class OriginalApplicationReceivedRemovalTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVED_PERMISSION = 'OriginalApplicationReceived:CustomFormEntry';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $this->admin = $this->user('received-removal-admin');
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->admin);
    }

    public function test_removed_action_cannot_be_invoked_and_legacy_flags_do_not_override_status_or_workflow(): void
    {
        CustomFormEntry::creating(static function (CustomFormEntry $entry): void {
            if (is_array($entry->workflow_snapshot)) {
                $entry->workflow_snapshot = [...$entry->workflow_snapshot, 'schema_version' => 1];
            }
        });
        $form = CustomForm::query()->create(['name' => 'Removal regression', 'slug' => 'received-removal-regression', 'is_active' => true]);
        WorkflowNotification::query()->create([
            'name' => 'Normal review and payment',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin']]],
                ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment', 'responsible_role' => ['admin']]],
            ],
        ])->forms()->attach($form);
        $entries = [];
        foreach (['pending', 'approved', 'draft', 'rejected'] as $status) {
            $entries[$status] = CustomFormEntry::query()->forceCreate([
                'custom_form_id' => $form->id, 'created_by' => $this->admin->id, 'review_status' => $status,
                'data' => ['registration_status' => $status, 'original_application_received' => true, 'answer' => 'Preserved '.$status],
            ]);
        }
        // An old stored grant must not restore the removed action.
        Permission::findOrCreate(self::REMOVED_PERMISSION, 'web');
        $before = DB::table('custom_form_entries')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$entries['pending'], $entries['approved'], $entries['rejected']])
            ->assertTableActionDoesNotExist('original_application_received', record: $entries['pending']);
        $column = $component->instance()->getTable()->getColumn('review_status');
        foreach ($entries as $status => $entry) {
            $this->assertSame($status, $column->record($entry)->getState());
            $this->assertSame(match ($status) {
                'pending' => WorkflowStageType::Review,
                'approved' => WorkflowStageType::Payment,
                'rejected' => WorkflowStageType::Rejected,
                default => null,
            }, WorkflowStageMessages::stageTypeFor($entry));
        }
        $component->call('mountAction', 'original_application_received', [], ['table' => true, 'recordKey' => $entries['pending']->id])
            ->assertSet('mountedActions', [])->call('callMountedAction');
        $this->assertSame($before, DB::table('custom_form_entries')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_permission_sync_removes_only_the_retired_permission_and_its_grants_across_guards_idempotently(): void
    {
        config()->set('auth.guards.api', ['driver' => 'session', 'provider' => 'users']);
        $recipient = $this->user('retired-permission-recipient');
        $retiredIds = [];
        $retainedIds = [];
        foreach (['web', 'api'] as $guard) {
            $retired = Permission::findOrCreate(self::REMOVED_PERMISSION, $guard);
            $retained = Permission::findOrCreate('View:CustomFormEntry', $guard);
            $role = Role::findOrCreate('permission-cleanup-'.$guard, $guard);
            $role->givePermissionTo([$retired, $retained]);
            foreach ([$retired, $retained] as $permission) {
                DB::table(config('permission.table_names.model_has_permissions'))->insert([
                    'permission_id' => $permission->id, 'model_type' => $recipient->getMorphClass(), 'model_id' => $recipient->id,
                ]);
            }
            $retiredIds[] = $retired->id;
            $retainedIds[] = $retained->id;
        }

        FilamentActionPermissions::sync();
        FilamentActionPermissions::sync();

        $this->assertFalse(Permission::query()->where('name', self::REMOVED_PERMISSION)->exists());
        $this->assertFalse(DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $retiredIds)->exists());
        $this->assertFalse(DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $retiredIds)->exists());
        $this->assertSame(2, Permission::query()->whereKey($retainedIds)->count());
        $this->assertSame(2, DB::table(config('permission.table_names.role_has_permissions'))->whereIn('permission_id', $retainedIds)->whereIn('role_id', Role::query()->where('name', 'like', 'permission-cleanup-%')->pluck('id'))->count());
        $this->assertSame(2, DB::table(config('permission.table_names.model_has_permissions'))->whereIn('permission_id', $retainedIds)->where('model_id', $recipient->id)->count());
        $this->assertNotContains(self::REMOVED_PERMISSION, FilamentActionPermissions::all());
        $this->assertNotContains('original_application_received', FilamentActionPermissions::resourceActions()[CustomFormEntryResource::class]);
    }

    public function test_role_permission_checkboxes_omit_the_removed_action_in_english_and_khmer(): void
    {
        $target = Role::findOrCreate('received-removal-target', 'web');
        $target->givePermissionTo(Permission::findOrCreate(self::REMOVED_PERMISSION, 'web'));
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditRole::class, ['record' => $target->getRouteKey()]);
            $field = collect($component->instance()->form->getFlatFields(withHidden: true))
                ->first(fn ($field): bool => $field instanceof CheckboxList && $field->getName() === CustomFormEntryResource::class);
            $this->assertNotNull($field);
            $this->assertArrayNotHasKey(self::REMOVED_PERMISSION, $field->getOptions());
            $this->assertArrayHasKey('ViewApplicationStage:CustomFormEntry', $field->getOptions());
            $this->assertArrayHasKey('ViewPdf:CustomFormEntry', $field->getOptions());
        }
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
