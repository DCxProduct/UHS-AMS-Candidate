<?php

namespace Tests\Feature;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WordDocumentPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('private');
        Storage::fake('public');
        config([
            'filament-custom-forms.uploads.disk' => 'private',
            'filament-custom-forms.uploads.legacy_disk' => 'public',
        ]);
    }

    public function test_owner_can_open_word_preview_and_original_document_still_downloads_unchanged(): void
    {
        $owner = $this->candidate('word-preview-owner');
        $contents = "original-word-document\x00\x01\xff";
        $entry = $this->entry($owner, contents: $contents);
        $this->actingAs($owner);
        $documentUrl = route('protected.custom-form-entry-document', ['entry' => $entry, 'fileIndex' => 0, 'inline' => 1]);
        $downloadUrl = route('protected.custom-form-entry-document', ['entry' => $entry, 'fileIndex' => 0]);

        $this->get($this->previewUrl($entry))
            ->assertOk()
            ->assertViewIs('protected-files.word-preview')
            ->assertViewHas('filename', 'Supporting letter.docx')
            ->assertViewHas('documentUrl', $documentUrl)
            ->assertViewHas('downloadUrl', $downloadUrl);

        $inlineResponse = $this->get($documentUrl)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="Supporting letter.docx"');
        $this->assertSame($contents, $inlineResponse->streamedContent());

        $downloadResponse = $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="Supporting letter.docx"');
        $this->assertSame($contents, $downloadResponse->streamedContent());
    }

    public function test_candidate_cannot_preview_another_candidates_word_document(): void
    {
        $entry = $this->entry($this->candidate('word-preview-owner'));

        $this->actingAs($this->candidate('word-preview-other'))
            ->get($this->previewUrl($entry))
            ->assertForbidden();
    }

    public function test_guest_word_preview_redirects_to_login(): void
    {
        $entry = $this->entry($this->candidate('word-preview-owner'));

        $this->get($this->previewUrl($entry))
            ->assertRedirect('/login');
    }

    #[DataProvider('unsupportedExtensions')]
    public function test_word_preview_rejects_unsupported_file_types(string $extension): void
    {
        $owner = $this->candidate('word-preview-owner');
        $entry = $this->entry($owner, $extension);

        $this->actingAs($owner)
            ->get($this->previewUrl($entry))
            ->assertNotFound();
    }

    public static function unsupportedExtensions(): array
    {
        return [
            'PDF' => ['pdf'],
            'legacy Word' => ['doc'],
            'HTML' => ['html'],
        ];
    }

    public function test_word_preview_rejects_invalid_file_index(): void
    {
        $owner = $this->candidate('word-preview-owner');
        $entry = $this->entry($owner);

        $this->actingAs($owner)
            ->get($this->previewUrl($entry, 1))
            ->assertNotFound();
    }

    public function test_word_preview_rejects_missing_upload(): void
    {
        $owner = $this->candidate('word-preview-owner');
        $entry = $this->entry($owner);
        Storage::disk('private')->delete('custom-form-uploads/01K9WORDPREVIEW.docx');

        $this->actingAs($owner)
            ->get($this->previewUrl($entry))
            ->assertNotFound();
    }

    private function previewUrl(CustomFormEntry $entry, int $index = 0): string
    {
        return route('protected.custom-form-entry-document-preview', ['entry' => $entry, 'fileIndex' => $index]);
    }

    private function candidate(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'student',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
            'is_active' => true,
        ]);
    }

    private function entry(User $owner, string $extension = 'docx', string $contents = 'original-word-document'): CustomFormEntry
    {
        $form = CustomForm::query()->create([
            'name' => 'Word preview form',
            'slug' => 'word-preview-form',
            'schema' => [],
            'is_active' => true,
        ]);
        $form->fields()->create(['name' => 'letter', 'label' => 'Supporting letter', 'type' => 'file_upload']);
        $path = 'custom-form-uploads/01K9WORDPREVIEW.'.$extension;
        Storage::disk('private')->put($path, $contents);

        return CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $owner->getKey(),
            'data' => ['letter' => $path],
        ]);
    }
}
