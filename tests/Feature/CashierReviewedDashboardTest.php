<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
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
use Illuminate\Support\Facades\DB;
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
        $this->assertSame(['3', '2', '1'], array_map(fn ($stat) => $stat->getValue(), $stats));
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
        $this->assertSame(['3', '3', '0'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
    }

    #[DataProvider('cashierRoles')]
    public function test_dashboard_counts_follow_visible_payment_status_rows_and_new_data_without_changing_source_records(string $role): void
    {
        $cashier = $this->staff($role);
        $cashier->givePermissionTo(Permission::findOrCreate('ClearData:PaymentStatusList', 'web'));
        $this->actingAs($cashier);
        $form = $this->form('menu-only-clear-total');
        $paid = $this->entry($form, 'approved');
        $unpaid = $this->entry($form, 'approved');
        $outsideFilter = $this->entry($this->form('outside-clear-filter'), 'approved');
        $this->pay($paid);
        $paidPaymentId = Payment::query()->sole()->id;
        $archivedBefore = (array) DB::table('cashier_dashboard_totals')->sole();

        $this->assertSame(['3', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $component = Livewire::test(ListPaymentStatusLists::class)
            ->assertCanSeeTableRecords([$paid, $unpaid, $outsideFilter])
            ->call('clearDataFromTableSelection', [$paid->getKey()])
            ->assertCanNotSeeTableRecords([$paid])->assertCanSeeTableRecords([$unpaid, $outsideFilter]);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $paid->id]);
        $this->assertNotNull($paid->fresh()->payment_status_lists_deleted_at);
        $this->assertDatabaseHas('payments', ['id' => $paidPaymentId, 'status_payt' => 'paid']);
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['2', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));

        $component->set('tableFilters.payment_status_list_filters.form_id', (string) $form->getKey())
            ->assertCanSeeTableRecords([$unpaid])->assertCanNotSeeTableRecords([$outsideFilter])
            ->call('clearDataFromTableSelection', [], true, [])->assertCanNotSeeTableRecords([$paid, $unpaid]);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $unpaid->id]);
        $this->assertNotNull($unpaid->fresh()->payment_status_lists_deleted_at);
        $this->assertDatabaseHas('custom_form_entries', ['id' => $outsideFilter->id]);
        $this->assertSame('1', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['1', '1', '1'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));

        $newPaid = $this->entry($form, 'approved');
        $this->assertSame(['2', '1', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->pay($newPaid);
        $this->assertSame(['2', '2', '1'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $newUnpaid = $this->entry($form, 'approved');
        $this->assertSame(['3', '2', '2'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));

        Livewire::test(ListPaymentStatusLists::class)->call('clearDataFromTableSelection', [], true, [])
            ->assertCanNotSeeTableRecords([$outsideFilter, $newPaid, $newUnpaid]);
        $this->assertSame('0', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['0', '2', '0'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->assertDatabaseCount('custom_form_entries', 5);
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame($archivedBefore, (array) DB::table('cashier_dashboard_totals')->sole());
        Livewire::test(ListPaymentStatusLists::class)->call('clearDataFromTableSelection');
        $this->assertSame(['0', '2', '0'], $this->totals());
        $this->assertDatabaseHas('custom_forms', ['id' => $form->id]);
        $this->assertDatabaseHas('users', ['id' => $cashier->id]);
        $afterCleanup = $this->entry($form, 'approved');
        $this->assertSame(['1', '2', '1'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
        $this->pay($afterCleanup);
        $this->assertSame(['1', '3', '0'], array_map(fn ($stat) => $stat->getValue(), $this->stats()));
    }

    public function test_payment_menu_clears_reduce_only_the_paid_card_and_repeated_clears_do_not_change_it_again(): void
    {
        $cashier = $this->staff('cashier_manager');
        $cashier->givePermissionTo(Permission::findOrCreate('ClearData:Payment', 'web'));
        $this->actingAs($cashier);
        $paid = Payment::query()->create(['receipt_number' => 'MENU-PAID', 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $returned = Payment::query()->create(['receipt_number' => 'MENU-RETURNED', 'status_payt' => 'return', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $archivedBefore = (array) DB::table('cashier_dashboard_totals')->sole();
        $this->assertSame(['0', '1', '0'], $this->totals());
        $component = Livewire::test(ListPayments::class)->call('clearDataFromTableSelection')->assertCanNotSeeTableRecords([$paid, $returned]);
        $after = Payment::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->assertDatabaseCount('payments', 2);
        $this->assertSame(['0', '0', '0'], $this->totals());
        $this->travel(1)->hour();
        $component->call('clearDataFromTableSelection')->call('clearDataFromTableSelection', [$paid->id]);
        $this->assertSame($after, Payment::query()->orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(['0', '0', '0'], $this->totals());
        $this->assertSame($archivedBefore, (array) DB::table('cashier_dashboard_totals')->sole());
        Payment::query()->create(['receipt_number' => 'MENU-NEXT', 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $this->assertSame(['0', '1', '0'], $this->totals());
    }

    public function test_preexisting_historical_offsets_are_ignored_and_do_not_prevent_dashboard_counts_from_decreasing(): void
    {
        $cashier = $this->staff('cashier_manager');
        $cashier->givePermissionTo(Permission::findOrCreate('ClearData:PaymentStatusList', 'web'));
        $this->actingAs($cashier);
        DB::table('cashier_dashboard_totals')->where('id', 1)->update(['applications' => 10, 'paid' => 4, 'unpaid' => 6]);
        $form = $this->form('historical-offsets');
        $paid = $this->entry($form, 'approved');
        $unpaid = $this->entry($form, 'approved');
        $this->pay($paid);
        $this->assertSame(['2', '1', '1'], $this->totals());
        Livewire::test(ListPaymentStatusLists::class)->call('clearDataFromTableSelection')->assertCanNotSeeTableRecords([$paid, $unpaid]);
        $this->assertSame(['0', '1', '0'], $this->totals());
        $this->assertSame(['id' => 1, 'applications' => 10, 'paid' => 4, 'unpaid' => 6], (array) DB::table('cashier_dashboard_totals')->sole());
        $this->assertDatabaseCount('custom_form_entries', 2);
        $this->assertDatabaseCount('payments', 1);
    }

    #[DataProvider('cashierRoles')]
    public function test_paid_card_excludes_ams_receipts_and_is_zero_when_cashier_menus_are_empty(string $role): void
    {
        $cashier = $this->staff($role);
        $cashier->givePermissionTo(Permission::findOrCreate('ClearData:Payment', 'web'));
        $this->actingAs($cashier);
        $local = Payment::query()->create(['receipt_number' => 'LOCAL-CARD', 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $ams = Payment::query()->create(['receipt_number' => 'AMS-CARD', 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $ams->forceFill(['integration_source' => 'ams'])->saveQuietly();
        $this->assertSame('1', PaymentResource::getNavigationBadge());
        $this->assertSame(['0', '1', '0'], $this->totals());
        Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$local])->assertCanNotSeeTableRecords([$ams])->call('clearDataFromTableSelection');
        $this->assertSame('0', PaymentResource::getNavigationBadge());
        $this->assertSame('0', PaymentStatusListResource::getNavigationBadge());
        $this->assertSame(['0', '0', '0'], $this->totals());
        $this->assertSame('paid', $local->fresh()->status_payt);
        $this->assertSame('paid', $ams->fresh()->status_payt);
        $this->assertNull($ams->fresh()->records_cleared_at);
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

    private function totals(): array
    {
        return array_map(fn ($stat): string => (string) $stat->getValue(), $this->stats());
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
