<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentStatusListRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
    }

    public function test_canonical_resource_urls_use_the_new_payment_status_list_slug(): void
    {
        $this->assertSame(url('/payment-status-lists'), PaymentStatusListResource::getUrl('index', panel: 'app'));
        $this->assertSame(url('/payment-status-lists/create'), PaymentStatusListResource::getUrl('create', panel: 'app'));
        $this->assertSame(
            'filament.app.resources.payment-status-lists.index',
            PaymentStatusListResource::getRouteBaseName(Filament::getPanel('app')).'.index',
        );
    }

    public function test_legacy_menu_urls_redirect_to_the_canonical_pages_with_filters_preserved(): void
    {
        $user = $this->staff('route_cashier');
        foreach (['ViewAny', 'Create'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':PaymentStatusList', 'web'));
        }
        $this->actingAs($user);

        $query = 'filters%5Bcandidate_payment_list_filters%5D%5Bform_id%5D=42&search=receipt';
        $response = $this->get('/unpaid-applications?'.$query)->assertStatus(301);
        $this->assertSame('/payment-status-lists', parse_url($response->headers->get('Location'), PHP_URL_PATH));
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $actualQuery);
        parse_str($query, $expectedQuery);
        $expectedQuery['filters']['payment_status_list_filters'] = $expectedQuery['filters']['candidate_payment_list_filters'];
        unset($expectedQuery['filters']['candidate_payment_list_filters']);
        $this->assertSame($expectedQuery, $actualQuery);

        $this->get('/unpaid-applications/create?form_id=42')
            ->assertStatus(301)
            ->assertRedirect(PaymentStatusListResource::getUrl('create', ['form_id' => 42], panel: 'app'));

        $this->assertSame(url('/unpaid-applications'), route('filament.app.resources.unpaid-applications.index'));
        $this->assertSame(url('/unpaid-applications/create'), route('filament.app.resources.unpaid-applications.create'));
    }

    public function test_legacy_redirects_do_not_bypass_authentication_or_new_permissions(): void
    {
        $this->get('/unpaid-applications')->assertRedirect('/login');
        $this->get('/unpaid-applications/create')->assertRedirect('/login');

        $user = $this->staff('route_unprivileged');
        foreach (['ViewAny', 'Create'] as $action) {
            Permission::findOrCreate($action.':PaymentStatusList', 'web');
        }
        $this->actingAs($user);
        $this->get('/unpaid-applications')->assertForbidden();
        $this->get('/unpaid-applications/create')->assertForbidden();
        $this->get('/payment-status-lists')->assertForbidden();
        $this->get('/payment-status-lists/create')->assertForbidden();
    }

    public function test_inactive_accounts_cannot_use_the_legacy_menu_redirect(): void
    {
        $user = $this->staff('route_inactive');
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:PaymentStatusList', 'web'));
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/unpaid-applications')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function staff(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
