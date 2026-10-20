<?php

namespace Chanthoeun\FilamentCustomForms\Tests\Feature;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Tests\TestCase;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_custom_form_creates_document_template()
    {
        if (! class_exists(DocumentTemplate::class)) {
            $this->markTestSkipped('Document builder not installed');
        }

        $form = CustomForm::create([
            'name' => 'Test Registration Form',
            'slug' => 'test-registration-form',
            'schema' => [
                ['name' => 'first_name', 'label' => 'First Name'],
                ['name' => 'last_name', 'label' => 'Last Name'],
            ],
            'is_active' => true,
        ]);

        $template = DocumentTemplate::where('type', 'custom_form_'.$form->id)->sole();
        $this->assertSame('Test Registration Form Template', json_decode($template->name, true)['en']);
    }
}
