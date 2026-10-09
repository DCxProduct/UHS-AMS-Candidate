<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Models\User;
use App\Support\UserTypeOptions;
use App\Support\CandidateDisplayName;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class AuthenticationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        $this->resetUserTypeOptionsCaches();

        parent::tearDown();
    }

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

    public function test_registration_and_login_forms_do_not_expose_username(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('en');
        Filament::setCurrentPanel('app');

        try {
            $register = Livewire::test(Register::class);
            $registerSchema = $register->instance()->getSchema('form');

            $this->assertNull($registerSchema?->getComponentByStatePath('username'));
            $this->assertSame('First Name (Latin)', $registerSchema?->getComponentByStatePath('first_name_en')?->getLabel());
            $this->assertSame('Last Name (Latin)', $registerSchema?->getComponentByStatePath('last_name_en')?->getLabel());
            $this->assertSame(
                'Enter a password with at least 8 characters',
                $registerSchema?->getComponentByStatePath('password')?->getPlaceholder(),
            );

            $login = Livewire::test(Login::class);
            $loginSchema = $login->instance()->getSchema('form');

            $this->assertSame(
                'Email or Phone number',
                $loginSchema?->getComponentByStatePath('login')?->getLabel(),
            );
            $this->assertSame(
                'Enter email or phone number',
                $loginSchema?->getComponentByStatePath('login')?->getPlaceholder(),
            );
        } finally {
            Filament::setCurrentPanel(null);
            app()->setLocale($originalLocale);
        }
    }

    public function test_login_accepts_email_and_normalized_phone_number(): void
    {
        Filament::setCurrentPanel('app');

        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'Student User',
            'username' => 'student_010123456',
            'email' => 'student-login@example.test',
            'phone' => '010123456',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('data.login', 'student-login@example.test')
            ->set('data.password', 'password')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);

        Auth::logout();

        Livewire::test(Login::class)
            ->set('data.login', '010 123 456')
            ->set('data.password', 'password')
            ->call('authenticate');

        $this->assertAuthenticatedAs($user);

        Filament::setCurrentPanel(null);
    }

    public function test_registration_generates_a_unique_internal_username_from_phone(): void
    {
        $register = Livewire::test(Register::class);
        $method = new ReflectionMethod(Register::class, 'generateInternalUsername');

        $this->assertSame(
            'student_010123456',
            $method->invoke($register->instance(), '010123456'),
        );

        User::query()->create([
            'registration_type' => 'student',
            'name' => 'Existing Student',
            'username' => 'student_010123456',
            'email' => 'existing-student@example.test',
            'phone' => '011123456',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->assertSame(
            'student_010123456_1',
            $method->invoke($register->instance(), '010123456'),
        );
    }

    public function test_registration_uses_latin_name_for_display_without_changing_internal_username(): void
    {
        $register = Livewire::test(Register::class);
        $method = new ReflectionMethod(Register::class, 'handleRegistration');

        $user = $method->invoke($register->instance(), [
            'student_role' => 'student',
            'first_name_en' => '  Dara  ',
            'last_name_en' => 'Sok',
            'phone' => '010123456',
            'email' => 'dara-sok@example.test',
            'password' => 'password',
        ]);

        // Latin names are saved in capital letters.
        $this->assertSame('DARA SOK', $user->name);
        $this->assertSame('DARA SOK', $user->name_latin);
        $this->assertSame('student_010123456', $user->username);
        $this->assertSame('DARA SOK', CandidateDisplayName::for($user->fresh()));
        $this->assertSame('DARA SOK', $user->linkedSystemUser()?->name);
    }

    private function resetUserTypeOptionsCaches(): void
    {
        $properties = [
            'defaultsEnsured' => false,
            'optionsCache' => [],
            'colorsCache' => null,
            'candidateManagedRoleKeysCache' => null,
            'normalizedUserTypeCache' => [],
        ];

        foreach ($properties as $name => $value) {
            $property = new ReflectionProperty(UserTypeOptions::class, $name);
            $property->setValue(null, $value);
        }
    }
}
