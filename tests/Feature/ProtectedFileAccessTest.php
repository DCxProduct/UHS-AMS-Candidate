<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Support\CustomFormEntryFiles;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtectedFileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_access_own_payment_slip(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('payment-slips/own.png', 'image-data');

        $candidate = $this->createCandidate('candidate-one');
        $payment = $this->createPayment($candidate, 'OWN-001');

        $this->actingAs($candidate)
            ->get(route('protected.payment-slip', $payment))
            ->assertOk();
    }

    public function test_candidate_cannot_access_another_candidates_payment_slip(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('payment-slips/other.png', 'image-data');

        $owner = $this->createCandidate('candidate-owner');
        $otherCandidate = $this->createCandidate('candidate-other');
        $payment = $this->createPayment($owner, 'OTHER-001', 'payment-slips/other.png');

        $this->actingAs($otherCandidate)
            ->get(route('protected.payment-slip', $payment))
            ->assertForbidden();
    }

    public function test_anonymous_payment_slip_access_redirects_to_login(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('payment-slips/own.png', 'image-data');

        $candidate = $this->createCandidate('candidate-anonymous-owner');
        $payment = $this->createPayment($candidate, 'ANON-001');

        $this->get(route('protected.payment-slip', $payment))
            ->assertRedirect('/login');
    }

    public function test_candidate_can_preview_and_download_document_and_image_uploads(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        config([
            'filament-custom-forms.uploads.disk' => 'private',
            'filament-custom-forms.uploads.legacy_disk' => 'public',
        ]);

        $candidate = $this->createCandidate('candidate-file-owner');
        $form = CustomForm::query()->create([
            'name' => 'Testing Form',
            'slug' => 'testing-form',
            'schema' => [],
            'is_active' => true,
        ]);

        CustomFormField::query()->create([
            'custom_form_id' => $form->getKey(),
            'name' => 'file',
            'type' => 'file_upload',
            'required' => false,
            'options' => [],
            'sort' => 1,
        ]);
        CustomFormField::query()->create([
            'custom_form_id' => $form->getKey(),
            'name' => 'photo',
            'type' => 'image_upload',
            'required' => false,
            'options' => [],
            'sort' => 2,
        ]);
        CustomFormField::query()->create([
            'custom_form_id' => $form->getKey(),
            'name' => 'File2',
            'type' => 'file_upload',
            'required' => false,
            'options' => [],
            'sort' => 3,
        ]);

        $columnsMethod = new \ReflectionMethod(CustomFormEntriesTable::class, 'getColumns');
        $this->actingAs($candidate);
        $columns = $columnsMethod->invoke(null, (string) $form->getKey());
        $uploadedFilesColumn = collect($columns)->first(
            fn ($column): bool => $column->getName() === 'uploaded_files'
        );
        $this->assertNotNull($uploadedFilesColumn);

        Storage::disk('private')->put('custom-form-uploads/application.docx', 'word-data');
        Storage::disk('private')->put('custom-form-uploads/notes.pdf', 'pdf-data');
        Storage::disk('private')->put('custom-form-uploads/photo.png', 'image-data');
        Storage::disk('private')->put('custom-form-uploads/other.docx', 'other-word-data');

        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $candidate->getKey(),
            'data' => [
                'file' => [
                    'custom-form-uploads/application.docx',
                    'custom-form-uploads/notes.pdf',
                ],
                'photo' => 'custom-form-uploads/photo.png',
                'File2' => 'custom-form-uploads/other.docx',
            ],
        ]);

        app()->setLocale('en');

        $this->assertSame(
            'Word document (.docx), PDF document (.pdf)',
            app(CustomFormEntryFiles::class)->displayLabel($entry->data['file']),
        );

        $firstResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
            ]));

        $firstResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=file.docx');
        $this->assertSame('word-data', $firstResponse->streamedContent());

        $inlineResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
                'inline' => 1,
            ]));

        $inlineResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=file.docx');
        $this->assertSame('word-data', $inlineResponse->streamedContent());

        $secondResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 1,
            ]));

        $secondResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=file.pdf');
        $this->assertSame('pdf-data', $secondResponse->streamedContent());

        foreach ([false, true] as $inline) {
            $imageResponse = $this->actingAs($candidate)
                ->get(route('protected.custom-form-entry-document', [
                    'entry' => $entry,
                    'fileIndex' => 2,
                    'inline' => $inline ? 1 : 0,
                ]));
            $imageResponse
                ->assertOk()
                ->assertHeader('Content-Disposition', ($inline ? 'inline' : 'attachment').'; filename=photo.png');
            $this->assertSame('image-data', $imageResponse->streamedContent());
        }

        $otherDocumentResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 3,
            ]));
        $otherDocumentResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=File2.docx');
        $this->assertSame('other-word-data', $otherDocumentResponse->streamedContent());

        $zipResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-files-zip', ['entry' => $entry]));
        $zipResponse->assertOk();
        $this->assertStringContainsString(
            $candidate->getFilamentName().'.zip',
            (string) $zipResponse->headers->get('Content-Disposition'),
        );

        $archive = new \ZipArchive;
        $archivePath = $zipResponse->getFile()->getPathname();
        $this->assertTrue($archive->open($archivePath) === true);
        $this->assertSame('word-data', $archive->getFromName('file.docx'));
        $this->assertSame('pdf-data', $archive->getFromName('file.pdf'));
        $this->assertSame('other-word-data', $archive->getFromName('File2.docx'));
        $this->assertSame('image-data', $archive->getFromName('photo.png'));
        $archive->close();
        @unlink($archivePath);

        $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 4,
            ]))
            ->assertNotFound();
    }

    public function test_candidate_cannot_download_another_candidates_documents(): void
    {
        Storage::fake('private');

        $owner = $this->createCandidate('candidate-document-owner');
        $otherCandidate = $this->createCandidate('candidate-document-other');
        $form = CustomForm::query()->create([
            'name' => 'Protected Form',
            'slug' => 'protected-form',
            'schema' => [],
            'is_active' => true,
        ]);

        CustomFormField::query()->create([
            'custom_form_id' => $form->getKey(),
            'name' => 'document',
            'type' => 'file_upload',
            'required' => false,
            'options' => [],
            'sort' => 1,
        ]);

        Storage::disk('private')->put('custom-form-uploads/protected.docx', 'word-data');

        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $owner->getKey(),
            'data' => ['document' => 'custom-form-uploads/protected.docx'],
        ]);

        $this->actingAs($otherCandidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
            ]))
            ->assertForbidden();
    }

    public function test_download_and_inline_filenames_use_the_localized_field_label_and_preserve_file_contents(): void
    {
        Storage::fake('private');
        $candidate = $this->createCandidate('candidate-named-document');
        $form = CustomForm::query()->create([
            'name' => 'Named Documents',
            'slug' => 'named-documents',
            'schema' => [],
            'is_active' => true,
        ]);
        $form->fields()->create([
            'name' => 'supporting_document',
            'label' => json_encode(['en' => 'Supporting document', 'km' => 'លិខិតបញ្ជាក់']),
            'type' => 'file_upload',
        ]);
        $path = 'custom-form-uploads/01K9RANDOMUPLOAD.docx';
        $contents = "original-document\x00\x01\xff";
        Storage::disk('private')->put($path, $contents);
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $candidate->getKey(),
            'data' => ['supporting_document' => $path],
        ]);
        $this->actingAs($candidate)->withSession(['locale' => 'km']);
        app()->setLocale('km');
        $this->assertSame('លិខិតបញ្ជាក់.docx', app(CustomFormEntryFiles::class)->previewFiles($entry)[0]['download_name']);

        foreach ([false, true] as $inline) {
            $response = $this->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
                'inline' => $inline ? 1 : 0,
            ]))->assertOk();
            $disposition = (string) $response->headers->get('Content-Disposition');

            $this->assertStringStartsWith($inline ? 'inline;' : 'attachment;', $disposition);
            $this->assertStringContainsString("filename*=utf-8''".rawurlencode('លិខិតបញ្ជាក់.docx'), $disposition);
            $this->assertStringNotContainsString('01K9RANDOMUPLOAD', $disposition);
            $this->assertSame($contents, $response->streamedContent());
        }

        app()->setLocale('en');
        $response = $this->withSession(['locale' => 'en'])->get(route('protected.custom-form-entry-document', [
            'entry' => $entry,
            'fileIndex' => 0,
        ]))->assertOk();
        $this->assertStringContainsString('filename="Supporting document.docx"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame($contents, $response->streamedContent());
    }

    public function test_download_filename_sanitizes_field_labels_and_keeps_only_one_original_extension(): void
    {
        Storage::fake('private');
        $candidate = $this->createCandidate('candidate-safe-filename');
        $form = CustomForm::query()->create([
            'name' => 'Safe Filenames',
            'slug' => 'safe-filenames',
            'schema' => [],
            'is_active' => true,
        ]);
        $form->fields()->create([
            'name' => 'document',
            'label' => "../លិខិត\\បញ្ជាក់:<ឯកសារ>|*?\"\r\n.docx",
            'type' => 'file_upload',
        ]);
        $path = 'custom-form-uploads/01K9SAFERANDOM.docx';
        Storage::disk('private')->put($path, 'unchanged-document');
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $candidate->getKey(),
            'data' => ['document' => $path],
        ]);
        app()->setLocale('km');

        $response = $this->actingAs($candidate)->get(route('protected.custom-form-entry-document', [
            'entry' => $entry,
            'fileIndex' => 0,
        ]))->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertSame(1, preg_match("/filename\\*=utf-8''([^;]+)/", $disposition, $matches));
        $filename = rawurldecode($matches[1]);

        $this->assertStringContainsString('លិខិត', $filename);
        $this->assertDoesNotMatchRegularExpression('/[\\x00-\\x1F\\x7F<>:"\\/\\\\|?*]/u', $filename);
        $this->assertStringEndsWith('.docx', $filename);
        $this->assertSame(1, substr_count(strtolower($filename), '.docx'));
        $this->assertSame('unchanged-document', $response->streamedContent());

        $this->assertSame('document.pdf', app(CustomFormEntryFiles::class)->downloadFilename([
            'field_name' => 'document',
            'field_label' => ' ',
            'path' => 'custom-form-uploads/01K9RANDOM.pdf',
        ]));
    }

    private function createCandidate(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'student',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function createPayment(User $candidate, string $receipt, string $path = 'payment-slips/own.png'): Payment
    {
        return Payment::query()->create([
            'users_id' => $candidate->getKey(),
            'receipt_number' => $receipt,
            'payment_slip_path' => $path,
            'amount_kh' => '1000.00',
            'status_payt' => 'paid',
        ]);
    }
}
