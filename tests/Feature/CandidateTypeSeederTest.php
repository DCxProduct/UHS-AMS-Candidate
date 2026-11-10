<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\UserType;
use App\Support\UserTypeOptions;
use Database\Seeders\CandidateTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionProperty;
use Tests\TestCase;

class CandidateTypeSeederTest extends TestCase
{
    use RefreshDatabase;

    private const RETAINED_KEYS = [
        'national_entrance_exam_application_bachelor',
        'national_entrance_exam_application_associate',
        'national_exit_exam_application_bachelor',
        'national_exit_exam_application_associate',
        'continuing_bachelors_degree',
        'continuing_master_of_science',
        'continuing_medical_specialty',
    ];

    private const REMOVED_KEYS = [
        'national_entrance_exam_application_dental_surgeon',
        'national_entrance_exam_application_doctor_of_medicine',
        'national_exit_exam_application_master_of_science',
        'national_exit_exam_application_dental_surgeon',
        'national_exit_exam_application_doctor_of_medicine',
        'national_exit_exam_application_medical_specialty',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetUserTypeOptionsCaches();
    }

    protected function tearDown(): void
    {
        $this->resetUserTypeOptionsCaches();

        parent::tearDown();
    }

    public function test_fresh_seeding_and_runtime_initialization_only_create_the_seven_retained_types(): void
    {
        $this->seed(CandidateTypeSeeder::class);

        $this->assertSame(self::RETAINED_KEYS, UserType::query()->orderBy('display_order')->pluck('key')->all());
        $this->assertSame(range(1, 7), UserType::query()->orderBy('display_order')->pluck('display_order')->all());

        $this->resetUserTypeOptionsCaches();
        $this->assertSame(self::RETAINED_KEYS, array_keys(UserTypeOptions::options()));

        foreach (self::REMOVED_KEYS as $key) {
            $this->assertDatabaseMissing('user_types', ['key' => $key]);
            $this->assertDatabaseMissing('roles', ['name' => $key, 'guard_name' => 'web']);
        }
        $this->assertDatabaseCount('user_types', 7);
    }

    public function test_reseeding_updates_admission_labels_without_changing_existing_keys_or_ids(): void
    {
        $defaults = collect(UserTypeOptions::defaultRecords())->keyBy('key');
        $expectedKhmerLabels = [
            'continuing_bachelors_degree' => 'ជ្រើសរើសចូលរៀនថ្នាក់បន្ត-បរិញ្ញាបត្រ',
            'continuing_master_of_science' => 'ជ្រើសរើសចូលរៀនថ្នាក់-បរិញ្ញាបត្រជាន់ខ្ពស់',
            'continuing_medical_specialty' => 'ប្រឡងជ្រើសរើសចូលរៀនថ្នាក់-វេជ្ជបណ្ឌិតឯកទេស',
        ];
        $originalIds = [];

        foreach (array_slice(self::RETAINED_KEYS, -3) as $key) {
            $record = UserType::query()->create([
                ...$defaults[$key],
                'label_en' => 'Old admission label',
                'label_kh' => 'ឈ្មោះចាស់',
            ]);
            $originalIds[$key] = $record->id;
        }

        $this->seed(CandidateTypeSeeder::class);
        $this->seed(CandidateTypeSeeder::class);

        foreach ($originalIds as $key => $id) {
            $record = UserType::query()->where('key', $key)->sole();
            $this->assertSame($id, $record->id);
            $this->assertSame($defaults[$key]['label_en'], $record->label_en);
            $this->assertSame($expectedKhmerLabels[$key], $record->label_kh);
            $this->assertDatabaseHas('roles', ['name' => $key, 'guard_name' => 'web']);
        }
        $this->assertDatabaseCount('user_types', 7);
    }

    public function test_reseeding_does_not_delete_an_existing_historical_type_or_its_role(): void
    {
        $historical = UserType::query()->create([
            'key' => 'national_exit_exam_application_master_of_science',
            'label_en' => 'Historical national exit master',
            'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-បរិញ្ញាបត្រជាន់ខ្ពស់',
            'group_name' => 'master_of_science',
            'is_active' => false,
        ]);
        $role = Role::query()->where('name', $historical->key)->sole();

        $this->seed(CandidateTypeSeeder::class);
        UserTypeOptions::options();

        $this->assertSame($historical->id, $historical->fresh()->id);
        $this->assertSame('Historical national exit master', $historical->fresh()->label_en);
        $this->assertFalse($historical->fresh()->is_active);
        $this->assertSame($role->id, Role::query()->where('name', $historical->key)->sole()->id);
        $this->assertDatabaseCount('user_types', 8);
    }

    private function resetUserTypeOptionsCaches(): void
    {
        foreach ([
            'defaultsEnsured' => false,
            'optionsCache' => [],
            'colorsCache' => null,
            'candidateManagedRoleKeysCache' => null,
            'normalizedUserTypeCache' => [],
        ] as $name => $value) {
            (new ReflectionProperty(UserTypeOptions::class, $name))->setValue(null, $value);
        }
    }
}
