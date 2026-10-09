<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Register;
use App\Models\SystemUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class RegisterLatinNameCapitalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_latin_names_are_saved_in_capital_letters(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $register = Livewire::test(Register::class)->instance();

        $method = new ReflectionMethod(Register::class, 'handleRegistration');
        $method->invoke($register, [
            'student_role' => 'candidate',
            'first_name_en' => 'sithan',
            'last_name_en' => ' manita ',
            'phone' => '010519102',
            'email' => null,
            'password' => 'Password123!',
        ]);

        $user = User::query()->where('phone', '010519102')->sole();
        $this->assertSame('SITHAN MANITA', $user->name);
        $this->assertSame('SITHAN MANITA', $user->name_latin);
        $this->assertSame('SITHAN MANITA', SystemUser::query()->where('phone', '010519102')->sole()->name);
    }

    public function test_the_name_fields_show_capitals_while_typing(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $this->get(route('filament.app.auth.register'))
            ->assertOk()
            ->assertSee('text-transform: uppercase;', false);
    }
}
