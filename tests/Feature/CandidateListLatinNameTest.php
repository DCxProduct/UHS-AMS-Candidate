<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateLists\Pages\CreateCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\EditCandidateList;
use App\Filament\Admin\Resources\CandidateLists\Pages\ListCandidateLists;
use App\Models\CandidateList;
use App\Models\Role;
use App\Models\User;
use App\Support\CandidateLatinName;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CandidateListLatinNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'admin_user',
            'username' => 'admin_user',
            'email' => 'admin_user@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_names_are_split_and_joined_like_registration(): void
    {
        $this->assertSame(['Sok', 'Dara'], CandidateLatinName::split('Sok Dara'));
        $this->assertSame(['Sok', 'Dara Vann'], CandidateLatinName::split('  Sok   Dara Vann '));
        $this->assertSame(['Sok', ''], CandidateLatinName::split('Sok'));
        $this->assertSame('Sok Dara', CandidateLatinName::join(' Sok ', 'Dara '));
    }

    public function test_the_form_asks_for_latin_names_instead_of_a_username(): void
    {
        Livewire::test(CreateCandidateList::class)
            ->assertFormFieldExists('first_name_en')
            ->assertFormFieldExists('last_name_en')
            ->assertFormFieldDoesNotExist('username');
    }

    public function test_creating_a_candidate_stores_the_name_and_makes_the_username_from_the_phone(): void
    {
        Livewire::test(CreateCandidateList::class)
            ->fillForm([
                'first_name_en' => 'Sok',
                'last_name_en' => 'Dara',
                'phone' => '012345678',
                'candidate_type' => array_key_first(UserTypeOptions::options()),
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidate = CandidateList::query()->where('phone', '012345678')->sole();
        $this->assertSame('Sok Dara', $candidate->name);
        $this->assertSame('student_012345678', $candidate->username);

        $loginUser = User::query()->where('username', 'student_012345678')->sole();
        $this->assertSame('Sok Dara', $loginUser->name);
        $this->assertSame('Sok Dara', $loginUser->name_latin);
    }

    public function test_editing_changes_the_name_but_keeps_the_username(): void
    {
        $candidate = $this->candidate('Sok Dara', 'student_012345678', '012345678');

        Livewire::test(EditCandidateList::class, ['record' => $candidate->getRouteKey()])
            ->assertSchemaStateSet(['first_name_en' => 'Sok', 'last_name_en' => 'Dara'])
            ->fillForm(['first_name_en' => 'Chan', 'last_name_en' => 'Dara Vann'])
            ->call('save')
            ->assertHasNoFormErrors();

        $candidate->refresh();
        $this->assertSame('Chan Dara Vann', $candidate->name);
        $this->assertSame('student_012345678', $candidate->username);
        $this->assertSame('Chan Dara Vann', User::query()->where('username', 'student_012345678')->sole()->name_latin);
    }

    public function test_latin_names_are_required_and_must_be_latin_letters(): void
    {
        Livewire::test(CreateCandidateList::class)
            ->fillForm([
                'first_name_en' => '',
                'last_name_en' => 'សុខ',
                'phone' => '012345678',
                'candidate_type' => array_key_first(UserTypeOptions::options()),
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->call('create')
            ->assertHasFormErrors(['first_name_en' => 'required', 'last_name_en' => 'regex']);
    }

    public function test_the_list_shows_and_searches_the_latin_names(): void
    {
        $sok = $this->candidate('Sok Dara', 'student_012345678', '012345678');
        $chan = $this->candidate('Chan Vann', 'student_098765432', '098765432');

        Livewire::test(ListCandidateLists::class)
            ->assertTableColumnExists('first_name_latin')
            ->assertTableColumnExists('last_name_latin')
            ->assertTableColumnDoesNotExist('username')
            ->assertTableColumnStateSet('first_name_latin', 'Sok', $sok)
            ->assertTableColumnStateSet('last_name_latin', 'Dara', $sok)
            ->searchTable('dara')
            ->assertCanSeeTableRecords([$sok])
            ->assertCanNotSeeTableRecords([$chan]);
    }

    private function candidate(string $name, string $username, string $phone): CandidateList
    {
        return CandidateList::query()->forceCreate([
            'name' => $name,
            'username' => $username,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'roles' => ['candidate'],
            'is_active' => true,
        ]);
    }
}
