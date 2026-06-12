<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_custom_form_creates_document_template()
    {
        if (!class_exists(DocumentTemplate::class)) {
            $this->markTestSkipped('Document builder not installed');
        }

        $form = CustomForm::create([
            'name' => 'Test Registration Form',
            'slug' => 'test-registration-form',
            'schema' => [
                ['name' => 'first_name', 'label' => 'First Name'],
                ['name' => 'last_name', 'label' => 'Last Name']
            ],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('document_templates', [
            'type' => 'custom_form_' . $form->id,
            'name' => 'Test Registration Form Template',
        ]);
    }
}
