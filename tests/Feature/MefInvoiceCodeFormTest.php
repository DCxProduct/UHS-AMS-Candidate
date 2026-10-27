<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\CreateMefInvoiceCode;
use App\Filament\Admin\Resources\MefInvoiceCodes\Pages\EditMefInvoiceCode;
use App\Models\MefInvoiceCode;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
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
            ->assertFormFieldExists('mef_code', fn (TextInput $field): bool => blank($field->getPrefixLabel()))
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

    public function test_edit_keeps_the_full_code_editable_and_never_duplicates_the_prefix(): void
    {
        $record = MefInvoiceCode::query()->create(['mef_code' => 'MEF00123', 'invoice_code' => '120--123']);

        $component = Livewire::test(EditMefInvoiceCode::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['mef_code' => 'MEF00123'])
            ->fillForm(['invoice_code' => '120--124'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('MEF00123', $record->fresh()->mef_code);

        $component->fillForm(['mef_code' => 'CUSTOM-456'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('CUSTOM-456', $record->fresh()->mef_code);

        $component->fillForm(['mef_code' => '456'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('MEF456', $record->fresh()->mef_code);
        $this->assertSame('120--124', MefInvoiceCode::resolveReceiptNumber('MEF456'));
    }

    public function test_full_mef_text_is_preserved_when_saved(): void
    {
        foreach (['MEF1', 'MEF-123', 'CUSTOM456', '1A', 'customABC-4'] as $index => $value) {
            Livewire::test(CreateMefInvoiceCode::class)
                ->fillForm(['mef_code' => $value, 'invoice_code' => '120--'.$index])
                ->call('create')
                ->assertHasNoFormErrors();
            $this->assertDatabaseHas('mef_invoice_codes', ['mef_code' => $value, 'invoice_code' => '120--'.$index]);
        }
    }

    public function test_empty_mef_input_is_still_required(): void
    {
        foreach (['', '   '] as $value) {
            Livewire::test(CreateMefInvoiceCode::class)
                ->fillForm(['mef_code' => $value, 'invoice_code' => '120--9'])
                ->call('create')
                ->assertHasFormErrors(['mef_code' => 'required']);
        }
        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_full_mef_code_respects_the_stored_length_limit(): void
    {
        foreach ([str_repeat('A', 101), str_repeat('1', 98)] as $value) {
            Livewire::test(CreateMefInvoiceCode::class)
                ->fillForm(['mef_code' => $value, 'invoice_code' => '120--98'])
                ->call('create')
                ->assertHasFormErrors(['mef_code' => 'max']);
        }
        $this->assertDatabaseCount('mef_invoice_codes', 0);
    }

    public function test_editing_the_invoice_preserves_an_unchanged_legacy_mef_identifier(): void
    {
        $record = MefInvoiceCode::query()->create(['mef_code' => 'MEF-100', 'invoice_code' => '120--100']);

        Livewire::test(EditMefInvoiceCode::class, ['record' => $record->getRouteKey()])
            ->assertFormSet(['mef_code' => 'MEF-100'])
            ->fillForm(['invoice_code' => '120--101'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('MEF-100', $record->fresh()->mef_code);
        $this->assertSame('120--101', MefInvoiceCode::resolveReceiptNumber('MEF-100'));
    }
}
