<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\CreateCustomForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\EditCustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

class CustomFormNavigationIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_form_can_be_created_with_a_selected_sidebar_icon(): void
    {
        Livewire::test(CreateCustomForm::class)
            ->fillForm([
                'name_en' => 'Scholarship Application',
                'name_km' => 'ពាក្យស្នើសុំអាហារូបករណ៍',
                'slug' => 'scholarship-application',
                'navigation_icon' => 'heroicon-o-academic-cap',
            ])
            ->call('create')->assertHasNoFormErrors();

        $form = CustomForm::where('slug', 'scholarship-application')->sole();
        $this->assertSame('heroicon-o-academic-cap', $form->navigation_icon);
        $this->assertSame('heroicon-o-academic-cap', $this->navigationItem($form)->getIcon());
    }

    public function test_editing_an_icon_keeps_other_form_settings_and_sidebar_items_unchanged(): void
    {
        $form = CustomForm::factory()->create([
            'name' => json_encode(['en' => 'Application', 'km' => 'ពាក្យស្នើសុំ', 'kh' => 'ពាក្យស្នើសុំ'], JSON_UNESCAPED_UNICODE),
            'navigation_icon' => 'heroicon-o-star',
            'requires_payment' => false,
            'allowed_roles' => ['national_entrance_exam_application_bachelor'],
            'schema' => [['type' => 'text_input', 'data' => ['name' => 'full_name']]],
            'submission_next_steps' => ['is_active' => true, 'heading_en' => 'Next steps'],
        ]);
        $other = CustomForm::factory()->create();
        $before = $form->getAttributes();
        $otherBefore = $other->fresh()->getAttributes();
        $item = $this->navigationItem($form);
        $url = $item->getUrl();
        $sort = $item->getSort();

        Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
            ->assertSchemaStateSet(['navigation_icon' => 'heroicon-o-star'])
            ->fillForm(['navigation_icon' => 'heroicon-o-heart'])
            ->call('save')->assertHasNoFormErrors();

        $form->refresh();
        foreach ($before as $column => $value) {
            if (! in_array($column, ['navigation_icon', 'updated_at'], true)) {
                $this->assertEquals($value, $form->getAttributes()[$column], $column.' changed');
            }
        }
        $this->assertSame('heroicon-o-heart', $this->navigationItem($form)->getIcon());
        $this->assertSame($url, $this->navigationItem($form)->getUrl());
        $this->assertSame($sort, $this->navigationItem($form)->getSort());
        $this->assertSame($otherBefore, $other->fresh()->getAttributes());
    }

    public function test_existing_forms_show_their_current_default_icon_and_invalid_icons_cannot_be_saved(): void
    {
        foreach (['ordinary-application' => 'heroicon-o-clipboard-document-list', 'profile' => 'heroicon-o-user', 'national-exam' => 'heroicon-o-academic-cap'] as $slug => $icon) {
            $form = CustomForm::factory()->create(['slug' => $slug]);
            Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
                ->assertSchemaStateSet(['navigation_icon' => $icon]);
            $this->assertNull($form->fresh()->navigation_icon);
        }

        Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
            ->fillForm(['navigation_icon' => 'missing-icon'])
            ->call('save')->assertHasFormErrors(['navigation_icon']);
        $this->assertNull($form->fresh()->navigation_icon);
    }

    private function navigationItem(CustomForm $form): NavigationItem
    {
        return collect(CustomFormEntryResource::getNavigationItems())
            ->firstOrFail(fn ($item): bool => $item->getKey() === 'custom-form-entry-'.$form->id);
    }
}
