<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\CreateNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\EditNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ListNewStudentAccounts;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ViewNewStudentAccount;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NewStudentAccountResourceTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->operator = User::query()->create([
            'registration_type' => 'admin', 'name' => 'New student records operator',
            'username' => 'new_student_records_operator', 'email' => 'new-student-operator@example.test',
            'password' => 'password', 'date_of_birth' => '1990-01-01', 'is_active' => true,
        ]);
        $this->actingAs($this->operator);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_authorized_staff_can_create_read_update_and_delete_an_independent_student_record(): void
    {
        $this->grant(...self::PERMISSIONS);
        $users = User::query()->count();
        $systemUsers = SystemUser::query()->count();

        Livewire::test(CreateNewStudentAccount::class)
            ->fillForm($this->data())
            ->call('create')
            ->assertHasNoFormErrors();
        $record = NewStudentAccount::query()->where('student_id', 'STU-0001')->sole();
        $this->assertTrue($record->is_active);
        $this->assertNotNull($record->created_at);
        $this->assertNotNull($record->updated_at);

        $list = Livewire::test(ListNewStudentAccounts::class)
            ->assertCanSeeTableRecords([$record])
            ->assertActionVisible('create')
            ->assertTableActionVisible('view', $record)
            ->assertTableActionVisible('edit', $record)
            ->assertTableActionVisible('delete', $record);
        foreach (['row_number', 'student_id', 'email', 'phone', 'first_name_latin', 'last_name_latin', 'is_active', 'created_at', 'updated_at'] as $column) {
            $list->assertTableColumnExists($column);
        }
        Livewire::test(ViewNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->assertSee('STU-0001')
            ->assertSee('SOK');
        $this->get(NewStudentAccountResource::getUrl('view', ['record' => $record]))->assertOk();

        Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->fillForm(['last_name_latin' => 'VIREAK', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('VIREAK', $record->fresh()->last_name_latin);
        $this->assertFalse($record->fresh()->is_active);

        Livewire::test(ListNewStudentAccounts::class)->callTableAction('delete', $record);
        $this->assertModelMissing($record);
        $this->assertSame($users, User::query()->count());
        $this->assertSame($systemUsers, SystemUser::query()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_list_permission_does_not_allow_individual_read_or_mutations(): void
    {
        $record = NewStudentAccount::query()->create($this->data());
        $this->assertFalse(NewStudentAccountResource::shouldRegisterNavigation());
        $this->get(NewStudentAccountResource::getUrl('index'))->assertForbidden();

        $this->grant('ViewAny');
        $this->assertTrue(NewStudentAccountResource::shouldRegisterNavigation());
        Livewire::test(ListNewStudentAccounts::class)
            ->assertCanSeeTableRecords([$record])
            ->assertActionHidden('create')
            ->assertTableActionHidden('view', $record)
            ->assertTableActionHidden('edit', $record)
            ->assertTableActionHidden('delete', $record)
            ->mountTableAction('delete', $record)
            ->callMountedTableAction();
        $this->assertModelExists($record);
        $this->get(NewStudentAccountResource::getUrl('create'))->assertForbidden();
        $this->get(NewStudentAccountResource::getUrl('view', ['record' => $record]))->assertForbidden();
        $this->get(NewStudentAccountResource::getUrl('edit', ['record' => $record]))->assertForbidden();

        $this->grant('View');
        $this->get(NewStudentAccountResource::getUrl('view', ['record' => $record]))->assertOk();
        $this->assertFalse(NewStudentAccountResource::canCreate());
        $this->assertFalse(NewStudentAccountResource::canEdit($record));
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
    }

    public function test_update_and_delete_permissions_are_independent(): void
    {
        $this->grant('ViewAny', 'Update');
        $record = NewStudentAccount::query()->create($this->data());
        $this->get(NewStudentAccountResource::getUrl('edit', ['record' => $record]))->assertOk();
        $this->assertFalse(NewStudentAccountResource::canCreate());
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
        Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->assertActionHidden('delete')
            ->call('mountAction', 'delete')
            ->call('callMountedAction');
        $this->assertModelExists($record);

        $this->grant('Delete');
        $this->assertTrue(NewStudentAccountResource::canDelete($record));
        $this->assertFalse(NewStudentAccountResource::canDeleteAny());
        $this->grant('DeleteAny');
        $this->assertTrue(NewStudentAccountResource::canDeleteAny());
    }

    public function test_student_accounts_cannot_access_the_resource_even_when_crud_permissions_are_granted(): void
    {
        $this->operator->forceFill(['registration_type' => 'student'])->save();
        $this->operator->assignRole(Role::findOrCreate('admin', 'web'));
        $this->grant(...self::PERMISSIONS);
        $record = NewStudentAccount::query()->create($this->data());

        $this->assertTrue($this->operator->can('Create:NewStudentAccount'));
        $this->assertFalse(NewStudentAccountResource::shouldRegisterNavigation());
        $this->assertFalse(NewStudentAccountResource::canCreate());
        $this->assertFalse(NewStudentAccountResource::canEdit($record));
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
        foreach (['index', 'create', 'view', 'edit'] as $page) {
            $this->get(NewStudentAccountResource::getUrl($page, in_array($page, ['view', 'edit'], true) ? ['record' => $record] : []))->assertForbidden();
        }
    }

    public function test_bulk_delete_also_requires_permission_to_delete_each_selected_record(): void
    {
        $this->grant('ViewAny', 'DeleteAny');
        $first = NewStudentAccount::query()->create($this->data());
        $second = NewStudentAccount::query()->create($this->data(['student_id' => 'STU-0002', 'email' => 'second-student@example.test']));

        Livewire::test(ListNewStudentAccounts::class)
            ->callTableBulkAction('delete', [$first, $second]);
        $this->assertModelExists($first);
        $this->assertModelExists($second);

        $this->grant('Delete');
        Livewire::test(ListNewStudentAccounts::class)
            ->callTableBulkAction('delete', [$first, $second])
            ->assertHasNoTableBulkActionErrors();
        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
    }

    public function test_student_identifiers_and_emails_are_validated_without_rejecting_unchanged_edit_values(): void
    {
        $this->grant(...self::PERMISSIONS);
        $record = NewStudentAccount::query()->create($this->data());

        Livewire::test(CreateNewStudentAccount::class)
            ->fillForm($this->data())
            ->call('create')
            ->assertHasFormErrors(['student_id' => 'unique', 'email' => 'unique']);
        Livewire::test(CreateNewStudentAccount::class)
            ->fillForm($this->data(['student_id' => 'STU-0002', 'email' => 'invalid-email']))
            ->call('create')
            ->assertHasFormErrors(['email' => 'email']);
        Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->fillForm($this->data())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(1, NewStudentAccount::query()->count());
    }

    public function test_generated_fields_cannot_be_supplied_as_editable_student_data(): void
    {
        $this->grant(...self::PERMISSIONS);
        Livewire::test(CreateNewStudentAccount::class)
            ->fillForm($this->data(['email' => null, 'phone' => null]))
            ->set('data.row_number', 99)
            ->set('data.created_at', '1990-01-01 00:00:00')
            ->set('data.updated_at', '1990-01-01 00:00:00')
            ->call('create')
            ->assertHasNoFormErrors();

        $record = NewStudentAccount::query()->sole();
        $this->assertArrayNotHasKey('row_number', $record->getAttributes());
        $this->assertTrue($record->created_at->isToday());
        $this->assertTrue($record->updated_at->isToday());
        $this->assertNull($record->email);
        $this->assertNull($record->phone);
    }

    private function grant(string ...$actions): void
    {
        foreach ($actions as $action) {
            $this->operator->givePermissionTo(Permission::findOrCreate($action.':NewStudentAccount', 'web'));
        }
        $this->operator->unsetRelation('roles')->unsetRelation('permissions');
    }

    private function data(array $overrides = []): array
    {
        return [
            'student_id' => 'STU-0001', 'email' => 'new-student@example.test', 'phone' => '012345678',
            'first_name_latin' => 'SOK', 'last_name_latin' => 'DARA', 'is_active' => true,
            ...$overrides,
        ];
    }
}
