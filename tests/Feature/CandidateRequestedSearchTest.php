<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateRequested\CandidateRequestedResource;
use App\Filament\Admin\Resources\CandidateRequested\Pages\ListCandidateRequested;
use App\Filament\Admin\Resources\CandidateRequested\Tables\CandidateRequestedTable;
use App\Models\CandidateRequested;
use App\Models\Role;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class CandidateRequestedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_search_narrows_candidate_requested_records(): void
    {
        $matching = $this->createEntry(
            seatNumber: 'DEMO-087',
            nameEnglish: 'Sok Dara',
            nameKhmer: 'សុខ ដារា',
        );
        $other = $this->createEntry(
            seatNumber: 'DEMO-088',
            nameEnglish: 'Chan Lina',
            nameKhmer: 'ចាន់ លីណា',
        );

        $this->assertSame([$matching->id], $this->search('DEMO-087')->pluck('id')->all());
        $this->assertSame([$other->id, $matching->id], $this->search('DEMO')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('Sok Dara')->pluck('id')->all());
        $this->assertSame([$matching->id], $this->search('សុខ')->pluck('id')->all());
    }

    public function test_global_search_returns_no_records_for_an_impossible_value(): void
    {
        $this->createEntry(seatNumber: 'DEMO-087', nameEnglish: 'Sok Dara', nameKhmer: 'សុខ ដារា');

        $this->assertCount(0, $this->search('NO_SUCH_QA_927')->get());
    }

    public function test_clearing_search_resets_the_livewire_search_state(): void
    {
        $this->createEntry(seatNumber: 'DEMO-087', nameEnglish: 'Sok Dara', nameKhmer: 'សុខ ដារា');

        $role = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        $permission = \Spatie\Permission\Models\Permission::query()->firstOrCreate([
            'name' => 'ViewAny:CandidateRequested',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $admin = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Search Admin',
            'username' => 'search_admin',
            'email' => 'search-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole($role);

        $this->actingAs($admin);

        Livewire::test(ListCandidateRequested::class)
            ->set('tableSearch', 'NO_SUCH_QA_927')
            ->call('resetTableSearch')
            ->assertSet('tableSearch', '');
    }

    private function search(string $term): Builder
    {
        $query = CandidateRequestedResource::getEloquentQuery();
        $method = new ReflectionMethod(CandidateRequestedTable::class, 'applyGlobalSearch');
        $method->invoke(null, $query, $term);

        return $query;
    }

    private function createEntry(string $seatNumber, string $nameEnglish, string $nameKhmer): CandidateRequested
    {
        $username = strtolower(str_replace('-', '_', $seatNumber)).'_'.strtolower(str_replace(' ', '_', $nameEnglish));
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => $nameKhmer,
            'name_latin' => $nameEnglish,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'seat_number' => $seatNumber,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $form = CustomForm::query()->create([
            'name' => 'Admission Form '.uniqid(),
            'slug' => 'admission-form-'.uniqid(),
            'is_active' => true,
            'menu_placement' => 'sidebar',
            'requires_payment' => false,
        ]);

        $entry = CandidateRequested::query()->create([
            'custom_form_id' => $form->id,
            'created_by' => $user->id,
            'data' => [
                'seat_number' => $seatNumber,
                'first_name_en' => explode(' ', $nameEnglish)[0],
                'last_name_en' => explode(' ', $nameEnglish, 2)[1] ?? '',
                'first_name_kh' => explode(' ', $nameKhmer)[0],
                'last_name_kh' => explode(' ', $nameKhmer, 2)[1] ?? '',
                'candidate_status' => 'pending',
            ],
        ]);

        $entry->forceFill(['review_status' => 'accepted'])->saveQuietly();

        return $entry->fresh();
    }
}
