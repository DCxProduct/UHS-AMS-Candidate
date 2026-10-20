<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class CustomFormResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_page()
    {
        Livewire::test(Pages\ListCustomForms::class)
            ->assertSuccessful();
    }

    public function test_can_list_forms()
    {
        $forms = CustomForm::factory()->count(10)->create();

        Livewire::test(Pages\ListCustomForms::class)
            ->assertCanSeeTableRecords($forms);
    }

    public function test_can_create_forms()
    {
        $newData = CustomForm::factory()->make();

        Livewire::test(Pages\CreateCustomForm::class)
            ->fillForm([
                'name_en' => $newData->name,
                'name_km' => $newData->name,
                'slug' => $newData->slug,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stored = CustomForm::where('slug', $newData->slug)->sole();
        $this->assertSame($newData->name, json_decode($stored->name, true)['en']);
    }

    public function test_can_edit_forms()
    {
        $form = CustomForm::factory()->create();
        $newName = 'Updated Name';

        Livewire::test(Pages\EditCustomForm::class, [
            'record' => $form->getRouteKey(),
        ])
            ->fillForm([
                'name_en' => $newName,
                'name_km' => $newName,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals($newName, json_decode($form->refresh()->name, true)['en']);
    }

    public function test_can_delete_forms()
    {
        $form = CustomForm::factory()->create();

        Livewire::test(Pages\EditCustomForm::class, [
            'record' => $form->getRouteKey(),
        ])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($form);
    }
}
