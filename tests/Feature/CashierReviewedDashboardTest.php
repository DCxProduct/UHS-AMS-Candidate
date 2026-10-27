<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashierReviewedDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
    }

    public static function cashierRoles(): array
    {
        return [['cashier_manager'], ['cashier_officer']];
    }

    #[DataProvider('cashierRoles')]
    public function test_cashier_dashboard_shows_reviewed_unpaid_count_first_and_updates_after_payment(string $role): void
    {
        $this->actingAs($this->staff($role));
        $form = $this->form('reviewed-payment');
        $approved = $this->entry($form, 'approved');
        $accepted = $this->entry($form, 'accepted');
        $passed = $this->entry($form, 'passed');
        $this->entry($form, 'approved', [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $this->entry($this->form('reviewed-no-payment', requiresPayment: false), 'approved');

        foreach (['pending', 'draft', 'rejected', 'failed', 'send_back', 'returned', '', null] as $status) {
            $this->entry($form, $status);
        }
        $this->entry($this->form('profile'), 'approved');
        $this->entry($this->form('inactive-review', active: false), 'approved');
        $this->pay($approved);
        $this->pay($accepted);

        $this->assertContains(CashierStatsOverview::class, (new Dashboard)->getWidgets());
        $this->assertTrue(CashierStatsOverview::canView());
        $stats = $this->stats();
        $this->assertCount(4, $stats);
        $this->assertSame([
            __('dashboard.payment_status_lists'),
            __('dashboard.paid_records'),
            __('dashboard.unpaid_statuses'),
        ], array_map(fn ($stat) => $stat->getLabel(), array_slice($stats, 1, 3)));
        $this->assertSame(['3', '2', '1'], array_map(fn ($stat) => $stat->getValue(), array_slice($stats, 1, 3)));
        $this->assertNotContains(__('dashboard.payment_records'), array_map(fn ($stat) => $stat->getLabel(), $stats));
        $this->assertSame(__('dashboard.cashier_reviewed_applicants'), $stats[0]->getLabel());
        $this->assertSame(__('dashboard.cashier_reviewed_unpaid_hint'), $stats[0]->getDescription());
        $this->assertSame('1', $stats[0]->getValue());

        app()->setLocale('km');
        Livewire::test(CashierStatsOverview::class)
            ->assertSuccessful()
            ->assertDontSee(__('dashboard.payment_records'))
            ->assertSee('ចំនួនអ្នកដាក់ពាក្យដែលពិនិត្យរួចរាល់')
            ->assertSee(__('dashboard.cashier_reviewed_unpaid_hint'));

        $this->pay($passed);
        $this->assertSame(['0', '3', '3', '0'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
    }

    #[DataProvider('cashierRoles')]
    public function test_reviewed_count_respects_existing_form_access_restrictions(string $role): void
    {
        $user = $this->staff($role);
        $this->actingAs($user);
        $allowed = $this->form('allowed-reviewed');
        $excluded = $this->form('excluded-reviewed');
        $this->entry($allowed, 'approved');
        $this->entry($allowed, 'passed');
        $this->entry($excluded, 'accepted');
        $restrictedRole = Role::findByName($role, 'web');
        $restrictedRole->forceFill([
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => [$allowed->getKey()],
        ])->save();

        $this->assertSame('2', $this->stats()[0]->getValue());

        $restrictedRole->forceFill(['allowed_custom_form_ids' => []])->save();
        $this->assertSame('0', $this->stats()[0]->getValue());
    }

    public function test_other_dashboard_roles_keep_their_existing_widgets_and_cards(): void
    {
        foreach (['admin', 'registrar_manager'] as $role) {
            $this->actingAs($this->staff($role));
            $widgets = (new Dashboard)->getWidgets();
            $this->assertContains(AdminStatsOverview::class, $widgets);
            $this->assertNotContains(CashierStatsOverview::class, $widgets);
            $this->assertCount(3, $this->stats());
        }

        $this->actingAs($this->staff('cashier'));
        $this->assertContains(CashierStatsOverview::class, (new Dashboard)->getWidgets());
        $this->assertTrue(CashierStatsOverview::canView());
        $this->assertCount(3, $this->stats());
    }

    private function pay(CustomFormEntry $entry): void
    {
        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(),
            'form_id' => $entry->custom_form_id,
            'receipt_number' => 'CASHIER-REVIEWED-'.$entry->getKey(),
            'status_payt' => 'paid',
            'amount_usd' => 5,
            'datetime_pay' => now(),
        ]);
    }

    private function stats(): array
    {
        return (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);
    }

    private function staff(string $role): User
    {
        $staffRole = Role::findOrCreate($role, 'web');
        foreach (['ViewAny:PaymentStatusList', 'ViewAny:Payment'] as $permission) {
            $staffRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => $role,
            'username' => 'reviewed_'.$role,
            'email' => $role.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($staffRole);

        return $user;
    }

    private function form(string $slug, bool $active = true, bool $requiresPayment = true): CustomForm
    {
        return CustomForm::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => $active,
            'requires_payment' => $requiresPayment,
        ]);
    }

    private function entry(CustomForm $form, ?string $status, array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->getKey(),
            'review_status' => $status,
            'data' => ['registration_status' => $status, ...$data],
        ]);
    }
}
