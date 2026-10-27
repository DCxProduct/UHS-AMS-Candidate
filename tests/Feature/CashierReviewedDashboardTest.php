<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
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
    public function test_cashier_dashboard_omits_the_reviewed_card_and_preserves_payment_counts(string $role): void
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
        $this->assertCount(3, $stats);
        $this->assertSame([
            __('dashboard.payment_status_lists'),
            __('dashboard.paid_records'),
            __('dashboard.unpaid_statuses'),
        ], array_map(fn ($stat) => $stat->getLabel(), $stats));
        $this->assertSame(['4', '2', '2'], array_map(fn ($stat) => $stat->getValue(), $stats));
        $this->assertNotContains(__('dashboard.payment_records'), array_map(fn ($stat) => $stat->getLabel(), $stats));
        $this->assertNotContains(__('dashboard.cashier_reviewed_applicants'), array_map(fn ($stat) => $stat->getLabel(), $stats));

        app()->setLocale('km');
        Livewire::test(CashierStatsOverview::class)
            ->assertSuccessful()
            ->assertDontSee(__('dashboard.payment_records'))
            ->assertDontSee('ចំនួនអ្នកដាក់ពាក្យដែលពិនិត្យរួចរាល់')
            ->assertDontSee(__('dashboard.cashier_reviewed_unpaid_hint'))
            ->assertSee(__('dashboard.payment_status_lists'))
            ->assertSee(__('dashboard.paid_records'))
            ->assertSee(__('dashboard.unpaid_statuses'));

        $this->pay($passed);
        $this->assertSame(['4', '3', '1'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
    }

    #[DataProvider('cashierRoles')]
    public function test_all_dashboard_counts_survive_menu_clear_and_follow_new_applications_and_payments(string $role): void
    {
        $cashier = $this->staff($role);
        $cashier->givePermissionTo(Permission::findOrCreate('ClearData:PaymentStatusList', 'web'));
        $this->actingAs($cashier);
        $form = $this->form('cumulative-total');
        $paid = $this->entry($form, 'approved');
        $unpaid = $this->entry($form, 'approved');
        $outsideFilter = $this->entry($this->form('outside-clear-filter'), 'approved');
        $this->pay($paid);
        $paymentBefore = Payment::query()->sole()->getAttributes();

        $this->assertSame(['3', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $component = Livewire::test(ListPaymentStatusLists::class)
            ->assertCanSeeTableRecords([$paid, $unpaid, $outsideFilter])
            ->call('clearDataFromTableSelection', [$paid->getKey()])
            ->assertCanNotSeeTableRecords([$paid])
            ->assertCanSeeTableRecords([$unpaid, $outsideFilter]);

        $this->assertTrue($paid->fresh()->data[PaymentStatusListResource::HIDDEN_FLAG]);
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['3', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));

        $component->set('tableFilters.payment_status_list_filters.form_id', (string) $form->getKey())
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$outsideFilter])
            ->call('clearDataFromTableSelection', [], true, [])
            ->assertCanNotSeeTableRecords([$paid, $unpaid]);

        $this->assertTrue($unpaid->fresh()->data[PaymentStatusListResource::HIDDEN_FLAG]);
        $this->assertArrayNotHasKey(PaymentStatusListResource::HIDDEN_FLAG, $outsideFilter->fresh()->data);
        $this->assertSame('1', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['3', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));

        $this->entry($form, 'approved');
        $this->assertSame(['4', '1', '3'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame($paymentBefore, Payment::query()->sole()->getAttributes());
        $this->assertDatabaseCount('custom_form_entries', 4);

        Livewire::test(ListPaymentStatusLists::class)
            ->call('clearDataFromTableSelection', [], true, [])
            ->assertCanNotSeeTableRecords([$paid, $unpaid, $outsideFilter]);
        $this->assertSame('0', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['4', '1', '3'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->assertSame($paymentBefore, Payment::query()->sole()->getAttributes());

        $this->pay($unpaid);
        $this->assertSame(['4', '2', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->assertSame('0', PaymentStatusListResource::getNavigationBadge());

        $newPaid = $this->entry($form, 'approved');
        $this->pay($newPaid);
        $this->assertSame(['5', '3', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->entry($form, 'approved');
        $this->assertSame(['6', '3', '3'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
    }

    public function test_cumulative_query_retains_application_owner_scope(): void
    {
        $candidate = User::query()->forceCreate([
            'registration_type' => 'student',
            'name' => 'Candidate',
            'username' => 'cumulative_candidate',
            'email' => 'cumulative-candidate@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->actingAs($candidate);
        $form = $this->form('owner-cumulative-total');
        $own = $this->entry($form, 'approved');
        $own->forceFill(['created_by' => $candidate->getKey()])->saveQuietly();
        $ownCleared = $this->entry($form, 'approved', [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $ownCleared->forceFill(['created_by' => $candidate->getKey()])->saveQuietly();
        $other = $this->entry($form, 'approved');
        $other->forceFill(['created_by' => null])->saveQuietly();
        $otherCleared = $this->entry($form, 'approved', [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $otherCleared->forceFill(['created_by' => null])->saveQuietly();

        $this->assertEqualsCanonicalizing(
            [$own->getKey(), $ownCleared->getKey()],
            PaymentStatusListResource::getDashboardEloquentQuery()->pluck('id')->all(),
        );
        $this->assertSame([$own->getKey()], PaymentStatusListResource::getEloquentQuery()->pluck('id')->all());
        $this->assertFalse(PaymentStatusListResource::getDashboardEloquentQuery()->whereKey($other->getKey())->exists());
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
