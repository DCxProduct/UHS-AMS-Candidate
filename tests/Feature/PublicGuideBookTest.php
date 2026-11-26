<?php

namespace Tests\Feature;

use App\Models\GuideBook;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicGuideBookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (require database_path('migrations/2026_11_26_000002_create_system_guide_books_table.php'))->up();
        Storage::fake('guide_books');
    }

    private function guide(array $data = []): GuideBook
    {
        $path = 'files/guide-'.(GuideBook::count() + 1).'.pdf';
        Storage::disk('guide_books')->put($path, "%PDF-1.4\nGuide book\n%%EOF");

        return GuideBook::create([
            'title_en' => 'System guide', 'title_km' => 'សៀវភៅណែនាំប្រព័ន្ធ',
            'file_path' => $path, 'file_name' => 'guide.pdf',
            'is_published' => true, ...$data,
        ]);
    }

    public function test_login_link_opens_the_published_upload_directly(): void
    {
        $this->guide(['is_published' => false]);
        $first = $this->guide();
        $second = $this->guide();
        $this->get(route('guide-books.open'))->assertRedirect(route('guide-books.show', $first));
        $this->get(route('guide-books.show', $first))->assertOk()
            ->assertSee(route('guide-books.file', $first), false)
            ->assertSee(route('guide-books.index'), false);
        $first->update(['is_published' => false]);
        $this->get(route('guide-books.open'))->assertRedirect(route('guide-books.show', $second));
    }

    public function test_login_link_falls_back_to_the_empty_library_when_no_guide_is_published(): void
    {
        $this->guide(['is_published' => false]);
        $this->get(route('guide-books.open'))->assertRedirect(route('guide-books.index'));
        $this->get(route('guide-books.index'))->assertOk()->assertSee(__('guide_books.empty'));
    }

    public function test_guests_can_read_and_download_published_pdf_guides_without_signing_in(): void
    {
        $guide = $this->guide();
        $this->withSession(['locale' => 'en'])->get(route('guide-books.index'))->assertOk()->assertSee('System guide');
        $this->get(route('guide-books.show', $guide))->assertOk()->assertSee('iframe', false);
        $this->get(route('guide-books.file', $guide))->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Content-Disposition', 'inline; filename=guide.pdf');
        $this->get(route('guide-books.file', ['guideBook' => $guide, 'download' => 1]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=guide.pdf');
    }

    public function test_draft_guides_are_not_publicly_accessible(): void
    {
        $draft = $this->guide(['title_en' => 'Draft guide', 'is_published' => false]);
        $this->get(route('guide-books.index'))->assertOk()->assertDontSee('Draft guide');
        $this->get(route('guide-books.show', $draft))->assertNotFound();
        $this->get(route('guide-books.file', $draft))->assertNotFound();
    }

    public function test_khmer_labels_and_image_preview_are_supported(): void
    {
        $first = $this->guide(['title_km' => 'ណែនាំទីមួយ']);
        $this->guide(['title_km' => 'ណែនាំទីពីរ']);
        $this->withSession(['locale' => 'km'])->get(route('guide-books.index'))->assertOk()->assertSeeInOrder(['ណែនាំទីមួយ', 'ណែនាំទីពីរ']);
        Storage::disk('guide_books')->put('files/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmN8AAAAASUVORK5CYII='));
        $first->update(['file_path' => 'files/image.png', 'file_name' => 'image.png']);
        $this->get(route('guide-books.show', $first))->assertOk()->assertSee('guide-image', false);
        $this->get(route('guide-books.file', $first))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_removing_display_settings_preserves_guide_data_and_its_uploaded_file(): void
    {
        $guide = $this->guide();
        Schema::table('system_guide_books', function (Blueprint $table): void {
            $table->json('applications')->nullable();
            $table->integer('sort_order')->default(1);
        });
        DB::table('system_guide_books')->where('id', $guide->id)->update(['applications' => '["student"]', 'sort_order' => 99]);
        $before = $guide->fresh()->getAttributes();
        unset($before['applications'], $before['sort_order']);
        $migration = require database_path('migrations/2026_11_26_000005_remove_guide_book_display_settings.php');
        $migration->up();
        $migration->up();
        $this->assertFalse(Schema::hasColumn('system_guide_books', 'applications'));
        $this->assertFalse(Schema::hasColumn('system_guide_books', 'sort_order'));
        $this->assertSame($before, $guide->fresh()->getAttributes());
        Storage::disk('guide_books')->assertExists($guide->file_path);
    }

    public function test_default_id_migration_preserves_contents_and_files_when_moving_the_guide_to_id_one(): void
    {
        $guide = $this->guide();
        DB::table('system_guide_books')->where('id', $guide->id)->update(['id' => 9]);
        $before = (array) DB::table('system_guide_books')->where('id', 9)->first();
        $before['id'] = 1;
        $migration = require database_path('migrations/2026_11_26_000006_use_id_one_for_default_guide_book.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, (array) DB::table('system_guide_books')->where('id', 1)->first());
        $this->assertDatabaseCount('system_guide_books', 1);
        Storage::disk('guide_books')->assertExists($guide->file_path);
    }

    public function test_id_one_migration_does_not_overwrite_an_existing_guide_or_remove_other_guides(): void
    {
        $this->guide();
        $this->guide(['title_en' => 'Other guide']);
        $before = DB::table('system_guide_books')->orderBy('id')->get()->toArray();
        (require database_path('migrations/2026_11_26_000006_use_id_one_for_default_guide_book.php'))->up();
        $this->assertEquals($before, DB::table('system_guide_books')->orderBy('id')->get()->toArray());
    }

    public function test_unpublishing_a_guide_immediately_blocks_its_public_file(): void
    {
        $guide = $this->guide();
        $this->get(route('guide-books.file', $guide))->assertOk();
        $guide->update(['is_published' => false]);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
    }

    public function test_missing_files_unsafe_paths_and_disguised_html_are_not_served(): void
    {
        $guide = $this->guide();
        Storage::disk('guide_books')->delete($guide->file_path);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
        $guide->update(['file_path' => '../secret.pdf']);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
        Storage::disk('guide_books')->put('files/fake.pdf', '<html><script>alert(1)</script></html>');
        $guide->update(['file_path' => 'files/fake.pdf']);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
    }

    public function test_replaced_and_deleted_files_are_removed_and_the_shared_migration_is_repeatable(): void
    {
        $guide = $this->guide();
        $old = $guide->file_path;
        Storage::disk('guide_books')->put('files/new.pdf', "%PDF-1.4\nNew guide\n%%EOF");
        $guide->update(['file_path' => 'files/new.pdf']);
        Storage::disk('guide_books')->assertMissing($old);
        (require database_path('migrations/2026_11_26_000002_create_system_guide_books_table.php'))->up();
        $this->assertSame('files/new.pdf', $guide->fresh()->file_path);
        $guide->delete();
        Storage::disk('guide_books')->assertMissing('files/new.pdf');
    }

    public function test_login_previews_and_downloads_published_guides_inside_a_modal_without_new_tab_links(): void
    {
        $guide = $this->guide();
        $this->withSession(['locale' => 'en'])->get(config('guide-books.login_path'))->assertOk()
            ->assertSee('data-fi-modal-id="login-guide-book"', false)
            ->assertSee(route('guide-books.file', $guide), false)
            ->assertSee(route('guide-books.file', ['guideBook' => $guide, 'download' => 1]), false)
            ->assertDontSee(route('guide-books.open'), false);
        $modal = view('guide-books.login-link')->render();
        $this->assertStringContainsString('<iframe', $modal);
        $this->assertStringContainsString('<template x-if="isOpen">', $modal);
        $this->assertStringNotContainsString('target="_blank"', $modal);
    }

    public function test_login_modal_previews_images_but_never_exposes_draft_files(): void
    {
        $draft = $this->guide(['is_published' => false]);
        $modal = view('guide-books.login-link')->render();
        $this->assertStringNotContainsString(route('guide-books.file', $draft), $modal);
        $this->assertStringNotContainsString('<iframe', $modal);
        Storage::disk('guide_books')->put('files/image.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+nmN8AAAAASUVORK5CYII='));
        $draft->update(['is_published' => true, 'file_path' => 'files/image.png']);
        $modal = view('guide-books.login-link')->render();
        $this->assertStringContainsString('<img', $modal);
        $this->assertStringContainsString(route('guide-books.file', $draft), $modal);
        $this->assertStringNotContainsString('target="_blank"', $modal);
    }

    public function test_the_login_page_has_a_guide_modal_and_an_empty_library_stays_usable(): void
    {
        $this->get(route('guide-books.index'))->assertOk()->assertSee(__('guide_books.empty'));
        $this->get(config('guide-books.login_path'))->assertOk()->assertSee('data-fi-modal-id="login-guide-book"', false);
    }
}
