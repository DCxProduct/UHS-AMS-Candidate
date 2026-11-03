<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\CustomFormEntryFiles;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CustomFormUploadedFilesPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');
        config([
            'filament-custom-forms.uploads.disk' => 'private',
            'filament-custom-forms.uploads.legacy_disk' => 'public',
        ]);
        app()->setLocale('en');
    }

    public function test_preview_metadata_only_embeds_supported_files_and_uses_protected_links(): void
    {
        $form = $this->form();
        $form->fields()->create([
            'name' => 'documents',
            'label' => json_encode(['en' => 'Supporting documents', 'km' => 'ឯកសារភ្ជាប់']),
            'type' => 'file_upload',
        ]);

        $paths = [
            'custom-form-uploads/application.PDF',
            'custom-form-uploads/photo.png',
            'custom-form-uploads/results.xlsx',
            'custom-form-uploads/page.html',
            'custom-form-uploads/image.svg',
        ];
        foreach ($paths as $path) {
            Storage::disk('private')->put($path, 'uploaded-file');
        }

        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'data' => ['documents' => $paths],
        ]);

        $files = app(CustomFormEntryFiles::class)->previewFiles($entry);

        $this->assertCount(5, $files);
        $this->assertSame(['pdf', 'image', null, null, null], array_column($files, 'preview_type'));

        foreach ($files as $index => $file) {
            $this->assertSame('Supporting documents', $file['label']);
            $this->assertSame('Supporting documents.'.strtolower(pathinfo($paths[$index], PATHINFO_EXTENSION)), $file['name']);
            $this->assertSame($file['download_name'], $file['name']);
            $this->assertSame(route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => $index,
            ]), $file['download_url']);
            $this->assertSame($index < 2 ? route('protected.custom-form-entry-document', [
                'entry' => $entry,
                'fileIndex' => $index,
                'inline' => 1,
            ]) : null, $file['preview_url']);
        }

        app()->setLocale('km');
        $this->assertSame('ឯកសារភ្ជាប់', app(CustomFormEntryFiles::class)->previewFiles($entry)[0]['label']);
        $this->assertSame('ឯកសារភ្ជាប់.pdf', app(CustomFormEntryFiles::class)->previewFiles($entry)[0]['name']);
    }

    public function test_preview_metadata_resolves_nested_legacy_uploads_without_duplicates_or_missing_files(): void
    {
        $form = $this->form();
        $form->fields()->create(['name' => 'document', 'type' => 'file_upload']);
        Storage::disk('public')->put('custom-form-uploads/legacy.pdf', 'legacy-pdf');
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'data' => ['section' => ['document' => [
                '/storage/custom-form-uploads/legacy.pdf',
                'custom-form-uploads/legacy.pdf',
                'custom-form-uploads/missing.pdf',
                '../unrelated.pdf',
            ]]],
        ]);

        $files = app(CustomFormEntryFiles::class)->previewFiles($entry);

        $this->assertCount(1, $files);
        $this->assertSame('document', $files[0]['label']);
        $this->assertSame('document.pdf', $files[0]['name']);
        $this->assertSame('pdf', $files[0]['preview_type']);
        $this->assertSame(route('protected.custom-form-entry-document', [
            'entry' => $entry,
            'fileIndex' => 0,
            'inline' => 1,
        ]), $files[0]['preview_url']);
    }

    public function test_clicking_uploaded_files_opens_preview_modal_with_download_controls(): void
    {
        $admin = $this->signInAdmin();

        $form = $this->form();
        $form->fields()->create(['name' => 'document', 'label' => 'Application document', 'type' => 'file_upload']);
        $form->fields()->create(['name' => 'photo', 'label' => 'Candidate photo', 'type' => 'image_upload']);
        $form->fields()->create(['name' => 'letter', 'label' => 'Supporting letter', 'type' => 'file_upload']);
        Storage::disk('private')->put('custom-form-uploads/application.pdf', '%PDF-1.4');
        Storage::disk('private')->put('custom-form-uploads/photo.png', 'image-data');
        Storage::disk('private')->put('custom-form-uploads/letter.docx', 'word-data');
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $admin->getKey(),
            'data' => [
                'document' => 'custom-form-uploads/application.pdf',
                'photo' => 'custom-form-uploads/photo.png',
                'letter' => 'custom-form-uploads/letter.docx',
            ],
        ]);

        $component = Livewire::withQueryParams(['form_id' => $form->getKey()])
            ->test(ListCustomFormEntries::class)
            ->assertSuccessful();
        $column = $component->instance()->getTable()->getColumn('uploaded_files');
        $this->assertNotNull($column);
        $column->record($entry);
        $this->assertSame('Download 3', $column->getState());
        $this->assertNull($column->getUrl());
        $this->assertInstanceOf(Action::class, $column->getAction());

        $files = app(CustomFormEntryFiles::class)->previewFiles($entry);
        $this->assertCount(3, $files);
        $this->assertSame(['Application document.pdf', 'Candidate photo.png', 'Supporting letter.docx'], array_column($files, 'name'));
        $this->assertSame(['pdf', 'image', null], array_column($files, 'preview_type'));
        $this->assertSame('Candidate photo', $files[1]['label']);
        $this->assertSame('Candidate photo.png', $files[1]['download_name']);
        $this->assertSame(route('protected.custom-form-entry-document', [
            'entry' => $entry,
            'fileIndex' => 1,
            'inline' => 1,
        ]), $files[1]['preview_url']);

        // Action-backed columns render this mounting call; callTableColumnAction only executes closures.
        $component->call('mountTableAction', 'preview_uploaded_files', (string) $entry->getKey());

        $action = $component->instance()->getMountedAction();
        $this->assertNotNull($action);
        $this->assertSame('preview_uploaded_files', $action->getName());
        $this->assertNull($action->getModalSubmitAction());
        $this->assertSame('filament.custom-form-entries.uploaded-files-preview', $action->getModalContent()->name());
        $html = $action->getModalContent()->render();
        $this->assertStringContainsString('Application document.pdf', $html);
        $this->assertStringNotContainsString('application.pdf', $html);
        $this->assertStringContainsString('Candidate photo.png', $html);
        $this->assertStringContainsString('Supporting letter.docx', $html);
        $this->assertStringContainsString('<iframe', $html);
        $footerActions = $action->getExtraModalFooterActions();
        $this->assertCount(1, $footerActions);
        $this->assertSame(route('protected.custom-form-entry-files-zip', ['entry' => $entry]), reset($footerActions)->getUrl());
    }

    public function test_image_only_form_has_uploaded_files_preview_column(): void
    {
        $admin = $this->signInAdmin();
        $form = $this->form();
        $form->fields()->create(['name' => 'photo', 'label' => 'Portrait', 'type' => 'image']);
        Storage::disk('private')->put('custom-form-uploads/portrait.jpg', 'portrait-image');
        $entry = CustomFormEntry::query()->create([
            'custom_form_id' => $form->getKey(),
            'created_by' => $admin->getKey(),
            'data' => ['photo' => 'custom-form-uploads/portrait.jpg'],
        ]);

        $component = Livewire::withQueryParams(['form_id' => $form->getKey()])
            ->test(ListCustomFormEntries::class)
            ->assertSuccessful();
        $column = $component->instance()->getTable()->getColumn('uploaded_files');

        $this->assertNotNull($column);
        $column->record($entry);
        $this->assertSame('Download 1', $column->getState());
        $this->assertInstanceOf(Action::class, $column->getAction());
        $this->assertNull($column->getUrl());
        $files = app(CustomFormEntryFiles::class)->previewFiles($entry);
        $this->assertCount(1, $files);
        $this->assertSame('image', $files[0]['preview_type']);
        $this->assertSame('Portrait.jpg', $files[0]['name']);
        $this->assertSame('Portrait.jpg', $files[0]['download_name']);
    }

    private function signInAdmin(): User
    {
        Filament::setCurrentPanel('app');
        $admin = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Preview administrator',
            'username' => 'uploaded_files_preview_admin',
            'email' => 'uploaded_files_preview_admin@example.test',
            'password' => 'password',
            'date_of_birth' => '2000-01-01',
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);

        return $admin;
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Uploaded file previews',
            'slug' => 'uploaded-file-previews',
            'schema' => [],
            'is_active' => true,
        ]);
    }
}
