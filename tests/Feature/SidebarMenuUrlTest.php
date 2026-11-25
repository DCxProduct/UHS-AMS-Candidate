<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PaymentTypes\PaymentTypeResource;
use App\Filament\Admin\Resources\SidebarMenus\Pages\EditSidebarMenu;
use App\Filament\Admin\Resources\SidebarMenus\SidebarMenuResource;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\ResolveSidebarMenuUrl;
use App\Models\Role;
use App\Models\SidebarMenu;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use App\Support\SidebarMenuUrls;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SidebarMenuUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        FilamentActionPermissions::sync();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::query()->forceCreate(['name' => 'URL tester', 'username' => uniqid('url_'), 'email' => uniqid().'@example.test', 'registration_type' => 'admin', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        $this->actingAs($user);

        return $user;
    }

    private function menu(User $user, string $class, string $name): SidebarMenu
    {
        return SidebarMenu::create(['role_id' => $user->roles()->first()->id, 'target_key' => 'item:'.$class, 'label_en' => $name, 'sort_order' => 1]);
    }

    public function test_renaming_dashboard_in_ui_changes_its_sidebar_url_and_keeps_the_original_route(): void
    {
        $user = $this->actor();
        $menu = $this->menu($user, Dashboard::class, 'Dashboard');
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->fillForm(['label_en' => 'Dashboardgasdadsadasd'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('/dashboardgasdadsadasd', app(SidebarMenuUrls::class)->forUrl('/dashboard'));
        $this->get('/dashboard')->assertRedirect('/dashboardgasdadsadasd');
        $this->get('/dashboardgasdadsadasd')->assertOk()->assertSee('/dashboardgasdadsadasd');
        $this->assertSame('filament.app.pages.dashboard', request()->route()->getName());
        $this->get('/dashboard?section=recent')->assertRedirect('/dashboardgasdadsadasd?section=recent');
    }

    public function test_resource_and_sidebar_management_menu_urls_follow_their_english_names(): void
    {
        $user = $this->actor();
        $this->menu($user, PaymentResource::class, 'Money Records');
        $this->menu($user, PaymentTypeResource::class, 'Money Methods');
        $this->menu($user, SidebarMenuResource::class, 'Navigation Settings');
        $urls = app(SidebarMenuUrls::class);
        $this->assertSame('/money-records/create?form=4#details', $urls->forUrl('/payment-records/create?form=4#details'));
        $this->assertSame('https://other.example/payment-records', $urls->forUrl('https://other.example/payment-records'));
        $this->assertNull($urls->renamedPath('/payment-records-other'));
        $this->get('/payment-records')->assertRedirect('/money-records');
        $this->get('/money-records')->assertOk();
        $this->get('/money-records/create')->assertNotFound();
        $this->get('/money-methods/create')->assertOk();
        $this->get('/money-records/999999/edit')->assertNotFound();
        $this->get('/sidebar-menus')->assertRedirect('/navigation-settings');
        $this->get('/navigation-settings')->assertOk();
    }

    public function test_aliases_preserve_authentication_and_resource_authorization(): void
    {
        $user = $this->actor();
        $this->menu($user, Dashboard::class, 'Overview Area');
        $this->menu($user, PaymentResource::class, 'Money Records');
        Auth::logout();
        $this->get('/overview-area')->assertRedirect('/login');
        $this->get('/money-records')->assertRedirect('/login');
        $this->get('/not-a-sidebar-menu')->assertNotFound();
        $this->actor('sidebar_reader_without_payment_permission');
        $this->get('/money-records')->assertForbidden();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->get('/overview-area')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_alias_resolution_preserves_post_data_uploads_query_and_original_endpoint(): void
    {
        $this->menu($this->actor(), PaymentResource::class, 'Money Records');
        $file = UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf');
        $request = Request::create('/money-records/42/edit?filter=paid', 'POST', ['amount' => '123.45'], [], ['receipt' => $file]);
        $response = (new ResolveSidebarMenuUrl)->handle($request, function (Request $resolved) use ($file): Response {
            $this->assertSame('POST', $resolved->method());
            $this->assertSame('/payment-records/42/edit', $resolved->getPathInfo());
            $this->assertSame('paid', $resolved->query('filter'));
            $this->assertSame('123.45', $resolved->input('amount'));
            $this->assertSame($file->getPathname(), $resolved->file('receipt')->getPathname());

            return new Response('Saved');
        });
        $this->assertSame('Saved', $response->getContent());
    }

    public function test_duplicate_or_reserved_names_do_not_take_over_other_routes(): void
    {
        $user = $this->actor();
        $dashboard = $this->menu($user, Dashboard::class, 'Reports Area');
        $this->menu($user, PaymentResource::class, 'Reports Area');
        $urls = app(SidebarMenuUrls::class);
        $first = $urls->forUrl('/dashboard');
        $second = $urls->forUrl('/payment-records');
        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('/reports-area', $first);
        $this->assertStringStartsWith('/reports-area', $second);
        $this->get($first)->assertOk();
        $this->get($second)->assertOk();
        $dashboard->update(['label_en' => 'Login']);
        $this->assertNotSame('/login', $urls->forUrl('/dashboard'));
        Auth::logout();
        $this->get('/login')->assertOk();
    }

    public function test_slug_uses_english_for_both_locales_and_resets_to_original_when_name_is_cleared(): void
    {
        $menu = $this->menu($this->actor(), Dashboard::class, 'My Dashboard');
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame('/my-dashboard', app(SidebarMenuUrls::class)->forUrl('/dashboard'));
        }
        $menu->update(['label_km' => 'ឈ្មោះថ្មី']);
        $this->assertSame('/my-dashboard', app(SidebarMenuUrls::class)->forUrl('/dashboard'));
        $menu->update(['label_en' => null]);
        $this->assertSame('/dashboard', app(SidebarMenuUrls::class)->forUrl('/dashboard'));
        $this->get('/dashboard')->assertOk();
    }

    public function test_renames_work_with_compiled_routes_without_rebuilding_the_route_cache(): void
    {
        $originalRoutes = app('router')->getRoutes();
        app('router')->setCompiledRoutes($originalRoutes->compile());
        try {
            $menu = $this->menu($this->actor(), Dashboard::class, 'First Overview');
            $this->get('/first-overview')->assertOk();
            $menu->update(['label_en' => 'Second Overview']);
            $this->get('/second-overview')->assertOk();
            $this->get('/dashboard')->assertRedirect('/second-overview');
        } finally {
            app('router')->setRoutes($originalRoutes);
        }
    }
}
