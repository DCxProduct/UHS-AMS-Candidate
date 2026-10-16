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

    public function test_forgot_password_by_sms_is_on_by_default(): void
    {
        $this->assertTrue(MessagingSetting::forgotPasswordBySms());

        $this->get(route('student.password.request'))
            ->assertOk()
            ->assertSee(route('student.password.request', ['method' => 'phone']), false);

        $this->get(route('student.password.request', ['method' => 'phone']))
            ->assertOk()
            ->assertSee(route('student.password.phone'), false);
    }

    public function test_turning_forgot_password_by_sms_off_leaves_only_email(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(EditMessagingSetting::class, ['record' => MessagingSetting::singleton()->getRouteKey()])
            ->assertSchemaStateSet(['forgot_password_sms' => true])
            ->fillForm(['forgot_password_sms' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse(MessagingSetting::forgotPasswordBySms());

        auth()->logout();
        $this->flushSession();


        $this->get(route('student.password.request', ['method' => 'phone']))
            ->assertOk()
            ->assertSee('action="'.route('student.password.email').'"', false)
            ->assertDontSee('action="'.route('student.password.phone').'"', false)
            ->assertDontSee(route('student.password.request', ['method' => 'phone']), false);


        $this->post(route('student.password.phone'), ['phone' => '015916217'])->assertNotFound();
        $this->get(route('student.password.phone.verify', ['phone' => '015916217']))->assertNotFound();
        $this->post(route('student.password.phone.update'), [
            'phone' => '015916217',
            'code' => '123456',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();
    }

    public function test_turning_forgot_password_by_email_off_leaves_only_phone(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(EditMessagingSetting::class, ['record' => MessagingSetting::singleton()->getRouteKey()])
            ->assertSchemaStateSet(['forgot_password_email' => true])
            ->fillForm(['forgot_password_email' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse(MessagingSetting::forgotPasswordByEmail());
        $this->assertTrue(MessagingSetting::forgotPasswordBySms());

        auth()->logout();
        $this->flushSession();

        $this->get(route('student.password.request'))
            ->assertOk()
            ->assertSee('action="'.route('student.password.phone').'"', false)
            ->assertDontSee('action="'.route('student.password.email').'"', false)
            ->assertDontSee(route('student.password.request', ['method' => 'phone']), false);

        $this->post(route('student.password.email'), ['email' => 'someone@example.test'])->assertNotFound();
        $this->get(route('student.password.reset', ['token' => 'abc', 'email' => 'someone@example.test']))->assertNotFound();
        $this->post(route('student.password.update'), [
            'token' => 'abc',
            'email' => 'someone@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertNotFound();
    }

    public function test_both_ways_cannot_be_turned_off(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(EditMessagingSetting::class, ['record' => MessagingSetting::singleton()->getRouteKey()])
            ->fillForm(['forgot_password_email' => false, 'forgot_password_sms' => false])
            ->call('save')
            ->assertHasFormErrors(['forgot_password_email']);

        $this->assertTrue(MessagingSetting::forgotPasswordByEmail());


        MessagingSetting::singleton()->update(['forgot_password_email' => false, 'forgot_password_sms' => false]);
        $this->assertTrue(MessagingSetting::forgotPasswordByEmail());
        $this->assertFalse(MessagingSetting::forgotPasswordBySms());
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
