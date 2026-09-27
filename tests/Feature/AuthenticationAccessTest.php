<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_valid_credentials_authenticate_and_invalid_credentials_do_not(): void
    {
        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Login User',
            'username' => 'login_user',
            'email' => 'login-user@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->assertTrue(Auth::attempt(['username' => 'login_user', 'password' => 'password']));
        $this->assertAuthenticatedAs($user);

        Auth::logout();

        $this->assertFalse(Auth::attempt(['username' => 'login_user', 'password' => 'incorrect']));
        $this->assertGuest();
    }

    public function test_active_admin_account_can_access_dashboard(): void
    {
        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Test Admin',
            'username' => 'test_admin',
            'email' => 'test-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
