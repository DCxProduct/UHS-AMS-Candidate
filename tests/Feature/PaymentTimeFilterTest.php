<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentTimeFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));

        $role = Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('ViewAny:Payment', 'web'));
        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Payment filter administrator',
            'username' => 'payment_filter_admin',
            'email' => 'payment_filter_admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_live_time_filters_combine_with_dates_totals_and_export_selection(): void
    {
        $beforeDate = $this->payment('BEFORE-DATE', '2026-10-26 10:00:00');
        $beforeTime = $this->payment('BEFORE-TIME', '2026-10-27 07:59:59');
        $start = $this->payment('START', '2026-10-27 08:00:00', 2);
        $middle = $this->payment('MIDDLE', '2026-10-27 12:10:00', 3);
        $end = $this->payment('END', '2026-10-27 17:00:59', 4);
        $afterTime = $this->payment('AFTER-TIME', '2026-10-27 17:01:00');
        $afterDate = $this->payment('AFTER-DATE', '2026-10-28 10:00:00');

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '08:00')
            ->set('tableFilters.payment_filters.to_time', '17:00')
            ->assertCanSeeTableRecords([$start, $middle, $end])
            ->assertCanNotSeeTableRecords([$beforeDate, $beforeTime, $afterTime, $afterDate])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 9)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 36000);

        $query = new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery');
        $this->assertEqualsCanonicalizing(
            [$start->getKey(), $middle->getKey(), $end->getKey()],
            $query->invoke($component->instance())->pluck('id')->all(),
        );
        $this->assertSame(
            [$middle->getKey()],
            $query->invoke($component->instance(), [$middle->getKey(), $beforeTime->getKey()])->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$start->getKey(), $end->getKey()],
            $query->invoke($component->instance(), [], true, [$middle->getKey()])->pluck('id')->all(),
        );

        $component->set('tableFilters.payment_filters.receipt_number', 'MIDDLE')
            ->assertCanSeeTableRecords([$middle])
            ->assertCanNotSeeTableRecords([$start, $end])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 3);
    }

    public function test_single_time_bounds_and_clearing_times_update_rows_immediately(): void
    {
        $early = $this->payment('EARLY', '2026-10-27 07:00:00');
        $middle = $this->payment('MIDDAY', '2026-10-27 12:00:30');
        $late = $this->payment('LATE', '2026-10-27 22:00:00');

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '12:00')
            ->assertCanSeeTableRecords([$middle, $late])
            ->assertCanNotSeeTableRecords([$early])
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', '12:00')
            ->assertCanSeeTableRecords([$early, $middle])
            ->assertCanNotSeeTableRecords([$late])
            ->set('tableFilters.payment_filters.to_time', null)
            ->assertCanSeeTableRecords([$early, $middle, $late]);
    }

    public function test_date_and_time_bounds_form_one_continuous_interval_including_intervening_days(): void
    {
        $beforeStart = $this->payment('BEFORE-START', '2026-10-25 17:59:59');
        $start = $this->payment('START-DATETIME', '2026-10-25 18:00:00', 2);
        $firstNight = $this->payment('NIGHT-ONE', '2026-10-25 23:00:00');
        $secondDay = $this->payment('DAY-TWO', '2026-10-26 12:00:00');
        $secondNight = $this->payment('NIGHT-TWO', '2026-10-26 23:00:00');
        $lastMorning = $this->payment('LAST-MORNING', '2026-10-27 04:00:00');
        $end = $this->payment('END-DATETIME', '2026-10-27 09:00:59', 3);
        $afterEnd = $this->payment('AFTER-END', '2026-10-27 09:01:00');

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_date', '2026-10-25')
            ->set('tableFilters.payment_filters.to_date', '2026-10-27')
            ->set('tableFilters.payment_filters.from_time', '18:00')
            ->set('tableFilters.payment_filters.to_time', '09:00')
            ->assertCanSeeTableRecords([$start, $firstNight, $secondDay, $secondNight, $lastMorning, $end])
            ->assertCanNotSeeTableRecords([$beforeStart, $afterEnd])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 9);

        $query = new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery');
        $this->assertEqualsCanonicalizing(
            [$start->getKey(), $firstNight->getKey(), $secondDay->getKey(), $secondNight->getKey(), $lastMorning->getKey(), $end->getKey()],
            $query->invoke($component->instance())->pluck('id')->all(),
        );

        $component->set('tableFilters.payment_filters.to_date', '2026-10-25')
            ->assertCanNotSeeTableRecords([$beforeStart, $start, $firstNight, $secondDay, $secondNight, $lastMorning, $end, $afterEnd]);
    }

    public function test_missing_times_use_the_full_start_or_end_day_without_clipping_intervening_days(): void
    {
        $beforeStart = $this->payment('BEFORE-START', '2026-10-25 07:00:00');
        $firstEvening = $this->payment('FIRST-EVENING', '2026-10-25 22:00:00');
        $lastMorning = $this->payment('LAST-MORNING', '2026-10-26 03:00:00');
        $afterEnd = $this->payment('AFTER-END', '2026-10-26 20:00:00');

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_date', '2026-10-25')
            ->set('tableFilters.payment_filters.to_date', '2026-10-26')
            ->set('tableFilters.payment_filters.from_time', '08:00')
            ->assertCanSeeTableRecords([$firstEvening, $lastMorning, $afterEnd])
            ->assertCanNotSeeTableRecords([$beforeStart])
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', '09:00')
            ->assertCanSeeTableRecords([$beforeStart, $firstEvening, $lastMorning])
            ->assertCanNotSeeTableRecords([$afterEnd]);
    }

    public function test_equal_time_bounds_include_the_whole_minute_and_midnight_bounds_work(): void
    {
        $midnight = $this->payment('MIDNIGHT', '2026-10-27 00:00:45');
        $before = $this->payment('BEFORE-MINUTE', '2026-10-27 08:14:59');
        $start = $this->payment('MINUTE-START', '2026-10-27 08:15:00');
        $end = $this->payment('MINUTE-END', '2026-10-27 08:15:59');
        $after = $this->payment('AFTER-MINUTE', '2026-10-27 08:16:00');
        $lastMinute = $this->payment('LAST-MINUTE', '2026-10-27 23:59:59');

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '08:15')
            ->set('tableFilters.payment_filters.to_time', '08:15')
            ->assertCanSeeTableRecords([$start, $end])
            ->assertCanNotSeeTableRecords([$midnight, $before, $after, $lastMinute])
            ->set('tableFilters.payment_filters.from_time', '00:00')
            ->set('tableFilters.payment_filters.to_time', '23:59')
            ->assertCanSeeTableRecords([$midnight, $before, $start, $end, $after, $lastMinute]);
    }

    public function test_invalid_time_ranges_return_no_rows_without_a_query_error(): void
    {
        $payment = $this->payment('VALID-RECORD', '2026-10-27 12:00:00');

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '16:00')
            ->set('tableFilters.payment_filters.to_time', '15:00')
            ->assertCanNotSeeTableRecords([$payment])
            ->set('tableFilters.payment_filters.to_time', null)
            ->set('tableFilters.payment_filters.from_time', 'not-a-time')
            ->assertCanNotSeeTableRecords([$payment])
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', '25:00')
            ->assertCanNotSeeTableRecords([$payment])
            ->set('tableFilters.payment_filters.to_time', null)
            ->assertCanSeeTableRecords([$payment]);
    }

    public function test_legacy_date_only_payments_use_the_same_day_recording_time_without_rewriting_history(): void
    {
        $this->travelTo(now()->setTime(10, 39, 25));
        $legacy = $this->payment('LEGACY-DATE-ONLY', '2026-10-27', 5);
        $explicitEarly = $this->payment('EXPLICIT-EARLY', '2026-10-27 03:00:00');
        $explicitLate = $this->payment('EXPLICIT-LATE', '2026-10-27 17:00:00');

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '04:00')
            ->set('tableFilters.payment_filters.to_time', '16:16')
            ->assertCanSeeTableRecords([$legacy])
            ->assertCanNotSeeTableRecords([$explicitEarly, $explicitLate])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5);

        $query = new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery');
        $this->assertSame([$legacy->getKey()], $query->invoke($component->instance())->pluck('id')->all());
        $this->assertSame('2026-10-27 00:00:00', $legacy->fresh()->getRawOriginal('datetime_pay'));

        $component->set('tableFilters.payment_filters.to_time', '10:38')
            ->assertCanNotSeeTableRecords([$legacy]);
    }

    public function test_legacy_fallback_preserves_paid_dates_and_explicit_payment_clocks(): void
    {
        $this->travelTo(now()->setTime(10, 39, 25));
        $olderDate = $this->payment('OLDER-PAID-DATE', '2026-10-26');
        $explicit = $this->payment('EXPLICIT-CLOCK', '2026-10-27 08:00:00');

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_time', '10:00')
            ->set('tableFilters.payment_filters.to_time', '11:00')
            ->assertCanNotSeeTableRecords([$olderDate, $explicit])
            ->set('tableFilters.payment_filters.from_date', '2026-10-26')
            ->set('tableFilters.payment_filters.to_date', '2026-10-26')
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', null)
            ->assertCanSeeTableRecords([$olderDate])
            ->assertCanNotSeeTableRecords([$explicit]);
    }

    private function payment(string $receipt, string $paidAt, int $amount = 1): Payment
    {
        return Payment::query()->create([
            'receipt_number' => $receipt,
            'datetime_pay' => $paidAt,
            'amount_usd' => $amount,
            'amount_kh' => $amount * 4000,
        ]);
    }
}
