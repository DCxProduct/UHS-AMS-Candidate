<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas\CustomFormEntryForm;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class CustomFormEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_logic_matches_any_selected_value_for_single_or_multi_value_fields(): void
    {
        $matcher = new \ReflectionMethod(CustomFormEntryForm::class, 'visibilityValueMatches');

        $this->assertTrue($matcher->invoke(null, 'nursing', ['medicine', 'nursing']));
        $this->assertTrue($matcher->invoke(null, ['medicine', 'nursing'], ['nursing', 'dentistry']));
        $this->assertFalse($matcher->invoke(null, ['medicine', 'nursing'], ['dentistry', 'law']));
    }

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

    public function test_skip_logic_shows_field_when_any_selected_value_matches(): void
    {
        $form = CustomForm::factory()->create(['name' => 'Conditional Form']);
        $form->fields()->create([
            'name' => 'major',
            'label' => 'Major',
            'type' => 'select_dropdown',
            'options' => ['choices' => [
                'medicine' => 'Medicine',
                'nursing' => 'Nursing',
                'dentistry' => 'Dentistry',
            ]],
        ]);
        $form->fields()->create([
            'name' => 'extra_details',
            'label' => 'Extra details',
            'type' => 'text_input',
            'options' => [
                'visible_when' => [
                    'field' => 'major',
                    'operator' => 'in',
                    'value' => ['nursing', 'dentistry'],
                ],
            ],
        ]);

        Livewire::withQueryParams(['form_id' => $form->id])
            ->test(Pages\CreateCustomFormEntry::class)
            ->assertFormFieldHidden('data.extra_details')
            ->set('data.data.major', 'nursing')
            ->assertFormFieldVisible('data.extra_details')
            ->set('data.data.major', 'medicine')
            ->assertFormFieldHidden('data.extra_details');
    }
}
