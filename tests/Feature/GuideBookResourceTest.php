<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\GuideBooks\GuideBookResource;
use App\Filament\Admin\Resources\GuideBooks\Pages\EditGuideBook;
use App\Filament\Admin\Resources\GuideBooks\Pages\ManageGuideBook;
use App\Filament\Admin\Resources\GuideBooks\Pages\ViewGuideBook;
use App\Models\GuideBook;
use App\Models\User;
use App\Policies\GuideBookPolicy;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuideBookResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Filament::getPanel('app')->navigation(fn (NavigationBuilder $builder) => $builder->items([]));
        Storage::fake('guide_books');
        GuideBook::query()->delete();
        $this->actor(['ViewAny', 'Update']);
    }

    private function actor(array $actions): void
    {
        $role = Role::findOrCreate('guide-operator-'.md5(implode('|', $actions)), 'web');
        foreach ($actions as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':GuideBook', 'web'));
        }
        $user = User::query()->forceCreate(['registration_type' => 'admin', 'name' => 'Guide operator', 'username' => uniqid('guide'), 'email' => uniqid().'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user, 'web');
    }

    private function data(): array
    {
        return ['title_en' => 'Login instructions', 'title_km' => 'សេចក្ដីណែនាំចូលប្រព័ន្ធ',
            'file_path' => UploadedFile::fake()->createWithContent('instructions.pdf', "%PDF-1.4\nInstructions\n%%EOF")];
    }

    public function test_role_form_only_offers_supported_guide_book_permissions(): void
    {
        $grants = DB::table('role_has_permissions')->get()->toArray();
        $entity = FilamentShield::getResources()[GuideBookResource::class];
        $this->assertEqualsCanonicalizing(
            ['ViewAny:GuideBook', 'Update:GuideBook', 'Reset:GuideBook'],
            array_keys(RoleResource::getResourcePermissionOptions($entity)),
        );
        $this->assertFalse((new GuideBookPolicy)->create(auth()->user()));
        $this->assertEquals($grants, DB::table('role_has_permissions')->get()->toArray());
    }

    private function record(): GuideBook
    {
        Storage::disk('guide_books')->put('files/test.pdf', "%PDF-1.4\nTest\n%%EOF");

        return GuideBook::create(['title_en' => 'Test guide', 'title_km' => 'សៀវភៅណែនាំ', 'file_path' => 'files/test.pdf',
            'file_name' => 'test.pdf', 'is_published' => false]);
    }

    public function test_authorized_users_can_edit_upload_read_and_publish_without_view_or_delete_buttons(): void
    {
        $grants = DB::table('role_has_permissions')->get()->toArray();
        $page = Livewire::test(ManageGuideBook::class);
        $guide = GuideBook::sole();
        $page->assertRedirect(GuideBookResource::getUrl('edit', ['record' => $guide]));
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->fillForm($this->data())->call('save')->assertHasNoFormErrors();
        $guide->refresh();
        $this->assertFalse($guide->is_published);
        $this->assertTrue(GuideBook::validFilePath($guide->file_path));
        Storage::disk('guide_books')->assertExists($guide->file_path);
        $this->assertSame('instructions.pdf', $guide->file_name);
        Livewire::test(ManageGuideBook::class)->assertRedirect(GuideBookResource::getUrl('edit', ['record' => $guide]));
        Livewire::test(ViewGuideBook::class, ['record' => $guide->id])->assertOk();
        $this->get(route('guide-books.preview', $guide))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->fillForm(['file_path' => []])->fillForm([
            'file_path' => UploadedFile::fake()->image('instructions.png'), 'is_published' => true,
        ])->call('save')->assertHasNoFormErrors();
        $guide->refresh();
        $this->assertTrue($guide->is_published);
        $this->assertStringEndsWith('.png', $guide->file_path);
        $this->assertSame('image/png', Storage::disk('guide_books')->mimeType($guide->file_path));
        $this->assertSame('instructions.png', $guide->file_name);
        Storage::disk('guide_books')->assertExists($guide->file_path);
        $this->get(route('guide-books.file', $guide))->assertOk()->assertHeader('Content-Type', 'image/png');
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])
            ->assertActionDoesNotExist('view')->assertActionDoesNotExist('delete');
        $this->assertFalse(GuideBookResource::canDelete($guide));
        $this->assertFalse(GuideBookResource::canDeleteAny());
        $this->assertDatabaseCount('system_guide_books', 1);
        $this->assertEquals($grants, DB::table('role_has_permissions')->get()->toArray());
    }

    public function test_permissions_are_required_for_each_management_action_and_draft_preview(): void
    {
        $guide = $this->record();
        $this->actor(['ViewAny']);
        Livewire::test(ManageGuideBook::class)->assertRedirect(GuideBookResource::getUrl('view', ['record' => $guide]));
        $this->assertFalse(GuideBookResource::canCreate());
        $this->assertFalse(GuideBookResource::hasPage('create'));
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->assertForbidden();
        Livewire::test(ViewGuideBook::class, ['record' => $guide->id])->assertOk();
        $this->get(route('guide-books.preview', $guide))->assertOk();
        $this->assertFalse(GuideBookResource::canDelete($guide));
        $this->actor([]);
        Livewire::test(ManageGuideBook::class)->assertForbidden();
        Livewire::test(ViewGuideBook::class, ['record' => $guide->id])->assertForbidden();
        $this->get(route('guide-books.preview', $guide))->assertForbidden();
        $this->assertDatabaseCount('system_guide_books', 1);
    }

    public function test_legacy_delete_permissions_cannot_restore_deleted_actions(): void
    {
        $guide = $this->record();
        $this->actor(['ViewAny', 'View', 'Create', 'Update', 'Delete']);
        $this->assertFalse(GuideBookResource::canCreate());
        $this->assertFalse(GuideBookResource::canDelete($guide));
        $this->assertFalse(GuideBookResource::canDeleteAny());
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])
            ->assertActionDoesNotExist('view')->assertActionDoesNotExist('delete');
        $this->assertDatabaseCount('system_guide_books', 1);
    }

    public function test_reset_requires_its_own_permission_and_cannot_be_called_by_editors(): void
    {
        $guide = $this->record();
        $guide->update(['is_published' => true]);
        $before = $guide->fresh()->getAttributes();
        $this->actor(['ViewAny', 'Update']);
        $page = Livewire::test(EditGuideBook::class, ['record' => $guide->id]);
        $action = TestAction::make('resetGuideBook');
        $page->assertActionHidden($action);
        $page->mountAction($action)->assertSet('mountedActions', []);
        $this->assertEquals($before, $guide->fresh()->getAttributes());
        Storage::disk('guide_books')->assertExists($guide->file_path);
        $this->actor(['ViewAny', 'Reset']);
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->assertForbidden();
    }

    public function test_reset_is_only_persisted_after_a_successful_save(): void
    {
        $guide = $this->record();
        $guide->update(['title_en' => 'Changed title', 'title_km' => 'ថ្មី', 'is_published' => true]);
        $id = $guide->id;
        $this->actor(['ViewAny', 'Update', 'Reset']);
        $grants = DB::table('role_has_permissions')->get()->toArray();
        $page = Livewire::test(EditGuideBook::class, ['record' => $id]);
        $action = TestAction::make('resetGuideBook');
        $before = $guide->fresh()->getAttributes();
        $path = $guide->file_path;
        $page->assertActionVisible($action)->callAction($action)
            ->assertHasNoActionErrors()->assertNotified(__('guide_books.reset_pending'))
            ->assertSet('guideResetPending', true)
            ->assertSet('data.title_en', 'System User Guide')->assertSet('data.is_published', false);
        $this->assertEquals($before, $guide->fresh()->getAttributes());
        Storage::disk('guide_books')->assertExists($path);
        $this->get(route('guide-books.file', $guide))->assertOk();
        // Leaving without saving reloads the saved guide, not the pending reset.
        Livewire::test(EditGuideBook::class, ['record' => $id])
            ->assertSet('data.title_en', 'Changed title')->assertSet('guideResetPending', false);
        $page->fillForm(['title_en' => ''])->call('save')->assertHasFormErrors(['title_en' => 'required']);
        $this->assertEquals($before, $guide->fresh()->getAttributes());
        Storage::disk('guide_books')->assertExists($path);
        $page->fillForm(['title_en' => 'System User Guide'])->call('save')->assertHasNoFormErrors()
            ->assertSet('guideResetPending', false);
        $guide->refresh();
        $this->assertSame($id, $guide->id);
        $this->assertSame(GuideBook::defaultData(), $guide->only(array_keys(GuideBook::defaultData())));
        $this->assertDatabaseCount('system_guide_books', 1);
        $page->assertSet('data.title_en', 'System User Guide')->assertSet('data.is_published', false);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
        $this->assertEquals($grants, DB::table('role_has_permissions')->get()->toArray());
    }

    public function test_pending_reset_cannot_be_saved_after_reset_permission_is_revoked(): void
    {
        $guide = $this->record();
        $before = $guide->fresh()->getAttributes();
        $this->actor(['ViewAny', 'Update', 'Reset']);
        $page = Livewire::test(EditGuideBook::class, ['record' => $guide->id]);
        $page->callAction(TestAction::make('resetGuideBook'));
        $this->actor(['ViewAny', 'Update']);
        $page->call('save')->assertForbidden();
        $this->assertEquals($before, $guide->fresh()->getAttributes());
        Storage::disk('guide_books')->assertExists($guide->file_path);
    }

    public function test_pending_reset_cannot_publish_without_a_guide_file(): void
    {
        $guide = $this->record();
        $before = $guide->fresh()->getAttributes();
        $this->actor(['ViewAny', 'Update', 'Reset']);
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])
            ->callAction(TestAction::make('resetGuideBook'))
            ->fillForm(['is_published' => true])->call('save')->assertHasFormErrors(['file_path' => 'required']);
        $this->assertEquals($before, $guide->fresh()->getAttributes());
    }

    public function test_reset_permission_migration_is_scoped_and_idempotent(): void
    {
        $admin = Role::findOrCreate('admin', 'web');
        $reader = Role::findOrCreate('guide-reader', 'web');
        $ordinaryEditor = Role::findOrCreate('guide-editor', 'web');
        foreach (['ViewAny', 'Update'] as $action) {
            $permission = Permission::findOrCreate($action.':GuideBook', 'web');
            $admin->givePermissionTo($permission);
            $ordinaryEditor->givePermissionTo($permission);
        }
        $reader->givePermissionTo(Permission::findOrCreate('ViewAny:GuideBook', 'web'));
        $before = DB::table('role_has_permissions')->get()->toArray();
        $migration = require database_path('migrations/2026_11_26_000007_add_guide_book_reset_permission.php');
        $migration->up();
        $migration->up();
        $this->assertTrue($admin->fresh()->hasPermissionTo('Reset:GuideBook'));
        $this->assertFalse($reader->fresh()->hasPermissionTo('Reset:GuideBook'));
        $this->assertFalse($ordinaryEditor->fresh()->hasPermissionTo('Reset:GuideBook'));
        $resetId = Permission::findByName('Reset:GuideBook', 'web')->id;
        $this->assertEquals($before, DB::table('role_has_permissions')->where('permission_id', '!=', $resetId)->get()->toArray());
    }

    public function test_management_opens_edit_with_default_data_and_has_no_create_page(): void
    {
        $page = Livewire::test(ManageGuideBook::class);
        $guide = GuideBook::sole();
        $page->assertRedirect(GuideBookResource::getUrl('edit', ['record' => $guide]));
        $this->assertSame('System User Guide', $guide->title_en);
        $this->assertSame('សៀវភៅណែនាំប្រើប្រាស់ប្រព័ន្ធ', $guide->title_km);
        $this->assertSame(1, $guide->id);
        $this->assertFalse(Schema::hasColumn('system_guide_books', 'applications'));
        $this->assertFalse(Schema::hasColumn('system_guide_books', 'sort_order'));
        $this->assertSame('', $guide->file_path);
        $this->assertFalse($guide->is_published);
        $this->assertFalse(GuideBookResource::canCreate());
        $this->assertFalse(GuideBookResource::hasPage('create'));
        $this->get(GuideBookResource::getUrl('legacy-create'))->assertRedirect(GuideBookResource::getUrl('edit', ['record' => $guide]));
        Livewire::test(ManageGuideBook::class)->assertRedirect(GuideBookResource::getUrl('edit', ['record' => $guide]));
        $this->assertDatabaseCount('system_guide_books', 1);
        $this->get(route('guide-books.file', $guide))->assertNotFound();
    }

    public function test_default_setup_preserves_existing_guides_and_does_not_insert_duplicates(): void
    {
        $guide = $this->record();
        $before = $guide->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_11_26_000004_seed_default_guide_book.php');
        $migration->up();
        $this->assertEquals($before, array_intersect_key($guide->fresh()->getAttributes(), $before));
        $guide->delete();
        $migration->up();
        $migration->up();
        $default = GuideBook::sole();
        $this->assertSame(1, $default->id);
        $this->assertSame('System User Guide', $default->title_en);
        $this->assertSame('', $default->file_path);
        $this->assertFalse($default->is_published);
        $this->assertSame($default->id, GuideBook::ensureDefault()->id);
        $this->assertDatabaseCount('system_guide_books', 1);
    }

    public function test_users_without_edit_permission_cannot_initialize_a_missing_default(): void
    {
        $this->actor(['ViewAny']);
        Livewire::test(ManageGuideBook::class)->assertForbidden();
        $this->assertDatabaseCount('system_guide_books', 0);
    }

    public function test_uploads_reject_unsupported_types_and_oversized_files(): void
    {
        $guide = GuideBook::ensureDefault();
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->fillForm([...$this->data(), 'file_path' => UploadedFile::fake()->createWithContent('unsafe.html', '<html>Unsafe</html>')])
            ->call('save')->assertHasFormErrors();
        Livewire::test(EditGuideBook::class, ['record' => $guide->id])->fillForm([...$this->data(), 'file_path' => UploadedFile::fake()->image('large.png')->size(10241)])
            ->call('save')->assertHasFormErrors();
        $this->assertDatabaseCount('system_guide_books', 1);
        $this->assertSame('', $guide->fresh()->file_path);
        $this->assertFalse($guide->fresh()->is_published);
    }
}
