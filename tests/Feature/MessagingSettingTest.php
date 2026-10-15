<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\MessagingSettings\MessagingSettingResource;
use App\Filament\Admin\Resources\MessagingSettings\Pages\EditMessagingSetting;
use App\Models\MessagingSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use App\Support\PlasGateSms;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessagingSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'cashier_officer', 'guard_name' => 'web']);
        $this->admin = $this->user('admin_user', 'admin');

        config([
            'mail.mailers.smtp.host' => 'env-host.test',
            'mail.mailers.smtp.password' => 'env-mail-password',
            'mail.from.address' => 'env@example.test',
            'services.plasgate.private_key' => 'env-private-key',
            'services.plasgate.secret' => 'env-secret',
            'services.plasgate.sender' => 'EnvSender',
        ]);
    }

    public function test_saved_values_replace_env_and_empty_ones_keep_env(): void
    {
        MessagingSetting::singleton()->update([
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_password' => 'new-app-password',
            'plasgate_private_key' => 'new-private-key',
            'plasgate_sender' => 'UHS',
        ]);

        MessagingSetting::applyToConfig();

        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('new-app-password', config('mail.mailers.smtp.password'));
        $this->assertSame('new-private-key', config('services.plasgate.private_key'));
        $this->assertSame('UHS', config('services.plasgate.sender'));

        // Not saved in the menu: still from .env.
        $this->assertSame('env@example.test', config('mail.from.address'));
        $this->assertSame('env-secret', config('services.plasgate.secret'));
    }

    public function test_passwords_and_keys_are_stored_encrypted(): void
    {
        MessagingSetting::singleton()->update([
            'mail_password' => 'new-app-password',
            'plasgate_private_key' => 'new-private-key',
            'plasgate_secret' => 'new-secret',
        ]);

        $row = DB::table('messaging_settings')->first();

        foreach (['mail_password' => 'new-app-password', 'plasgate_private_key' => 'new-private-key', 'plasgate_secret' => 'new-secret'] as $column => $plain) {
            $this->assertNotSame($plain, $row->{$column});
            $this->assertStringNotContainsString($plain, (string) $row->{$column});
        }

        $this->assertSame('new-secret', MessagingSetting::singleton()->plasgate_secret);
    }

    public function test_admin_saves_the_settings_and_sees_them_again_on_the_page(): void
    {
        $this->actingAs($this->admin);
        $settings = MessagingSetting::singleton();
        $settings->update(['plasgate_secret' => 'saved-secret']);

        $this->get(MessagingSettingResource::getUrl('index'))->assertRedirect();

        Livewire::test(EditMessagingSetting::class, ['record' => $settings->getRouteKey()])
            // Saved keys are shown (as password fields) like the other values.
            ->assertSchemaStateSet(['plasgate_secret' => 'saved-secret'])
            ->fillForm([
                'mail_host' => 'smtp.gmail.com',
                'mail_username' => 'office@example.test',
                'mail_password' => 'typed-app-password',
                'plasgate_private_key' => 'typed-private-key',
                'plasgate_sender' => 'UHS',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet([
                'mail_host' => 'smtp.gmail.com',
                'mail_password' => 'typed-app-password',
                'plasgate_private_key' => 'typed-private-key',
                'plasgate_secret' => 'saved-secret',
            ]);

        $settings->refresh();
        $this->assertSame('smtp.gmail.com', $settings->mail_host);
        $this->assertSame('typed-app-password', $settings->mail_password);
        $this->assertSame('typed-private-key', $settings->plasgate_private_key);
        $this->assertSame('saved-secret', $settings->plasgate_secret);
        $this->assertSame('typed-app-password', config('mail.mailers.smtp.password'));
    }

    public function test_emptying_a_key_removes_it_so_env_is_used(): void
    {
        $this->actingAs($this->admin);
        $settings = MessagingSetting::singleton();
        $settings->update(['plasgate_secret' => 'saved-secret']);

        Livewire::test(EditMessagingSetting::class, ['record' => $settings->getRouteKey()])
            ->fillForm(['plasgate_secret' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($settings->refresh()->getRawOriginal('plasgate_secret'));
    }

    public function test_sms_otp_is_sent_with_the_saved_plasgate_keys(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        config(['services.plasgate.test_phone' => null]);

        MessagingSetting::singleton()->update([
            'plasgate_private_key' => 'menu-private-key',
            'plasgate_secret' => 'menu-secret',
            'plasgate_sender' => 'UHS',
        ]);
        MessagingSetting::applyToConfig();

        $this->assertTrue(PlasGateSms::send('015916217', 'Your code: 123456'));

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'private_key=menu-private-key')
            && $request->header('X-Secret') === ['menu-secret']
            && $request['globals']['sender'] === 'UHS');
    }

    public function test_only_admin_can_open_it(): void
    {
        $settings = MessagingSetting::singleton();

        $this->actingAs($this->user('officer', 'cashier_officer'));
        $this->assertFalse(MessagingSettingResource::canAccess());
        $this->get(MessagingSettingResource::getUrl('edit', ['record' => $settings]))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->admin);
        $this->assertTrue(MessagingSettingResource::canAccess());
        $this->get(MessagingSettingResource::getUrl('edit', ['record' => $settings]))->assertOk();
    }

    public function test_the_role_page_offers_only_read_and_update(): void
    {
        $this->assertSame(['viewAny', 'update'], FilamentActionPermissions::resourcePolicyMethods()[MessagingSettingResource::class]);

        foreach (['ViewAny', 'Update'] as $action) {
            Permission::findOrCreate("{$action}:MessagingSetting", 'web');
        }
        Role::findByName('cashier_officer')->givePermissionTo(['ViewAny:MessagingSetting', 'Update:MessagingSetting']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $settings = MessagingSetting::singleton();
        $this->actingAs($this->user('officer', 'cashier_officer'));

        $this->assertTrue(MessagingSettingResource::canAccess());
        Livewire::test(EditMessagingSetting::class, ['record' => $settings->getRouteKey()])
            ->fillForm(['plasgate_sender' => 'UHS'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('UHS', $settings->refresh()->plasgate_sender);
    }

    private function user(string $username, string $role): User
    {
        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
