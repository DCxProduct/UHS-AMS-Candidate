<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Policies\PaymentStatusListPolicy;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentStatusListRenameMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_rename_preserves_ids_role_grants_direct_grants_and_guards(): void
    {
        $role = Role::findOrCreate('rename_cashier', 'web');
        $user = $this->user('rename_direct_user');
        $view = Permission::findOrCreate('ViewAny:UnpaidApplication', 'web');
        $custom = Permission::findOrCreate('Custom:Receipt:UnpaidApplication', 'web');
        $otherGuard = Permission::findOrCreate('ViewAny:UnpaidApplication', 'api');
        $unrelated = Permission::findOrCreate('ViewAny:Payment', 'web');
        $role->givePermissionTo($view);
        $user->givePermissionTo($custom);

        $this->migration()->up();

        $this->assertDatabaseHas('permissions', ['id' => $view->id, 'name' => 'ViewAny:PaymentStatusList', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['id' => $custom->id, 'name' => 'Custom:Receipt:PaymentStatusList', 'guard_name' => 'web']);
        $this->assertDatabaseHas('permissions', ['id' => $otherGuard->id, 'name' => 'ViewAny:PaymentStatusList', 'guard_name' => 'api']);
        $this->assertDatabaseHas('permissions', ['id' => $unrelated->id, 'name' => 'ViewAny:Payment', 'guard_name' => 'web']);
        $this->assertDatabaseHas('role_has_permissions', ['permission_id' => $view->id, 'role_id' => $role->id]);
        $this->assertDatabaseHas('model_has_permissions', ['permission_id' => $custom->id, 'model_id' => $user->id, 'model_type' => User::class]);
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', '%:UnpaidApplication')->count());
    }

    public function test_duplicate_new_permissions_merge_grants_into_the_established_permission_id(): void
    {
        $old = Permission::findOrCreate('Pay:UnpaidApplication', 'web');
        $new = Permission::findOrCreate('Pay:PaymentStatusList', 'web');
        $oldRole = Role::findOrCreate('old_rename_cashier', 'web');
        $newRole = Role::findOrCreate('new_rename_cashier', 'web');
        $oldUser = $this->user('old_direct_rename_cashier');
        $newUser = $this->user('new_direct_rename_cashier');
        $oldRole->givePermissionTo($old);
        $newRole->givePermissionTo($new);
        $oldUser->givePermissionTo($old);
        $newUser->givePermissionTo($new);
        $otherGuard = Permission::findOrCreate('Pay:PaymentStatusList', 'api');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertDatabaseHas('permissions', ['id' => $old->id, 'name' => 'Pay:PaymentStatusList', 'guard_name' => 'web']);
        $this->assertDatabaseMissing('permissions', ['id' => $new->id]);
        foreach ([$oldRole, $newRole] as $role) {
            $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $old->id]);
        }
        foreach ([$oldUser, $newUser] as $user) {
            $this->assertDatabaseHas('model_has_permissions', ['model_id' => $user->id, 'model_type' => User::class, 'permission_id' => $old->id]);
            $this->assertTrue($user->fresh()->can('Pay:PaymentStatusList'));
        }
        $this->assertDatabaseHas('permissions', ['id' => $otherGuard->id, 'name' => 'Pay:PaymentStatusList', 'guard_name' => 'api']);

        $this->migration()->down();
        $this->assertDatabaseHas('permissions', ['id' => $old->id, 'name' => 'Pay:UnpaidApplication', 'guard_name' => 'web']);
        $this->assertTrue($oldUser->fresh()->can('Pay:UnpaidApplication'));
        $this->assertTrue($newUser->fresh()->can('Pay:UnpaidApplication'));
    }

    public function test_hidden_rows_and_application_data_survive_forward_and_reverse_rename(): void
    {
        $form = CustomForm::query()->create(['name' => 'Rename applications', 'slug' => 'rename-applications', 'is_active' => true]);
        $hidden = $this->entry($form, ['hidden_from_candidate_payment_lists' => true]);
        $visible = $this->entry($form, ['hidden_from_candidate_payment_lists' => '0']);
        $partiallyRenamed = $this->entry($form, ['hidden_from_candidate_payment_lists' => true, 'hidden_from_payment_status_lists' => false]);
        $untouched = $this->entry($form);
        $updatedAt = $hidden->getRawOriginal('updated_at');

        $this->migration()->up();

        foreach ([$hidden, $partiallyRenamed] as $entry) {
            $data = $entry->fresh()->data;
            $this->assertTrue($data['hidden_from_payment_status_lists']);
            $this->assertArrayNotHasKey('hidden_from_candidate_payment_lists', $data);
            $this->assertSame('approved', $data['registration_status']);
            $this->assertSame(['first_name_kh' => 'សុខ'], $data['profile']);
        }
        $this->assertFalse($visible->fresh()->data['hidden_from_payment_status_lists']);
        $this->assertArrayNotHasKey('hidden_from_payment_status_lists', $untouched->fresh()->data);
        $this->assertSame($updatedAt, $hidden->fresh()->getRawOriginal('updated_at'));

        $this->migration()->down();

        $this->assertTrue($hidden->fresh()->data['hidden_from_candidate_payment_lists']);
        $this->assertFalse($visible->fresh()->data['hidden_from_candidate_payment_lists']);
        $this->assertArrayNotHasKey('hidden_from_payment_status_lists', $hidden->fresh()->data);
    }

    public function test_rollback_preserves_merged_permissions_and_authorization_after_reapplying(): void
    {
        $user = $this->user('rename_policy_user');
        $permission = Permission::findOrCreate('ViewAny:UnpaidApplication', 'web');
        $user->givePermissionTo($permission);
        $policy = new PaymentStatusListPolicy;
        $denied = $this->user('rename_denied_user');
        $this->assertTrue($user->can('ViewAny:UnpaidApplication'));

        $this->migration()->up();
        $this->assertTrue($policy->viewAny($user->fresh()));
        $this->assertFalse($policy->viewAny($denied));
        $this->assertFalse($policy->create($user->fresh()));

        $this->migration()->down();
        $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'ViewAny:UnpaidApplication']);
        $this->assertTrue($user->fresh()->can('ViewAny:UnpaidApplication'));
        $this->assertDatabaseHas('model_has_permissions', ['permission_id' => $permission->id, 'model_id' => $user->id]);

        $this->migration()->up();
        $this->assertTrue($policy->viewAny($user->fresh()));
        $this->assertFalse($policy->viewAny($denied->fresh()));
    }

    public function test_optional_audit_morph_type_is_renamed_without_rewriting_history_payloads(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('auditable_type')->nullable();
        });
        $id = DB::table('audit_logs')->insertGetId([
            'action' => 'cleared', 'module' => 'Unpaid Applications',
            'description' => 'Historical Unpaid Applications description',
            'auditable_type' => 'App\\Models\\UnpaidApplication',
            'metadata' => json_encode(['module' => 'Unpaid Applications']),
        ]);

        $this->migration()->up();

        $this->assertDatabaseHas('audit_logs', [
            'id' => $id,
            'auditable_type' => 'App\\Models\\PaymentStatusList',
            'module' => 'Unpaid Applications',
            'description' => 'Historical Unpaid Applications description',
            'metadata' => json_encode(['module' => 'Unpaid Applications']),
        ]);

        $this->migration()->down();
        $this->assertSame('App\\Models\\UnpaidApplication', DB::table('audit_logs')->where('id', $id)->value('auditable_type'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_27_000001_rename_payment_status_list_identifiers.php');
    }

    private function entry(CustomForm $form, array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->create([
            'custom_form_id' => $form->id,
            'review_status' => 'approved',
            'data' => ['registration_status' => 'approved', 'profile' => ['first_name_kh' => 'សុខ'], ...$data],
        ]);
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin', 'name' => $username,
            'username' => $username, 'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
