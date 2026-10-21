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

        foreach ([
            'first_name_en' => 'text_input',
            'last_name_en' => 'text_input',
            'contact_email' => 'email',
        ] as $sort => $type) {
            DB::table('custom_form_fields')->insert([
                'custom_form_id' => $profileFormId,
                'name' => $sort,
                'label' => $sort,
                'type' => $type,
                'required' => true,
                'options' => json_encode([]),
                'sort' => 1,
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
        $this->assertSame('dara-sok@example.test', data_get($prefilled, 'data.contact_email'));

        $existingValues = app(ProfileFormData::class)->prefillStateForForm($profileFormId, [
            'data' => [
                'first_name_en' => 'ExistingFirst',
                'last_name_en' => 'ExistingLast',
                'contact_email' => 'changed@example.test',
            ],
        ]);

        $this->assertSame('ExistingFirst', data_get($existingValues, 'data.first_name_en'));
        $this->assertSame('ExistingLast', data_get($existingValues, 'data.last_name_en'));
        $this->assertSame('changed@example.test', data_get($existingValues, 'data.contact_email'));
    }

    public function test_email_builder_fields_prefill_the_candidate_account_email(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'Dara Sok',
            'username' => 'student_010123457',
            'email' => 'dara-sok@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Application',
            'slug' => 'application',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('custom_form_fields')->insert([
            'custom_form_id' => $formId,
            'name' => 'applicant_contact',
            'label' => 'Contact email',
            'type' => 'email',
            'required' => false,
            'options' => json_encode([]),
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login($user);

        $prefilled = app(ProfileFormData::class)->prefillStateForForm($formId, ['data' => []]);

        $this->assertSame('dara-sok@example.test', data_get($prefilled, 'data.applicant_contact'));

        $existing = app(ProfileFormData::class)->prefillStateForForm($formId, [
            'data' => ['applicant_contact' => 'alternate@example.test'],
        ]);

        $this->assertSame('alternate@example.test', data_get($existing, 'data.applicant_contact'));
    }

    public function test_matching_profile_fields_prefill_form_builder_fields_by_name_or_unique_label(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'Dara Sok',
            'username' => 'student_010123458',
            'email' => 'dara-profile@example.test',
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
        $applicationFormId = DB::table('custom_forms')->insertGetId([
            'name' => 'Application',
            'slug' => 'application-profile-prefill',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$profileFormId, 'profile_phone', 'Phone Number'],
            [$profileFormId, 'profile_address', 'Address'],
            [$applicationFormId, 'profile_phone', 'Phone Number'],
            [$applicationFormId, 'application_address', 'Address'],
        ] as [$formId, $name, $label]) {
            DB::table('custom_form_fields')->insert([
                'custom_form_id' => $formId,
                'name' => $name,
                'label' => $label,
                'type' => 'text_input',
                'required' => false,
                'options' => json_encode([]),
                'sort' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('custom_form_entries')->insert([
            'custom_form_id' => $profileFormId,
            'created_by' => $user->id,
            'data' => json_encode([
                'profile_phone' => '012345678',
                'profile_address' => 'Phnom Penh',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login($user);

        $prefilled = app(ProfileFormData::class)->prefillDataForForm($applicationFormId, []);

        $this->assertSame('012345678', data_get($prefilled, 'profile_phone'));
        $this->assertSame('Phnom Penh', data_get($prefilled, 'application_address'));
    }
}
