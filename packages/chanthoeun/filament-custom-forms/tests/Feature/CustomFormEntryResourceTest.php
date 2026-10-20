<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class CustomFormEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_page()
    {
        Livewire::test(Pages\ListCustomFormEntries::class)
            ->assertSuccessful();
    }

    public function test_can_create_entry()
    {
        $form = CustomForm::factory()->create(['name' => 'Registration Form']);

        // Add a field to the form
        $form->fields()->create([
            'name' => 'full_name',
            'label' => 'Full Name',
            'type' => 'text_input',
            'required' => true,
        ]);

        Livewire::withQueryParams(['form_id' => $form->id])->test(Pages\CreateCustomFormEntry::class)
            ->fillForm([
                'custom_form_id' => $form->id,
                'data.full_name' => 'John Doe',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('custom_form_entries', [
            'custom_form_id' => $form->id,
        ]);

        $entry = CustomFormEntry::first();
        $this->assertEquals('John Doe', $entry->data['full_name']);
    }
}
