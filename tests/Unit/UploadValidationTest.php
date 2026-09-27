<?php

namespace Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    public function test_permitted_document_type_is_accepted(): void
    {
        $validator = Validator::make(
            ['document' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
            ['document' => $this->documentRules()],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_executable_document_type_is_rejected(): void
    {
        $validator = Validator::make(
            ['document' => UploadedFile::fake()->create('script.php', 100, 'application/x-php')],
            ['document' => $this->documentRules()],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_oversized_document_is_rejected(): void
    {
        $validator = Validator::make(
            ['document' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')],
            ['document' => $this->documentRules()],
        );

        $this->assertTrue($validator->fails());
    }

    private function documentRules(): array
    {
        return [
            'file',
            'mimetypes:'.implode(',', config('filament-custom-forms.uploads.accepted_mime_types')),
            'max:'.config('filament-custom-forms.uploads.max_size_kb'),
        ];
    }
}
