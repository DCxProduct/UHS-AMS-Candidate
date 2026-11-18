<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\Major;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\FormEntryData;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentTableSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetOptionCaches();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $role = Role::findOrCreate('cashier_manager', 'web');
        foreach (['ViewAny:Payment', 'ViewAny:PaymentStatusList', 'Pay:PaymentStatusList'] as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }
        $admin = $this->user('search_receiver', 'admin');
        $admin->forceFill(['name' => 'Receipt Receiver'])->save();
        $admin->assignRole($role);
        $this->actingAs($admin);
        $this->candidate = $this->user('search_owner', 'student');
        $this->candidate->forceFill(['name' => 'Owner Khmer', 'name_latin' => 'OWNER LATIN', 'phone' => '012888999'])->save();
    }

    protected function tearDown(): void
    {
        $this->resetOptionCaches();
        Filament::setCurrentPanel(null);

        parent::tearDown();
    }

    private function resetOptionCaches(): void
    {
        foreach (['fieldOptionCache', 'majorIdLabelCache'] as $property) {
            (new ReflectionProperty(FormEntryData::class, $property))->setValue(null, []);
        }
    }

    public static function menus(): array
    {
        return ['receipts' => [ListPayments::class], 'application payment statuses' => [ListPaymentStatusLists::class]];
    }

    #[DataProvider('menus')]
    public function test_displayed_application_and_receipt_fields_are_searchable_in_both_menus(string $page): void
    {
        $major = Major::query()->create(['key' => 'search-dental', 'name_en' => 'Search Dentistry', 'name_kh' => 'ទន្តសាស្ត្រ', 'is_active' => true]);
        $form = $this->form('Dentist Admissions');
        $entry = $this->entry($form, [
            'first_name_en' => 'SITHAN', 'last_name_en' => 'MANIKA', 'first_name_kh' => 'ស៊ីថាន', 'last_name_kh' => 'ម៉ានីកា',
            'gender' => 'FEMALE', 'phone_number' => '0971234567', 'date_of_birth' => '2001-03-17', 'major' => $major->id,
        ], 'MED260002');
        $payment = $this->payment($entry, ['receipt_number' => 'Receipt-Unique-Blue', 'datetime_pay' => '2026-10-27 13:45:00']);
        $otherEntry = $this->entry($this->form('Other Admissions'), ['full_name_en' => 'OTHER PERSON', 'gender' => 'male'], 'PHA260099');
        $otherPayment = $this->payment($otherEntry, ['receipt_number' => 'Other-Receipt', 'datetime_pay' => '2026-08-04', 'received_by' => null]);
        Payment::query()->whereKey($otherPayment->id)->update(['received_by' => null]);
        [$expected, $excluded] = $page === ListPayments::class ? [$payment, $otherPayment] : [$entry, $otherEntry];
        $component = $this->list($page)->assertCanSeeTableRecords([$expected, $excluded]);

        foreach (['mEd-0002', 'MED260002', 'sItHaN mAnIkA', 'ស៊ីថាន', 'female', 'ស្រី', '0971234567', '17-mAr-2001', 'Search Dentistry', 'dentist admissions', 'rEcEiPt-UnIqUe-BlUe', '27-oCt-2026', 'rEcEiPt rEcEiVeR'] as $search) {
            $component->searchTable($search);
            $this->assertSame([$expected->id], $component->instance()->getFilteredTableQuery()->pluck($expected->qualifyColumn('id'))->all(), $search);
        }
        $method = new ReflectionMethod($page, 'selectedOrFilteredQuery');
        $this->assertSame([$expected->id], $method->invoke($component->instance())->pluck($expected->qualifyColumn('id'))->all());
        $filter = $page === ListPayments::class ? 'payment_filters' : 'payment_status_list_filters';
        $component->set("tableFilters.{$filter}.form_id", (string) $otherEntry->custom_form_id)->assertCountTableRecords(0);
        $component->searchTable('does-not-exist')->assertCountTableRecords(0);
    }

    public function test_payment_status_search_uses_only_the_latest_exact_application_receipt_and_keeps_visibility_scope(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        $form = $this->form('Latest Receipt Search');
        $entry = $this->entry($form, ['full_name_en' => 'VISIBLE PERSON']);
        $this->payment($entry, ['receipt_number' => 'OLD-NEEDLE', 'datetime_pay' => '2026-01-15']);
        $latest = $this->payment($entry, ['receipt_number' => 'NEW-NEEDLE', 'datetime_pay' => '2026-05-21']);
        $unpaid = $this->entry($form, ['full_name_en' => 'UNPAID PERSON']);
        $hidden = $this->entry($form, [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $this->payment($hidden, ['receipt_number' => 'NEW-NEEDLE-HIDDEN']);
        $component = $this->list(ListPaymentStatusLists::class);
        foreach (['old-needle', '15-jan-2026'] as $search) {
            $component->searchTable($search)->assertCountTableRecords(0);
        }
        $component->searchTable('new-needle')->assertCanSeeTableRecords([$entry])->assertCanNotSeeTableRecords([$unpaid, $hidden]);
        $component->searchTable('paid in candidate system')->assertCanSeeTableRecords([$entry])->assertCanNotSeeTableRecords([$unpaid, $hidden]);
        $latest->update(['status_payt' => 'unpaid']);
        $component->searchTable('paid in candidate system')->assertCountTableRecords(0);
        $this->assertSame(3, Payment::query()->count());
    }

    public function test_receipt_search_selects_latest_legacy_entry_and_respects_split_name_and_owner_fallback_precedence(): void
    {
        $form = $this->form('Legacy Search');
        $old = $this->entry($form, ['full_name_en' => 'OLD ENTRY NAME'], 'MED260011');
        $latest = $this->entry($form, ['first_name_en' => 'CURRENT', 'last_name_en' => 'APPLICANT', 'full_name_en' => 'STALE FULL NAME'], 'MED260012');
        $legacy = $this->payment($latest, ['custom_form_entry_id' => null, 'receipt_number' => 'LEGACY-RECEIPT']);
        $linked = $this->payment($old, ['receipt_number' => 'EXPLICIT-RECEIPT']);
        $fallback = $this->payment($this->entry($this->form('Fallback Owner Search')), ['receipt_number' => 'FALLBACK-RECEIPT']);
        $component = $this->list(ListPayments::class);
        $component->searchTable('old entry name')->assertCanSeeTableRecords([$linked])->assertCanNotSeeTableRecords([$legacy, $fallback]);
        $component->searchTable('current applicant')->assertCanSeeTableRecords([$legacy])->assertCanNotSeeTableRecords([$linked, $fallback]);
        $component->searchTable('med-0012')->assertCanSeeTableRecords([$legacy])->assertCanNotSeeTableRecords([$linked, $fallback]);
        $component->searchTable('stale full name')->assertCountTableRecords(0);
        $component->searchTable('owner latin')->assertCanSeeTableRecords([$fallback])->assertCanNotSeeTableRecords([$legacy, $linked]);
        $component->searchTable('012888999')->assertCanSeeTableRecords([$legacy, $linked, $fallback]);
    }

    public function test_receipts_search_formatted_money_configured_bank_method_and_workflow_labels_without_changing_state(): void
    {
        PaymentType::query()->create(['key' => 'special_bank', 'method' => 'bank', 'abbreviation' => 'SBA', 'name_en' => 'Special Bank', 'name_kh' => 'ធនាគារពិសេស', 'is_active' => true]);
        $form = $this->form('Configured Payment Search');
        $workflow = WorkflowNotification::query()->create(['name' => 'Search labels', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_type' => 'payment', 'stage_name' => 'Payment', 'status_message' => 'Fully Settled Custom', 'payment_statuses' => ['unpaid_status_message' => 'Awaiting Fee Custom']]],
        ]]);
        $workflow->forms()->attach($form);
        $paid = $this->entry($form);
        $unpaid = $this->entry($form);
        $receipt = $this->payment($paid, ['type_payment' => 'special_bank', 'amount_usd' => 1234.50, 'amount_kh' => 4938000, 'exchange_rate' => 4000]);
        $pendingReceipt = $this->payment($unpaid, ['status_payt' => 'unpaid', 'type_payment' => 'cash', 'amount_usd' => 7]);
        $before = $receipt->fresh()->getAttributes();
        $component = $this->list(ListPayments::class);
        foreach (['sBa', 'bAnK', '1,234.50$', '4,938,000.00 ៛', '4,000.00', 'fully settled custom'] as $search) {
            $component->searchTable($search);
            $this->assertSame([$receipt->id], $component->instance()->getFilteredTableQuery()->pluck('payments.id')->all(), $search);
        }
        $component->searchTable('awaiting fee custom')->assertCanSeeTableRecords([$pendingReceipt])->assertCanNotSeeTableRecords([$receipt]);
        $component->searchTable('paid in candidate system')->assertCanSeeTableRecords([$receipt])->assertCanNotSeeTableRecords([$pendingReceipt]);
        $this->list(ListPaymentStatusLists::class)->searchTable('fully settled custom')->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$unpaid]);
        $this->list(ListPaymentStatusLists::class)->searchTable('awaiting fee custom')->assertCanSeeTableRecords([$unpaid])->assertCanNotSeeTableRecords([$paid]);
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
    }

    private function list(string $page)
    {
        $filter = $page === ListPayments::class ? 'payment_filters' : 'payment_status_list_filters';

        return Livewire::test($page)->set("tableFilters.{$filter}.from_date", null)
            ->set("tableFilters.{$filter}.to_date", null)->set("tableFilters.{$filter}.received_by", null);
    }

    private function user(string $name, string $type): User
    {
        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }

    private function form(string $name): CustomForm
    {
        return CustomForm::query()->create(['name' => $name, 'slug' => 'search-form-'.uniqid(), 'is_active' => true, 'requires_payment' => true]);
    }

    private function entry(CustomForm $form, array $data = [], ?string $candidateId = null): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'candidate_id' => $candidateId, 'review_status' => 'approved', 'data' => ['registration_status' => 'approved', ...$data]]);
    }

    private function payment(PaymentStatusList $entry, array $attributes = []): Payment
    {
        return Payment::query()->create(['custom_form_entry_id' => $entry->id, 'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id, 'received_by' => auth()->id(), 'receipt_number' => 'SEARCH-RECEIPT-'.uniqid(), 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(), ...$attributes]);
    }
}
