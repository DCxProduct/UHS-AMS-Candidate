<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_seeding_uses_the_fixed_password(): void
    {
        Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        (new AdminUserSeeder)->run();

        $admin = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertTrue(Hash::check('12345678', $admin->password));
    }
}
