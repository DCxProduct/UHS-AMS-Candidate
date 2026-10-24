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
            'first_name_kh' => 'សុភា',
            'last_name_kh' => 'ចាន់',
            'password' => Hash::make('password'),
            'roles' => ['Student'],
            'is_active' => true,
        ]);

        $profile = Livewire::actingAs($user)->test(MyProfile::class);

        $this->assertSame('First Name (Khmer)', $profile->instance()->getSchema('form')->getComponentByStatePath('first_name_kh')->getLabel());
        $this->assertSame('សុភា', $profile->get('data.first_name_kh'));
        $this->assertSame('ចាន់', $profile->get('data.last_name_kh'));
        $this->assertSame('First Name (Latin)', $profile->instance()->getSchema('form')->getComponentByStatePath('first_name_en')->getLabel());
        $this->assertSame('Last Name (Latin)', $profile->instance()->getSchema('form')->getComponentByStatePath('last_name_en')->getLabel());
        $this->assertSame('SITHAN', $profile->get('data.first_name_en'));
        $this->assertSame('VIRATANA', $profile->get('data.last_name_en'));

        $profile
            ->set('data.first_name_kh', 'ដារា')
            ->set('data.last_name_kh', 'សុខ')
            ->set('data.first_name_en', 'Dara')
            ->set('data.last_name_en', 'Sok')
            ->call('save');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Dara Sok',
            'first_name_kh' => 'ដារា',
            'last_name_kh' => 'សុខ',
            'name_latin' => 'Dara Sok',
            'username' => 'student_015916219',
            'email' => 'sithan@example.test',
            'phone' => '015916219',
        ]);
        $this->assertSame('Dara Sok', $systemUser->fresh()->name);
        $this->assertSame('ដារា', $systemUser->fresh()->first_name_kh);
        $this->assertSame('សុខ', $systemUser->fresh()->last_name_kh);
    }

    public function test_latin_name_fields_are_optional_on_profile(): void
    {
        Filament::setCurrentPanel('app');

        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'System Admin',
            'name_latin' => 'System Admin',
            'username' => 'system_admin',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        Livewire::actingAs($user)->test(MyProfile::class)
            ->set('data.first_name_en', '')
            ->set('data.last_name_en', '')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('System Admin', $user->fresh()->name_latin);
    }

    public function test_profile_menu_name_prefers_khmer_then_falls_back_to_latin(): void
    {
        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'System Admin',
            'name_latin' => 'SYSTEM ADMIN',
            'first_name_kh' => 'ប្រព័ន្ធ',
            'last_name_kh' => 'អ្នកគ្រប់គ្រង',
            'username' => 'system_admin',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->assertSame('ប្រព័ន្ធ អ្នកគ្រប់គ្រង', $user->getFilamentName());

        $user->forceFill(['first_name_kh' => null, 'last_name_kh' => null])->save();

        $this->assertSame('SYSTEM ADMIN', $user->fresh()->getFilamentName());
    }
}
