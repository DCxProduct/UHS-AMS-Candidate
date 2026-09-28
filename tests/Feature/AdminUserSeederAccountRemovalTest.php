<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SystemUser;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSeederAccountRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeder_does_not_create_cashier_or_registrar_accounts(): void
    {
        Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        (new AdminUserSeeder)->run();

        $this->assertDatabaseHas('users', [
            'username' => 'admin',
        ]);
        $this->assertFalse(SystemUser::query()->whereIn('username', ['cashier', 'registrar'])->exists());
        $this->assertFalse(Role::query()->whereIn('name', ['cashier', 'registrar'])->exists());
    }
}
