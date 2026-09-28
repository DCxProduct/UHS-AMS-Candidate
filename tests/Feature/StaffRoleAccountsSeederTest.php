<?php

namespace Tests\Feature;

use App\Models\SystemUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StaffRoleAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffRoleAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_eight_staff_accounts_and_linked_login_users_are_seeded(): void
    {
        $this->seedRoles();

        (new StaffRoleAccountsSeeder)->run();

        foreach ($this->accounts() as $account) {
            $systemUser = SystemUser::query()
                ->where('username', $account['username'])
                ->firstOrFail();

            $this->assertSame($account['email'], $systemUser->email);
            $this->assertSame($account['phone'], $systemUser->phone);
            $this->assertSame([$account['role']], $systemUser->roles);
            $this->assertTrue($systemUser->is_active);
            $this->assertNotNull($systemUser->email_verified_at);
            $this->assertTrue(Hash::check('12345678', $systemUser->password));

            $loginUser = User::query()
                ->where('username', $account['username'])
                ->firstOrFail();

            $this->assertTrue(Hash::check('12345678', $loginUser->password));
            $this->assertSame($account['phone'], $loginUser->phone);
            $this->assertTrue($loginUser->hasRole($account['role']));
        }

        $this->assertSame(count($this->accounts()), SystemUser::query()->count());
    }

    public function test_seeding_again_does_not_duplicate_staff_accounts(): void
    {
        $this->seedRoles();

        $seeder = new StaffRoleAccountsSeeder;
        $seeder->run();
        $seeder->run();

        $this->assertSame(count($this->accounts()), SystemUser::query()->count());
        $this->assertSame(
            count($this->accounts()),
            User::query()
                ->whereIn('username', array_column($this->accounts(), 'username'))
                ->count(),
        );
    }

    private function seedRoles(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(0);

        (new RolesAndPermissionsSeeder)->run();
    }

    private function accounts(): array
    {
        return [
            ['username' => 'regofficer', 'email' => 'regofficer@gmail.com', 'role' => 'registrar_officer', 'phone' => '010999002'],
            ['username' => 'regmg', 'email' => 'regmg@gmail.com', 'role' => 'registrar_manager', 'phone' => '010999003'],
            ['username' => 'cashofficer', 'email' => 'cashofficer@gmail.com', 'role' => 'cashier_officer', 'phone' => '010999004'],
            ['username' => 'cashmg', 'email' => 'cashmg@gmail.com', 'role' => 'cashier_manager', 'phone' => '010999005'],
            ['username' => 'crud', 'email' => 'crud@gmail.com', 'role' => 'crud', 'phone' => '010999006'],
            ['username' => 'viewer', 'email' => 'viewer@gmail.com', 'role' => 'viewer', 'phone' => '010999007'],
            ['username' => 'delete', 'email' => 'delete@gmail.com', 'role' => 'delete', 'phone' => '010999008'],
            ['username' => 'dataentry', 'email' => 'dataentry@gmail.com', 'role' => 'data_entry', 'phone' => '010999009'],
        ];
    }
}
