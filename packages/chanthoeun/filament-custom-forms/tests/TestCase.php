<?php

namespace Chanthoeun\FilamentCustomForms\Tests;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

abstract class TestCase extends \Tests\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        Notification::fake();
        Factory::guessFactoryNamesUsing(fn (string $model): string => str_starts_with($model, 'Chanthoeun\\FilamentCustomForms\\')
                ? 'Chanthoeun\\FilamentCustomForms\\Database\\Factories\\'.class_basename($model).'Factory'
                : 'Database\\Factories\\'.class_basename($model).'Factory');
        $user = User::create([
            'name' => 'Package Test Admin', 'username' => 'package_admin',
            'email' => 'package-admin@example.test', 'password' => 'test-only-password',
            'registration_type' => 'admin', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($user);
        Filament::setCurrentPanel('app');
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }
}
