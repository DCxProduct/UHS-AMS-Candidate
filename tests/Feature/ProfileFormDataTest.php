<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProfileFormData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileFormDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_form_prefills_registered_latin_names_without_overwriting_existing_values(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'Dara Sok',
            'name_latin' => 'Dara Sok',
            'username' => 'student_010123456',
            'email' => 'dara-sok@example.test',
            'phone' => '010123456',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);

        $profileFormId = DB::table('custom_forms')->insertGetId([
            'name' => 'Profile',
            'slug' => 'profile',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['first_name_en', 'last_name_en'] as $sort => $fieldName) {
            DB::table('custom_form_fields')->insert([
                'custom_form_id' => $profileFormId,
                'name' => $fieldName,
                'label' => $fieldName,
                'type' => 'text_input',
                'required' => true,
                'options' => json_encode([]),
                'sort' => $sort + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Auth::login($user);

        $prefilled = app(ProfileFormData::class)->prefillStateForForm($profileFormId, [
            'data' => [],
        ]);

        $this->assertSame('Dara', data_get($prefilled, 'data.first_name_en'));
        $this->assertSame('Sok', data_get($prefilled, 'data.last_name_en'));

        $existingValues = app(ProfileFormData::class)->prefillStateForForm($profileFormId, [
            'data' => [
                'first_name_en' => 'ExistingFirst',
                'last_name_en' => 'ExistingLast',
            ],
        ]);

        $this->assertSame('ExistingFirst', data_get($existingValues, 'data.first_name_en'));
        $this->assertSame('ExistingLast', data_get($existingValues, 'data.last_name_en'));
    }
}
