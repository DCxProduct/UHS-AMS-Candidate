<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Support\CustomFormEntryFiles;
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

    public function test_candidate_can_download_each_document_upload_without_images(): void
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
            'name' => 'documents',
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

        Storage::disk('private')->put('custom-form-uploads/application.docx', 'word-data');
        Storage::disk('private')->put('custom-form-uploads/notes.pdf', 'pdf-data');
        Storage::disk('private')->put('custom-form-uploads/photo.png', 'image-data');

        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $candidate->getKey(),
            'data' => [
                'documents' => [
                    'custom-form-uploads/application.docx',
                    'custom-form-uploads/notes.pdf',
                ],
                'photo' => 'custom-form-uploads/photo.png',
            ],
        ]);

        app()->setLocale('en');

        $this->assertSame(
            'Word document (.docx), PDF document (.pdf)',
            app(CustomFormEntryFiles::class)->displayLabel($entry->data['documents']),
        );

        $firstResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
            ]));

        $firstResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=application.docx');
        $this->assertSame('word-data', $firstResponse->streamedContent());

        $inlineResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 0,
                'inline' => 1,
            ]));

        $inlineResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="application.docx"');
        $this->assertSame('word-data', $inlineResponse->streamedContent());

        $secondResponse = $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 1,
            ]));

        $secondResponse
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=notes.pdf');
        $this->assertSame('pdf-data', $secondResponse->streamedContent());

        $this->actingAs($candidate)
            ->get(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => 2,
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
