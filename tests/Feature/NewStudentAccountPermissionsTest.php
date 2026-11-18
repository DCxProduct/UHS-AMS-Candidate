<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Models\Role;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewStudentAccountPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = [
        'ViewAny:NewStudentAccount', 'Create:NewStudentAccount', 'Update:NewStudentAccount',
        'ActivateAccount:NewStudentAccount', 'DeactivateAccount:NewStudentAccount',
        'ArchiveAccount:NewStudentAccount', 'RestoreAccount:NewStudentAccount',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        Http::fake();
        Mail::fake();
        FilamentActionPermissions::sync();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_sync_retires_view_delete_and_recovery_grants_without_changing_current_grants_or_other_guards(): void
    {
        $role = Role::findOrCreate('new-student-legacy-updater', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('View:NewStudentAccount', 'web'),
            Permission::findOrCreate('DeleteAny:NewStudentAccount', 'web'),
            Permission::findOrCreate('Update:NewStudentAccount', 'web'),
            Permission::findOrCreate('Delete:NewStudentAccount', 'web'),
            Permission::findOrCreate('RestoreDeletedAccount:NewStudentAccount', 'web'),
        ]);
        foreach (['View:NewStudentAccount', 'Delete:NewStudentAccount', 'DeleteAny:NewStudentAccount', 'RestoreDeletedAccount:NewStudentAccount'] as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        FilamentActionPermissions::sync();
        FilamentActionPermissions::sync();

        foreach (['View:NewStudentAccount', 'Delete:NewStudentAccount', 'DeleteAny:NewStudentAccount', 'RestoreDeletedAccount:NewStudentAccount'] as $permission) {
            $this->assertDatabaseMissing('permissions', ['name' => $permission, 'guard_name' => 'web']);
            $this->assertDatabaseHas('permissions', ['name' => $permission, 'guard_name' => 'api']);
        }
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, Permission::query()
            ->where('guard_name', 'web')->where('name', 'like', '%:NewStudentAccount')->pluck('name')->all());
        $this->assertSame(['Update:NewStudentAccount'], $role->fresh()->permissions->pluck('name')->all());
    }

    public function test_role_form_shows_current_actions_in_both_languages_and_saves_only_selected_status_permissions(): void
    {
        $editor = Role::findOrCreate('new-student-role-editor', 'web');
        $editor->givePermissionTo([
            Permission::findOrCreate('ViewAny:Role', 'web'), Permission::findOrCreate('Update:Role', 'web'),
        ]);
        $this->actor('new-student-role-editor-user', $editor);
        $target = Role::query()->create([
            'name' => 'new-student-activation-only', 'guard_name' => 'web',
            'label_en' => 'New student activation only', 'role_type_key' => 'staff',
        ]);

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditRole::class, ['record' => $target->getRouteKey()]);
            $field = collect($component->instance()->form->getFlatFields())->first(fn ($field): bool => $field instanceof CheckboxList
                && $field->getName() === NewStudentAccountResource::class);
            $this->assertNotNull($field);
            $this->assertEqualsCanonicalizing(self::PERMISSIONS, array_keys($field->getOptions()));
        }

        $selected = ['ViewAny:NewStudentAccount', 'ActivateAccount:NewStudentAccount'];
        $component->set($field->getStatePath(), $selected)->call('save')->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing($selected, $target->fresh()->permissions->pluck('name')->all());
        $assigned = $this->actor('new-student-activation-user', $target->fresh());
        foreach (self::PERMISSIONS as $permission) {
            $this->assertSame(in_array($permission, $selected, true), $assigned->can($permission));
        }
    }

    private function actor(string $username, Role $role): User
    {
        $actor = User::query()->create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'password' => 'password',
            'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $actor->assignRole($role);
        $this->assertFalse($actor->hasEffectiveRole('admin'));
        $this->flushSession();
        $this->actingAs($actor);

        return $actor;
    }
}
