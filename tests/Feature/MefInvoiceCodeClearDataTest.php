<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\ListMefInvoiceCodes;
use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\MefInvoiceCodeImportService;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Once;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MefInvoiceCodeClearDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->actingAs($this->staff());
    }

    public function test_clear_without_selection_permanently_deletes_codes_but_preserves_unrelated_payments(): void
    {
        $first = $this->code('MEF1', 1);
        $second = $this->code('MEF2', 2);
        $payment = Payment::query()->create(['receipt_number' => $first->invoice_code, 'amount_kh' => 1000, 'status_payt' => 'paid']);
        $paymentBefore = $payment->fresh()->getAttributes();

        $component = Livewire::test(ListMefInvoiceCodes::class)
            ->assertActionVisible('clear_data')
            ->assertCanSeeTableRecords([$first, $second]);
        $this->assertTrue($component->instance()->getAction('clear_data')->isConfirmationRequired());
        $this->assertTrue($component->instance()->getTable()->isSelectionEnabled());
        $this->assertFalse(auth()->user()->can('Delete:MefInvoiceCode'));
        $component->callAction('clear_data')
            ->assertCanNotSeeTableRecords([$first, $second]);

        $this->assertDatabaseCount('mef_invoice_codes', 0);
        $this->assertSame(0, MefInvoiceCodeResource::getEloquentQuery()->count());
        $this->assertNull($first->fresh());
        $this->assertNull($second->fresh());
        $this->assertNull(MefInvoiceCode::resolveReceiptNumber($first->mef_code));
        $this->assertNull(MefInvoiceCode::resolveReceiptNumber($first->invoice_code));
        $this->assertSame($paymentBefore, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_selected_rows_are_cleared_across_pages_without_clearing_other_rows(): void
    {
        $codes = collect(range(1, 12))->map(fn (int $i) => $this->code('MEF'.$i, $i));
        $selected = [$codes->first()->getKey(), $codes->last()->getKey()];

        Livewire::test(ListMefInvoiceCodes::class)
            ->set('selectedTableRecords', $selected)
            ->callAction('clear_data', arguments: ['selectedRecordKeys' => $selected])
            ->assertSet('selectedTableRecords', [])
            ->assertSet('deselectedTableRecords', [])
            ->assertSet('isTrackingDeselectedTableRecords', false);

        $this->assertDatabaseCount('mef_invoice_codes', 10);
        $this->assertFalse(MefInvoiceCode::query()->whereKey($selected)->exists());
        $this->assertEqualsCanonicalizing(
            $codes->slice(1, 10)->pluck('id')->all(),
            MefInvoiceCodeResource::getEloquentQuery()->pluck('id')->all(),
        );
    }

    public function test_clear_all_respects_the_current_search(): void
    {
        $remove = $this->code('CLEAR-1', 1);
        $removeAlso = $this->code('CLEAR-2', 2);
        $keep = $this->code('KEEP-3', 3);

        Livewire::test(ListMefInvoiceCodes::class)
            ->searchTable('CLEAR')
            ->call('clearDataFromTableSelection')
            ->searchTable('')
            ->assertCanSeeTableRecords([$keep]);

        $this->assertNull($remove->fresh());
        $this->assertNull($removeAlso->fresh());
        $this->assertNull($keep->fresh()->records_cleared_at);
        $this->assertDatabaseCount('mef_invoice_codes', 1);
        $this->assertSame(1, MefInvoiceCodeResource::getEloquentQuery()->count());
    }

    public function test_select_all_can_exclude_rows_and_does_not_clear_outside_the_search(): void
    {
        $remove = $this->code('MATCH-1', 1);
        $excluded = $this->code('MATCH-2', 2);
        $outside = $this->code('OTHER-3', 3);

        Livewire::test(ListMefInvoiceCodes::class)
            ->searchTable('MATCH')
            ->call('clearDataFromTableSelection', [], true, [$excluded->getKey()]);

        $this->assertNull($remove->fresh());
        $this->assertNull($excluded->fresh()->records_cleared_at);
        $this->assertNull($outside->fresh()->records_cleared_at);
        $this->assertDatabaseCount('mef_invoice_codes', 2);
        $this->assertSame(2, MefInvoiceCodeResource::getEloquentQuery()->count());
    }

    public function test_stale_selected_rows_cannot_bypass_the_current_search(): void
    {
        $inside = $this->code('MATCH-1', 1);
        $outside = $this->code('OTHER-2', 2);

        Livewire::test(ListMefInvoiceCodes::class)
            ->searchTable('MATCH')
            ->call('clearDataFromTableSelection', [$outside->getKey()]);

        $this->assertDatabaseCount('mef_invoice_codes', 2);
        $this->assertDatabaseHas('mef_invoice_codes', ['id' => $inside->getKey()]);
        $this->assertDatabaseHas('mef_invoice_codes', ['id' => $outside->getKey()]);
        $this->assertNull($inside->fresh()->records_cleared_at);
        $this->assertNull($outside->fresh()->records_cleared_at);
    }

    public function test_clear_all_includes_previously_cleared_rows_and_keeps_the_search_boundary(): void
    {
        $visible = $this->code('MATCH-1', 1);
        $archived = $this->code('MATCH-2', 2);
        $archived->forceFill(['records_cleared_at' => now()->subMonth()])->save();
        $outside = $this->code('OTHER-3', 3);
        $outsideArchived = $this->code('OTHER-4', 4);
        $outsideArchived->forceFill(['records_cleared_at' => now()->subMonth()])->save();

        Livewire::test(ListMefInvoiceCodes::class)
            ->searchTable('MATCH')
            ->assertCanSeeTableRecords([$visible])
            ->assertCanNotSeeTableRecords([$archived])
            ->call('clearDataFromTableSelection')
            ->searchTable('')
            ->assertCanSeeTableRecords([$outside])
            ->assertCanNotSeeTableRecords([$outsideArchived]);

        $this->assertNull($visible->fresh());
        $this->assertNull($archived->fresh());
        $this->assertDatabaseCount('mef_invoice_codes', 2);
        $this->assertNotNull($outsideArchived->fresh()->records_cleared_at);
    }

    public function test_selected_rows_do_not_expand_to_previously_cleared_unselected_rows(): void
    {
        $selected = $this->code('MEF1', 1);
        $archived = $this->code('MEF2', 2);
        $archived->forceFill(['records_cleared_at' => now()->subMonth()])->save();

        Livewire::test(ListMefInvoiceCodes::class)
            ->call('clearDataFromTableSelection', [$selected->id]);

        $this->assertNull($selected->fresh());
        $this->assertNotNull($archived->fresh());
        $this->assertDatabaseCount('mef_invoice_codes', 1);
    }

    public function test_permanently_deleted_codes_can_be_imported_again_as_new_records(): void
    {
        $code = $this->code('MEF1', 1);
        Livewire::test(ListMefInvoiceCodes::class)
            ->call('clearDataFromTableSelection', [$code->getKey()]);
        $this->assertNull($code->fresh());
        $result = app(MefInvoiceCodeImportService::class)->import(UploadedFile::fake()->createWithContent(
            'codes.csv',
            "mef_code,invoice_code\nMEF1,120--1\n",
        ));

        $this->assertSame(['created' => 1, 'existing' => 0], $result);
        $this->assertDatabaseCount('mef_invoice_codes', 1);
        $this->assertSame(1, MefInvoiceCodeResource::getEloquentQuery()->count());
        $this->assertFalse(MefInvoiceCode::query()->whereKey($code->id)->exists());
        $this->assertSame('120--1', MefInvoiceCode::resolveReceiptNumber('MEF1'));
    }

    public function test_view_only_users_cannot_clear_codes_even_by_calling_the_method_directly(): void
    {
        $this->actingAs($this->staff(canClear: false));
        $code = $this->code('MEF1', 1);

        $component = Livewire::test(ListMefInvoiceCodes::class)
            ->assertActionHidden('clear_data');
        $this->assertFalse($component->instance()->getTable()->isSelectionEnabled());
        $component->call('clearDataFromTableSelection', [$code->getKey()])->assertForbidden();

        $this->assertDatabaseHas('mef_invoice_codes', ['id' => $code->getKey()]);
    }

    public function test_delete_permission_does_not_grant_the_separate_clear_data_action(): void
    {
        $this->actingAs($this->staff(canClear: false, canDelete: true));
        $code = $this->code('MEF1', 1);
        $this->assertTrue(auth()->user()->can('Delete:MefInvoiceCode'));

        $component = Livewire::test(ListMefInvoiceCodes::class)->assertActionHidden('clear_data');
        $this->assertFalse($component->instance()->getTable()->isSelectionEnabled());
        $component->call('clearDataFromTableSelection', [$code->getKey()])->assertForbidden();

        $this->assertDatabaseHas('mef_invoice_codes', ['id' => $code->getKey()]);
    }

    public static function permissionLocales(): array
    {
        return [
            ['en', 'Import Excel', 'Clear Data'],
            ['km', 'នាំចូល Excel', 'សម្អាតទិន្នន័យ'],
        ];
    }

    #[DataProvider('permissionLocales')]
    public function test_role_permission_options_include_separate_translated_import_and_clear_actions(string $locale, string $importLabel, string $clearLabel): void
    {
        app()->setLocale($locale);
        Once::flush();
        $permissions = FilamentShield::getResourcePermissionsWithLabels(MefInvoiceCodeResource::class);

        $this->assertSame($importLabel, $permissions['ImportExcel:MefInvoiceCode']);
        $this->assertSame($clearLabel, $permissions['ClearData:MefInvoiceCode']);
        $this->assertArrayHasKey('Delete:MefInvoiceCode', $permissions);
        $this->assertContains('ClearData:MefInvoiceCode', FilamentActionPermissions::all());
        $this->assertContains('clearData', FilamentActionPermissions::resourcePolicyMethods()[MefInvoiceCodeResource::class]);
    }

    private function code(string $code, int $number): MefInvoiceCode
    {
        return MefInvoiceCode::query()->create(['mef_code' => $code, 'invoice_code' => '120--'.$number]);
    }

    private function staff(bool $canClear = true, bool $canDelete = false): User
    {
        $name = $canClear ? 'cashier_officer' : ($canDelete ? 'delete_only' : 'clear_viewer');
        $role = Role::findOrCreate($name, 'web');
        $role->givePermissionTo(Permission::findOrCreate('ViewAny:MefInvoiceCode', 'web'));
        if ($canClear) {
            $role->givePermissionTo(Permission::findOrCreate('ClearData:MefInvoiceCode', 'web'));
        }
        if ($canDelete) {
            $role->givePermissionTo(Permission::findOrCreate('Delete:MefInvoiceCode', 'web'));
        }
        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => $name,
            'username' => $name,
            'email' => $name.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
