<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\CreateNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\EditNewStudentAccount;
use App\Filament\Admin\Resources\NewStudentAccounts\Pages\ListNewStudentAccounts;
use App\Filament\Pages\Auth\Login;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\SystemUser;
use App\Models\User;
use App\Services\AmsStudentAccountTransfer;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class NewStudentAccountResourceTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny', 'Create', 'Update', 'ActivateAccount', 'DeactivateAccount'];

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

    public function test_authorized_staff_can_create_and_edit_a_retained_student_record_with_direct_edit_row_links(): void
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
            ->assertTableActionDoesNotExist('view', record: $record)
            ->assertTableActionVisible('edit', $record)
            ->assertTableActionDoesNotExist('delete', record: $record);
        foreach (['row_number', 'student_id', 'email', 'phone', 'first_name_latin', 'last_name_latin', 'is_active', 'created_at', 'updated_at'] as $column) {
            $list->assertTableColumnExists($column);
        }
        $this->assertSame(NewStudentAccountResource::getUrl('edit', ['record' => $record]), $list->instance()->getTable()->getRecordUrl($record));
        $this->assertArrayNotHasKey('view', NewStudentAccountResource::getPages());
        $this->get(NewStudentAccountResource::getUrl('index').'/'.$record->getRouteKey())->assertNotFound();

        Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->assertActionDoesNotExist('view')
            ->assertActionDoesNotExist('delete')
            ->fillForm(['last_name_latin' => 'VIREAK', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('VIREAK', $record->fresh()->last_name_latin);
        $this->assertFalse($record->fresh()->is_active);

        $this->assertModelExists($record);
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
        $list = Livewire::test(ListNewStudentAccounts::class)
            ->assertCanSeeTableRecords([$record])
            ->assertActionHidden('create')
            ->assertTableActionDoesNotExist('view', record: $record)
            ->assertTableActionHidden('edit', $record)
            ->assertTableActionDoesNotExist('delete', record: $record)
            ->mountTableAction('delete', $record)
            ->callMountedTableAction();
        $this->assertModelExists($record);
        $this->assertNull($list->instance()->getTable()->getRecordUrl($record));
        $this->get(NewStudentAccountResource::getUrl('create'))->assertForbidden();
        $this->get(NewStudentAccountResource::getUrl('index').'/'.$record->getRouteKey())->assertNotFound();
        $this->get(NewStudentAccountResource::getUrl('edit', ['record' => $record]))->assertForbidden();

        $this->grant('View');
        $this->get(NewStudentAccountResource::getUrl('index').'/'.$record->getRouteKey())->assertNotFound();
        $this->assertNull(Livewire::test(ListNewStudentAccounts::class)->instance()->getTable()->getRecordUrl($record));
        $this->assertFalse(NewStudentAccountResource::canCreate());
        $this->assertFalse(NewStudentAccountResource::canEdit($record));
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
        $this->assertFalse(NewStudentAccountResource::can('activateAccount', $record));
        $this->assertFalse(NewStudentAccountResource::can('deactivateAccount', $record));
    }

    public function test_update_permission_preserves_editing_but_legacy_delete_grants_never_enable_deletion(): void
    {
        $this->grant('ViewAny', 'Update');
        $record = NewStudentAccount::query()->create($this->data());
        $this->get(NewStudentAccountResource::getUrl('edit', ['record' => $record]))->assertOk();
        $list = Livewire::test(ListNewStudentAccounts::class)
            ->assertTableActionDoesNotExist('view', record: $record)
            ->assertTableActionVisible('edit', $record);
        $this->assertSame(NewStudentAccountResource::getUrl('edit', ['record' => $record]), $list->instance()->getTable()->getRecordUrl($record));
        $this->assertFalse(NewStudentAccountResource::canCreate());
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
        Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
            ->assertActionDoesNotExist('view')
            ->assertActionDoesNotExist('delete')
            ->call('mountAction', 'delete')
            ->call('callMountedAction');
        $this->assertModelExists($record);

        $this->grant('Delete');
        $this->assertFalse(NewStudentAccountResource::canDelete($record));
        $this->assertFalse(NewStudentAccountResource::canDeleteAny());
        $this->grant('DeleteAny');
        $this->assertFalse(NewStudentAccountResource::canDeleteAny());
        $this->assertFalse(NewStudentAccountResource::canForceDelete($record));
        $this->assertFalse(NewStudentAccountResource::canForceDeleteAny());
        $this->assertModelExists($record);
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
        $this->assertFalse(NewStudentAccountResource::can('activateAccount', $record));
        $this->assertFalse(NewStudentAccountResource::can('deactivateAccount', $record));
        foreach (['index', 'create', 'edit'] as $page) {
            $this->get(NewStudentAccountResource::getUrl($page, $page === 'edit' ? ['record' => $record] : []))->assertForbidden();
        }
        $this->get(NewStudentAccountResource::getUrl('index').'/'.$record->getRouteKey())->assertNotFound();
    }

    public function test_all_delete_actions_and_record_selection_are_removed_even_with_legacy_grants_and_admin_role(): void
    {
        $this->grant('ViewAny', 'Update', 'Delete', 'DeleteAny', 'ForceDelete', 'ForceDeleteAny');
        $first = NewStudentAccount::query()->create($this->data());
        $second = NewStudentAccount::query()->create($this->data(['student_id' => 'STU-0002', 'email' => 'second-student@example.test', 'phone' => '098111222']));

        foreach ([false, true] as $adminRole) {
            if ($adminRole) {
                $this->operator->assignRole(Role::findOrCreate('admin', 'web'));
            }
            $this->assertTrue($this->operator->can('DeleteAny:NewStudentAccount'));
            $this->assertFalse(NewStudentAccountResource::canDelete($first));
            $this->assertFalse(NewStudentAccountResource::canDeleteAny());
            $this->assertFalse(NewStudentAccountResource::canForceDelete($first));
            $this->assertFalse(NewStudentAccountResource::canForceDeleteAny());
            $list = Livewire::test(ListNewStudentAccounts::class)
                ->assertCanSeeTableRecords([$first, $second])
                ->assertTableActionDoesNotExist('delete', record: $first)
                ->mountTableAction('delete', $first)
                ->callMountedTableAction();
            Livewire::test(EditNewStudentAccount::class, ['record' => $first->getRouteKey()])
                ->assertActionDoesNotExist('delete')
                ->call('mountAction', 'delete')
                ->call('callMountedAction');
            $this->assertNull($list->instance()->getTable()->getBulkAction('delete'));
            $this->assertFalse($list->instance()->getTable()->isSelectionEnabled());
            $this->assertSame([], $list->instance()->getTable()->getFlatBulkActions());
            $this->assertModelExists($first);
            $this->assertModelExists($second);
        }

        $this->assertModelExists($first);
        $this->assertModelExists($second);
        $this->assertSame(2, NewStudentAccount::query()->count());
    }

    public function test_student_identifiers_and_emails_are_validated_without_rejecting_unchanged_edit_values(): void
    {
        $this->grant(...self::PERMISSIONS);
        $record = NewStudentAccount::query()->create($this->data());

        Livewire::test(CreateNewStudentAccount::class)
            ->fillForm($this->data())
            ->call('create')
            ->assertHasFormErrors(['student_id' => 'unique', 'email', 'phone']);
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

    #[DataProvider('studentLoginIdentifiers')]
    public function test_dedicated_permissions_toggle_the_student_account_and_control_each_login_identifier(string $field): void
    {
        $this->grant('ViewAny', 'ActivateAccount', 'DeactivateAccount');
        [$originalId, $record, $entry] = $this->promotedAccount();
        $other = NewStudentAccount::query()->create($this->data([
            'student_id' => 'STU-0002', 'email' => 'other-student@example.test', 'phone' => '098111222',
        ]));
        $before = $record->getRawOriginal();
        $beforeEntry = $entry->getRawOriginal();
        $this->assertNotSame($originalId, $record->id);

        Livewire::test(ListNewStudentAccounts::class)
            ->assertTableActionVisible('deactivate_account', $record)
            ->assertTableActionHidden('activate_account', $record)
            ->callTableAction('deactivate_account', $record);

        $this->assertFalse($record->fresh()->is_active);
        $this->assertTrue($this->operator->fresh()->is_active);
        $this->assertTrue($other->fresh()->is_active);
        Auth::logout();
        Livewire::test(Login::class)
            ->fillForm(['login' => $record->{$field}, 'password' => 'StudentPassword1!'])
            ->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();

        $this->actingAs($this->operator);
        Livewire::test(ListNewStudentAccounts::class)
            ->assertTableActionVisible('activate_account', $record)
            ->assertTableActionHidden('deactivate_account', $record)
            ->callTableAction('activate_account', $record);

        $this->assertTrue($record->fresh()->is_active);
        Auth::logout();
        Livewire::test(Login::class)
            ->fillForm(['login' => $record->{$field}, 'password' => 'StudentPassword1!'])
            ->call('authenticate')->assertHasNoFormErrors()->assertRedirect('/dashboard');
        $this->assertInstanceOf(StudentLogin::class, Auth::user());
        $this->assertSame($originalId, (int) Auth::id());
        $this->assertDatabaseMissing('users', ['id' => $originalId]);
        $this->assertSame($beforeEntry, $entry->fresh()->getRawOriginal());
        $after = $record->fresh()->getRawOriginal();
        unset($before['updated_at'], $before['locale'], $after['updated_at'], $after['locale']);
        $this->assertSame($before, $after);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function studentLoginIdentifiers(): array
    {
        return ['phone' => ['phone'], 'email' => ['email'], 'student ID' => ['student_id']];
    }

    public function test_list_only_permission_cannot_activate_or_deactivate_even_when_calling_the_callback_directly(): void
    {
        $this->grant('ViewAny');
        foreach ([true => 'deactivate_account', false => 'activate_account'] as $active => $action) {
            $record = NewStudentAccount::query()->create($this->data([
                'student_id' => 'PERMISSION-'.(int) $active, 'email' => null, 'phone' => null, 'is_active' => (bool) $active,
            ]))->fresh();
            $before = $record->getRawOriginal();
            $list = Livewire::test(ListNewStudentAccounts::class)
                ->assertTableActionHidden($action, $record)
                ->mountTableAction($action, $record)
                ->callMountedTableAction();
            $callback = $list->instance()->getTable()->getAction($action)->getActionFunction();

            try {
                $callback($record);
                $this->fail('Changing account status must require its dedicated permission.');
            } catch (HttpExceptionInterface $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame($before, $record->fresh()->getRawOriginal());
        }
    }

    public function test_deactivating_a_promoted_account_invalidates_its_existing_session_on_the_next_request(): void
    {
        [$originalId, $record] = $this->promotedAccount();
        $student = AccountUser::query()->findOrFail($originalId);
        Auth::logout();
        Auth::login($student);
        $sessionKey = Auth::guard()->getName();
        $this->withSession(['student_session_marker' => 'active'])
            ->get('/dashboard')->assertOk()->assertSessionHas($sessionKey, $originalId);

        // A separate staff request changes the stored state while this browser
        // still has its previously authenticated, active model in memory.
        $record->update(['is_active' => false]);
        $this->assertTrue($student->is_active);
        $this->get('/dashboard')->assertRedirect('/login')
            ->assertSessionMissing('student_session_marker')->assertSessionMissing($sessionKey);

        $this->assertGuest();
        $this->assertFalse($record->fresh()->is_active);
        $this->assertDatabaseMissing('users', ['id' => $originalId]);
    }

    #[DataProvider('accountStatusPermissions')]
    public function test_activation_and_deactivation_permissions_are_independent_of_each_other_and_update(string $permission, string $action, bool $active, string $opposite): void
    {
        $this->grant('ViewAny', $permission);
        $record = NewStudentAccount::query()->create($this->data(['is_active' => $active]))->fresh();
        $this->assertFalse(NewStudentAccountResource::canEdit($record));
        $this->get(NewStudentAccountResource::getUrl('edit', ['record' => $record]))->assertForbidden();

        Livewire::test(ListNewStudentAccounts::class)
            ->assertTableActionHidden('edit', $record)
            ->assertTableActionVisible($action, $record)
            ->callTableAction($action, $record);
        $this->assertSame(! $active, $record->fresh()->is_active);

        $component = Livewire::test(ListNewStudentAccounts::class)->assertTableActionHidden($opposite, $record);
        $callback = $component->instance()->getTable()->getAction($opposite)->getActionFunction();
        try {
            $callback($record);
            $this->fail('One status permission must not grant its opposite action.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(! $active, $record->fresh()->is_active);
    }

    public static function accountStatusPermissions(): array
    {
        return [
            'activate only' => ['ActivateAccount', 'activate_account', false, 'deactivate_account'],
            'deactivate only' => ['DeactivateAccount', 'deactivate_account', true, 'activate_account'],
        ];
    }

    public function test_update_permission_cannot_bypass_status_permissions_through_actions_or_edit_form(): void
    {
        $this->grant('ViewAny', 'Update');
        foreach ([true => 'deactivate_account', false => 'activate_account'] as $active => $action) {
            $record = NewStudentAccount::query()->create($this->data([
                'student_id' => 'UPDATE-'.(int) $active, 'email' => null, 'phone' => null, 'is_active' => (bool) $active,
            ]))->fresh();
            $component = Livewire::test(ListNewStudentAccounts::class)->assertTableActionHidden($action, $record);
            $callback = $component->instance()->getTable()->getAction($action)->getActionFunction();
            try {
                $callback($record);
                $this->fail('Update permission must not authorize a status action.');
            } catch (HttpExceptionInterface $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }

            $editor = Livewire::test(EditNewStudentAccount::class, ['record' => $record->getRouteKey()])
                ->assertFormFieldIsDisabled('is_active')
                ->set('data.is_active', ! $active)
                ->set('data.last_name_latin', 'UPDATED')
                ->call('save')->assertHasNoFormErrors();
            $this->assertSame((bool) $active, $record->fresh()->is_active);
            $this->assertSame('UPDATED', $record->fresh()->last_name_latin);

            // The write handler must also reject a posted status change if UI
            // field disabling is bypassed or authorization changes after mount.
            $writer = new ReflectionMethod(EditNewStudentAccount::class, 'handleRecordUpdate');
            try {
                $writer->invoke($editor->instance(), $record, ['is_active' => ! $active, 'last_name_latin' => 'DENIED']);
                $this->fail('The write handler must enforce the status permission.');
            } catch (HttpExceptionInterface $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame((bool) $active, $record->fresh()->is_active);
            $this->assertSame('UPDATED', $record->fresh()->last_name_latin);
        }
    }

    private function promotedAccount(): array
    {
        $candidate = User::query()->create([
            'registration_type' => 'student', 'name' => 'SOK DARA', 'name_latin' => 'SOK DARA',
            'username' => 'status-candidate', 'email' => 'new-student@example.test', 'phone' => '012345678',
            'date_of_birth' => '2000-01-01', 'password' => 'StudentPassword1!', 'is_active' => true,
            'candidate_roles' => ['candidate'],
        ]);
        $form = CustomForm::query()->create(['name' => 'Preserved status history', 'slug' => 'status-history', 'is_active' => true]);
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id,
            'review_status' => 'approved', 'data' => ['answer' => 'Preserved response'],
        ])->saveQuietly();
        $payload = $this->data();
        unset($payload['is_active']);
        $account = app(AmsStudentAccountTransfer::class)->receive($payload);

        return [$candidate->id, $account->fresh(), $entry->fresh()];
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
