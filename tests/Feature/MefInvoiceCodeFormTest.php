<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\CreateMefInvoiceCode;
use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\EditMefInvoiceCode;
use App\Models\MefInvoiceCode;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MefInvoiceCodeFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'MEF Form Admin',
            'username' => 'mef_form_admin',
            'email' => 'mef-form-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public function test_entering_a_number_saves_the_mef_prefix_and_keeps_receipt_lookup_working(): void
    {
        Livewire::test(CreateMefInvoiceCode::class)
            ->fillForm(['mef_code' => '1', 'invoice_code' => '120--1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mef_invoice_codes', ['mef_code' => 'MEF1', 'invoice_code' => '120--1']);
        $this->assertSame('120--1', MefInvoiceCode::resolveReceiptNumber('MEF1'));
    }

    public function test_numeric_input_preserves_leading_zeroes(): void
    {
        Livewire::test(CreateMefInvoiceCode::class)
            ->fillForm(['mef_code' => '000123', 'invoice_code' => '120--000123'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mef_invoice_codes', ['mef_code' => 'MEF000123', 'invoice_code' => '120--000123']);
    }

    public function test_edit_hydrates_only_digits_and_never_duplicates_the_prefix(): void
    {
        $record = MefInvoiceCode::query()->create(['mef_code' => 'MEF00123', 'invoice_code' => '120--123']);

        $component = Livewire::test(EditMefInvoiceCode::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['mef_code' => '00123'])
            ->fillForm(['invoice_code' => '120--124'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('MEF00123', $record->fresh()->mef_code);

        $component->fillForm(['mef_code' => '456'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('MEF456', $record->fresh()->mef_code);
        $this->assertSame('120--124', MefInvoiceCode::resolveReceiptNumber('MEF456'));
    }

    public function test_server_validation_rejects_non_numeric_and_empty_mef_input(): void
    {
        foreach (['ABC', '1A', '12-3', '1.5', 'MEF1', ''] as $value) {
            Livewire::test(CreateMefInvoiceCode::class)
                ->fillForm(['invoice_code' => '120--9'])
                ->set('data.mef_code', $value)
                ->call('create')
                ->assertHasFormErrors(['mef_code' => $value === '' ? 'required' : 'regex']);
        }

        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_mef_suffix_respects_the_stored_code_length_limit(): void
    {
        Livewire::test(CreateMefInvoiceCode::class)
            ->fillForm(['mef_code' => str_repeat('1', 98), 'invoice_code' => '120--98'])
            ->call('create')
            ->assertHasFormErrors(['mef_code' => 'max']);
        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_editing_the_invoice_preserves_an_unchanged_legacy_mef_identifier(): void
    {
        $record = MefInvoiceCode::query()->create(['mef_code' => 'MEF-100', 'invoice_code' => '120--100']);

        Livewire::test(EditMefInvoiceCode::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['mef_code' => '100'])
            ->fillForm(['invoice_code' => '120--101'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('MEF-100', $record->fresh()->mef_code);
        $this->assertSame('120--101', MefInvoiceCode::resolveReceiptNumber('MEF-100'));
    }
}
