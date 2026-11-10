<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateTypes\Pages\CreateCandidateType;
use App\Filament\Admin\Resources\CandidateTypes\Pages\EditCandidateType;
use App\Filament\Admin\Resources\DegreeLevels\Pages\CreateDegreeLevel;
use App\Filament\Admin\Resources\DegreeLevels\Pages\EditDegreeLevel;
use App\Filament\Admin\Resources\Majors\Pages\CreateMajor;
use App\Filament\Admin\Resources\Majors\Pages\EditMajor;
use App\Filament\Admin\Resources\PaymentTypes\Pages\CreatePaymentType;
use App\Filament\Admin\Resources\PaymentTypes\Pages\EditPaymentType;
use App\Filament\Admin\Resources\RoleTypes\Pages\CreateRoleType;
use App\Filament\Admin\Resources\RoleTypes\Pages\EditRoleType;
use App\Models\DegreeLevel;
use App\Models\Major;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\RoleType;
use App\Models\User;
use App\Models\UserType;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConfigurationKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        app()->setLocale('en');

        $admin = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Configuration Admin',
            'username' => 'configuration_admin',
            'email' => 'configuration-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'test-only-password',
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    public static function configurationResources(): array
    {
        return [
            'candidate types' => [
                CreateCandidateType::class, EditCandidateType::class, UserType::class, 'label_en',
                ['label_en' => 'Configuration Example', 'label_kh' => 'ប្រភេទបេក្ខជន', 'is_active' => true],
            ],
            'degree levels' => [
                CreateDegreeLevel::class, EditDegreeLevel::class, DegreeLevel::class, 'label_en',
                ['label_en' => 'Configuration Example', 'label_kh' => 'កម្រិតសិក្សា', 'is_active' => true],
            ],
            'role types' => [
                CreateRoleType::class, EditRoleType::class, RoleType::class, 'label_en',
                ['label_en' => 'Configuration Example', 'label_kh' => 'តួនាទី', 'is_active' => true],
            ],
            'payment types' => [
                CreatePaymentType::class, EditPaymentType::class, PaymentType::class, 'name_en',
                ['name_en' => 'Configuration Example', 'abbreviation' => 'EXAMPLE', 'method' => 'bank', 'is_active' => true],
            ],
            'majors' => [
                CreateMajor::class, EditMajor::class, Major::class, 'name_en',
                ['name_en' => 'Configuration Example', 'name_kh' => 'ជំនាញ', 'abbreviation' => 'EXAMPLE', 'is_active' => true],
            ],
        ];
    }

    #[DataProvider('configurationResources')]
    public function test_records_can_be_created_without_keys_even_when_display_names_repeat(
        string $createPage,
        string $editPage,
        string $model,
        string $nameField,
        array $data,
    ): void {
        foreach (range(1, 2) as $attempt) {
            Livewire::test($createPage)
                ->assertFormFieldDoesNotExist('key')
                ->fillForm($data)
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $keys = $model::query()->where($nameField, $data[$nameField])->pluck('key');

        $this->assertCount(2, $keys);
        $this->assertCount(2, $keys->unique());
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z0-9_-]+$/', $key);
            $this->assertLessThanOrEqual($model === Major::class ? 100 : 255, strlen($key));
        }
    }

    #[DataProvider('configurationResources')]
    public function test_editing_a_display_name_preserves_its_existing_key(
        string $createPage,
        string $editPage,
        string $model,
        string $nameField,
        array $data,
    ): void {
        $record = $model::query()->create(['key' => 'legacy_workflow_key', ...$data]);

        Livewire::test($editPage, ['record' => $record->getKey()])
            ->assertFormFieldDoesNotExist('key')
            ->fillForm([$nameField => 'Renamed Configuration'])
            ->set('data.key', 'injected_key')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Renamed Configuration', $record->fresh()->{$nameField});
        $this->assertSame('legacy_workflow_key', $record->fresh()->key);
    }

    public function test_editing_degree_candidate_and_payment_labels_preserves_their_connections(): void
    {
        $degree = DegreeLevel::query()->create(['key' => 'legacy_degree', 'label_en' => 'Original degree']);
        $candidateType = UserType::query()->create([
            'key' => 'legacy_candidate', 'label_en' => 'Original candidate', 'label_kh' => 'បេក្ខជន',
            'group_name' => $degree->key, 'is_active' => true,
        ]);
        $role = Role::query()->where('name', $candidateType->key)->sole();
        $paymentType = PaymentType::query()->create([
            'key' => 'legacy_bank', 'name_en' => 'Original bank', 'abbreviation' => 'BANK',
            'method' => 'bank', 'is_active' => true,
        ]);
        $payment = Payment::query()->create([
            'type_payment' => $paymentType->key, 'receipt_number' => 'LEGACY-KEY-001', 'amount_usd' => 5,
        ]);

        Livewire::test(EditDegreeLevel::class, ['record' => $degree->id])
            ->fillForm(['label_en' => 'Renamed degree'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditCandidateType::class, ['record' => $candidateType->id])
            ->fillForm(['label_en' => 'Renamed candidate'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditPaymentType::class, ['record' => $paymentType->id])
            ->fillForm(['name_en' => 'Renamed bank'])->call('save')->assertHasNoFormErrors();

        $this->assertSame($degree->key, $candidateType->fresh()->group_name);
        $this->assertSame($candidateType->key, $role->fresh()->name);
        $this->assertSame($paymentType->id, $payment->fresh()->paymentType->id);
        $this->assertSame('Renamed bank', $payment->fresh()->paymentType->name_en);
    }

    public function test_khmer_only_major_and_payment_without_names_can_be_created(): void
    {
        Livewire::test(CreateMajor::class)
            ->fillForm(['name_kh' => 'មុខវិជ្ជាថ្មី', 'name_en' => null, 'abbreviation' => 'KHM', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $major = Major::query()->where('abbreviation', 'KHM')->sole();
        $this->assertNotEmpty($major->key);
        $this->assertSame('មុខវិជ្ជាថ្មី', $major->name_kh);

        Livewire::test(CreatePaymentType::class)
            ->fillForm(['abbreviation' => 'WIRE', 'name_en' => null, 'name_kh' => null, 'method' => 'bank', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();
        $this->assertNotEmpty(PaymentType::query()->where('abbreviation', 'WIRE')->sole()->key);
    }

    public function test_generated_candidate_keys_do_not_reuse_system_roles(): void
    {
        $existingRole = Role::findOrCreate('review_manager', 'web');

        foreach (['Candidate', 'Admin', 'Cashier', 'Review Manager'] as $label) {
            Livewire::test(CreateCandidateType::class)
                ->fillForm(['label_en' => $label, 'label_kh' => 'បេក្ខជន', 'is_active' => true])
                ->call('create')->assertHasNoFormErrors();

            $type = UserType::query()->where('label_en', $label)->sole();
            $this->assertNotContains($type->key, ['candidate', 'student', 'admin', 'cashier', 'review_manager']);
            $this->assertDatabaseHas('roles', ['name' => $type->key, 'guard_name' => 'web']);
        }

        $this->assertSame('review_manager', $existingRole->fresh()->name);
    }

    public function test_generated_role_type_key_avoids_the_legacy_system_admin_alias(): void
    {
        Livewire::test(CreateRoleType::class)
            ->fillForm(['label_en' => 'System Admin', 'is_active' => true])
            ->call('create')->assertHasNoFormErrors();

        $roleType = RoleType::query()->where('label_en', 'System Admin')->sole();
        $this->assertNotSame('system_admin', $roleType->key);
        $this->assertArrayHasKey($roleType->key, RoleType::options());
        $this->assertSame($roleType->key, $roleType->fresh()->key);
    }

    public function test_long_major_names_get_distinct_keys_within_the_database_limit(): void
    {
        $name = str_repeat('Long major name ', 15);
        foreach (range(1, 2) as $attempt) {
            Livewire::test(CreateMajor::class)
                ->fillForm(['name_en' => $name, 'name_kh' => 'ជំនាញ', 'abbreviation' => 'LONG', 'is_active' => true])
                ->call('create')->assertHasNoFormErrors();
        }

        $keys = Major::query()->where('name_en', $name)->pluck('key');
        $this->assertCount(2, $keys->unique());
        foreach ($keys as $key) {
            $this->assertLessThanOrEqual(100, strlen($key));
        }
    }
}
