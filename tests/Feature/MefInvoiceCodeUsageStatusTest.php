<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\ListMefInvoiceCodes;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Rules\ValidMefInvoiceReceipt;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MefInvoiceCodeUsageStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $this->actor('usage-admin', Role::findOrCreate('admin', 'web'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_derived_status_labels_filters_search_and_sort_work_without_changing_codes_or_payments(string $locale): void
    {
        app()->setLocale($locale);
        $used = $this->code('MEF-101', '120--101');
        $unused = $this->code('MEF-202', '120--202');
        $alias = $this->code('MEF-103', '120--101');
        $other = $this->code('MEF-404', '120--404');
        $this->payment($used->invoice_code);
        $codesBefore = DB::table('mef_invoice_codes')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $paymentsBefore = DB::table('payments')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $component = Livewire::test(ListMefInvoiceCodes::class)->assertCanSeeTableRecords([$used, $unused, $alias, $other])
            ->assertTableColumnStateSet('usage_status', 'used', $used)
            ->assertTableColumnStateSet('usage_status', 'used', $alias)
            ->assertTableColumnStateSet('usage_status', 'unused', $unused);
        $column = $component->instance()->getTable()->getColumn('usage_status');
        $usedLabel = $column->record(MefInvoiceCodeResource::getEloquentQuery()->findOrFail($used->id))->formatState('used');
        $unusedLabel = $column->record(MefInvoiceCodeResource::getEloquentQuery()->findOrFail($unused->id))->formatState('unused');
        $this->assertNotSame($usedLabel, $unusedLabel);
        $this->assertStringNotContainsString('mef_invoice_codes.', $usedLabel);
        $this->assertStringNotContainsString('mef_invoice_codes.', $unusedLabel);
        if ($locale === 'en') {
            $this->assertSame('Used', $usedLabel);
            $this->assertSame('Unused', $unusedLabel);
        } else {
            $this->assertMatchesRegularExpression('/[\x{1780}-\x{17FF}]/u', $usedLabel);
            $this->assertMatchesRegularExpression('/[\x{1780}-\x{17FF}]/u', $unusedLabel);
        }
        $component->filterTable('usage_status', 'used')->assertCanSeeTableRecords([$used, $alias])->assertCanNotSeeTableRecords([$unused, $other]);
        $component->filterTable('usage_status', 'unused')->assertCanSeeTableRecords([$unused, $other])->assertCanNotSeeTableRecords([$used, $alias]);
        $component->removeTableFilter('usage_status');
        foreach (['uSeD', $usedLabel] as $search) {
            $component->searchTable($search)->assertCanSeeTableRecords([$used, $alias])->assertCanNotSeeTableRecords([$unused, $other]);
        }
        foreach (['UnUsEd', $unusedLabel] as $search) {
            $component->searchTable($search)->assertCanSeeTableRecords([$unused, $other])->assertCanNotSeeTableRecords([$used, $alias]);
        }
        $component->filterTable('usage_status', 'used')->assertCountTableRecords(0);
        $component->removeTableFilter('usage_status')->searchTable('mef-202')->assertCanSeeTableRecords([$unused])->assertCanNotSeeTableRecords([$used, $alias, $other]);
        $component->searchTable('')->sortTable('usage_status', 'asc');
        $this->assertSame([false, false, true, true], $component->instance()->getTableRecords()->pluck('is_used')->map(fn ($used): bool => (bool) $used)->all());
        $component->sortTable('usage_status', 'desc');
        $this->assertSame([true, true, false, false], $component->instance()->getTableRecords()->pluck('is_used')->map(fn ($used): bool => (bool) $used)->all());
        $this->assertSame($codesBefore, DB::table('mef_invoice_codes')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($paymentsBefore, DB::table('payments')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_every_existing_receipt_consumes_its_canonical_invoice_including_cleared_and_nonpaid_receipts(): void
    {
        $records = [];
        foreach (['paid', 'unpaid', 'return'] as $index => $status) {
            $record = $this->code('MEF-STATUS-'.$index, '120--'.(500 + $index));
            $this->payment($record->invoice_code, ['status_payt' => $status, 'records_cleared_at' => $status === 'return' ? now() : null]);
            $records[] = $record;
            $validator = Validator::make(['receipt_number' => $record->mef_code], ['receipt_number' => [new ValidMefInvoiceReceipt]]);
            $this->assertFalse($validator->passes());
            $this->assertSame(__('payments.validation.receipt_number_unique'), $validator->errors()->first('receipt_number'));
        }
        $unused = $this->code('MEF-STATUS-NEW', '120--599');
        $alias = $this->code('MEF-STATUS-ALIAS', $records[2]->invoice_code);
        $component = Livewire::test(ListMefInvoiceCodes::class);
        foreach ([...$records, $alias] as $record) {
            $component->assertTableColumnStateSet('usage_status', 'used', $record);
        }
        $component->assertTableColumnStateSet('usage_status', 'unused', $unused);
        $this->assertTrue(Validator::make(['receipt_number' => $unused->mef_code], ['receipt_number' => [new ValidMefInvoiceReceipt]])->passes());
        $this->assertSame($unused->invoice_code, MefInvoiceCode::resolveReceiptNumber($unused->mef_code));
        $this->payment(MefInvoiceCode::resolveReceiptNumber($unused->mef_code));
        $component->call('$refresh')->assertTableColumnStateSet('usage_status', 'used', $unused);
        $this->assertDatabaseCount('mef_invoice_codes', 5);
        $this->assertDatabaseCount('payments', 4);
    }

    public function test_status_uses_one_exists_projection_for_all_rows_and_does_not_add_permissions_to_a_viewer_role(): void
    {
        $reader = Role::findOrCreate('mef-usage-read-only', 'web');
        $reader->givePermissionTo(Permission::findOrCreate('ViewAny:MefInvoiceCode', 'web'));
        $viewer = $this->actor('mef-status-reader', $reader);
        foreach (range(1, 6) as $index) {
            $code = $this->code('MEF-READ-'.$index, '120--'.(700 + $index));
            if ($index % 2 === 0) {
                $this->payment($code->invoice_code);
            }
        }
        $query = MefInvoiceCodeResource::getEloquentQuery()->orderBy('id');
        DB::enableQueryLog();
        DB::flushQueryLog();
        $records = $query->get();
        $states = $records->map(fn (MefInvoiceCode $code): bool => (bool) $code->is_used)->all();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([false, true, false, true, false, true], $states);
        $this->assertCount(1, $queries);
        Livewire::test(ListMefInvoiceCodes::class)->assertSuccessful()->assertCanSeeTableRecords($records)
            ->assertActionHidden('import_excel')->assertActionHidden('clear_data');
        foreach (['Create', 'Update', 'Delete', 'ImportExcel', 'ClearData'] as $action) {
            $this->assertFalse($viewer->can($action.':MefInvoiceCode'));
        }
        $this->assertSame(['ViewAny:MefInvoiceCode'], $reader->fresh()->permissions->pluck('name')->all());
    }

    private function actor(string $username, Role $role): User
    {
        $actor = User::query()->create([
            'registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $actor->assignRole($role);
        $this->actingAs($actor);

        return $actor;
    }

    private function code(string $mef, string $invoice): MefInvoiceCode
    {
        return MefInvoiceCode::query()->create(['mef_code' => $mef, 'invoice_code' => $invoice]);
    }

    private function payment(string $receipt, array $attributes = []): Payment
    {
        return Payment::query()->create(['receipt_number' => $receipt, 'amount_usd' => 5, 'status_payt' => 'paid', ...$attributes]);
    }
}
