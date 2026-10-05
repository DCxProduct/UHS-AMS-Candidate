<?php

namespace Tests\Feature;

use App\Filament\Student\Pages\MyProfile;
use App\Models\SystemUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    private string $originalLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalLocale = app()->getLocale();
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        app()->setLocale($this->originalLocale);

        parent::tearDown();
    }

    public function test_profile_uses_separate_latin_names_and_keeps_login_username(): void
    {
        Filament::setCurrentPanel('app');

        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'SITHAN VIRATANA',
            'name_latin' => 'SITHAN VIRATANA',
            'username' => 'student_015916219',
            'email' => 'sithan@example.test',
            'phone' => '015916219',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $systemUser = SystemUser::query()->create([
            'name' => 'SITHAN VIRATANA',
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => Hash::make('password'),
            'roles' => ['Student'],
            'is_active' => true,
        ]);

        $profile = Livewire::actingAs($user)->test(MyProfile::class);

        $this->assertSame('First Name (Latin)', $profile->instance()->getSchema('form')->getComponentByStatePath('first_name_en')->getLabel());
        $this->assertSame('Last Name (Latin)', $profile->instance()->getSchema('form')->getComponentByStatePath('last_name_en')->getLabel());
        $this->assertSame('SITHAN', $profile->get('data.first_name_en'));
        $this->assertSame('VIRATANA', $profile->get('data.last_name_en'));

        $profile
            ->set('data.first_name_en', 'Dara')
            ->set('data.last_name_en', 'Sok')
            ->call('save');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Dara Sok',
            'name_latin' => 'Dara Sok',
            'username' => 'student_015916219',
            'email' => 'sithan@example.test',
            'phone' => '015916219',
        ]);
        $this->assertSame('Dara Sok', $systemUser->fresh()->name);
    }
}
