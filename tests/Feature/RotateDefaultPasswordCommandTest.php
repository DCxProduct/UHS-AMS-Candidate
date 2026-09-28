<?php

namespace Tests\Feature;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RotateDefaultPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_matching_accounts_without_changing_them(): void
    {
        $oldPassword = '1234567'.'a';
        $newPassword = '12345678';

        $user = User::query()->create($this->userData('legacy-user', $oldPassword));
        $systemUser = SystemUser::query()->create($this->systemUserData('legacy-system-user', $oldPassword));

        $exitCode = Artisan::call('auth:rotate-default-password', [
            'from' => $oldPassword,
            'to' => $newPassword,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('2 total', Artisan::output());
        $this->assertTrue(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Hash::check($oldPassword, $systemUser->fresh()->password));
    }

    public function test_command_rotates_only_accounts_with_the_existing_password(): void
    {
        $oldPassword = '1234567'.'a';
        $newPassword = '12345678';

        $user = User::query()->create($this->userData('legacy-user', $oldPassword));
        $systemUser = SystemUser::query()->create($this->systemUserData('legacy-system-user', $oldPassword));
        $unmatchedUser = User::query()->create($this->userData('other-user', 'different-password'));

        $exitCode = Artisan::call('auth:rotate-default-password', [
            'from' => $oldPassword,
            'to' => $newPassword,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertFalse(Hash::check($oldPassword, $user->fresh()->password));
        $this->assertTrue(Hash::check($newPassword, $systemUser->fresh()->password));
        $this->assertTrue(Hash::check('different-password', $unmatchedUser->fresh()->password));
    }

    private function userData(string $username, string $password): array
    {
        return [
            'registration_type' => 'student',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'phone' => '010'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'date_of_birth' => '2001-01-01',
            'password' => $password,
        ];
    }

    private function systemUserData(string $username, string $password): array
    {
        return [
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'phone' => '011'.str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT),
            'password' => $password,
            'is_active' => true,
        ];
    }
}
