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

    public function test_profile_repeater_prefills_matching_form_builder_repeater_rows(): void
    {
        $user = User::query()->create([
            'registration_type' => 'student',
            'name' => 'Dara Sok',
            'username' => 'student_010123459',
            'email' => 'dara-repeater@example.test',
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
            'slug' => 'application-repeater-prefill',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $profileRepeaterId = DB::table('custom_form_fields')->insertGetId([
            'custom_form_id' => $profileFormId,
            'name' => 'educations',
            'label' => 'Education',
            'type' => 'repeater',
            'required' => false,
            'options' => json_encode([]),
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetRepeaterId = DB::table('custom_form_fields')->insertGetId([
            'custom_form_id' => $applicationFormId,
            'name' => 'education_history',
            'label' => 'Education',
            'type' => 'repeater',
            'required' => false,
            'options' => json_encode([]),
            'sort' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [$profileFormId, $profileRepeaterId, 'school_name', 'Educational Institution'],
            [$applicationFormId, $targetRepeaterId, 'institution', 'Educational Institution'],
            [$profileFormId, $profileRepeaterId, 'from_year', 'From Year'],
            [$applicationFormId, $targetRepeaterId, 'from_year', 'From Year'],
        ] as [$formId, $parentId, $name, $label]) {
            DB::table('custom_form_fields')->insert([
                'custom_form_id' => $formId,
                'parent_id' => $parentId,
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
                'educations' => [
                    ['data' => ['school_name' => 'University One', 'from_year' => '2020']],
                    ['data' => ['school_name' => 'University Two', 'from_year' => '2024']],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Auth::login($user);

        $prefilled = app(ProfileFormData::class)->prefillDataForForm($applicationFormId, []);

        $this->assertSame([
            ['institution' => 'University One', 'from_year' => '2020'],
            ['institution' => 'University Two', 'from_year' => '2024'],
        ], data_get($prefilled, 'education_history'));

        $blankRows = app(ProfileFormData::class)->prefillStateForForm($applicationFormId, [
            'data' => [
                'education_history' => [
                    'row-one' => ['institution' => '', 'from_year' => 'Candidate value'],
                    'row-two' => ['institution' => '', 'from_year' => ''],
                ],
            ],
        ]);

        $this->assertSame([
            'row-one' => ['institution' => 'University One', 'from_year' => 'Candidate value'],
            'row-two' => ['institution' => 'University Two', 'from_year' => '2024'],
        ], data_get($blankRows, 'data.education_history'));

        $filamentRows = app(ProfileFormData::class)->prefillStateForForm(
            $applicationFormId,
            [
                'data' => [
                    'education_history' => [
                        'row-one' => ['data' => ['institution' => '', 'from_year' => '']],
                        'row-two' => ['data' => ['institution' => '', 'from_year' => '']],
                    ],
                ],
            ],
            wrapRepeaterItemData: true,
        );

        $this->assertSame([
            'row-one' => ['data' => ['institution' => 'University One', 'from_year' => '2020']],
            'row-two' => ['data' => ['institution' => 'University Two', 'from_year' => '2024']],
        ], data_get($filamentRows, 'data.education_history'));
    }
}
