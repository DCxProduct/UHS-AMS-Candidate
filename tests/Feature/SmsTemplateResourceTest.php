<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SmsTemplates\Pages\EditSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Models\Role;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Support\PasswordResetOtpSms;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmsTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));

        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web']);

        $this->admin = $this->user('admin_user', 'admin', ['admin']);
    }

    public function test_the_otp_sms_uses_its_template(): void
    {
        $user = $this->user('dara', 'student', locale: 'en');
        $user->forceFill(['phone' => '012345678'])->save();

        $this->assertSame(
            'UHS-AMS: Your password reset code is 123456. It expires in 10 minutes. Do not share this code.',
            PasswordResetOtpSms::text($user, '123456'),
        );

        SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP)->update([
            'custom_variables' => [['name' => 'hotline', 'value' => '023 123 456']],
            'body' => 'Hi {{ name }}, code {{ code }} ({{ minutes }} min). Help: {{ hotline }}',
        ]);

        $this->assertSame('Hi dara, code 654321 (10 min). Help: 023 123 456', PasswordResetOtpSms::text($user, '654321'));

        // The same text goes to a Khmer-language candidate.
        $user->forceFill(['locale' => 'km'])->save();
        $this->assertSame('Hi dara, code 654321 (10 min). Help: 023 123 456', PasswordResetOtpSms::text($user, '654321'));
    }

    public function test_the_phone_reset_sends_the_template_text(): void
    {
        config(['services.plasgate.private_key' => 'key', 'services.plasgate.secret' => 'secret', 'services.plasgate.test_phone' => null]);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        $user = $this->user('dara', 'student', locale: 'en');
        $user->forceFill(['phone' => '012345678'])->save();
        SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP)->update(['body' => 'Your UHS code: {{ code }}']);

        $this->post(route('student.password.phone'), ['phone' => '012345678']);

        Http::assertSent(fn (HttpRequest $request): bool => preg_match('/^Your UHS code: \d{6}$/', $request['messages'][0]['content']) === 1);
    }

    public function test_the_otp_template_page_shows_sms_text_and_can_send_a_test_sms(): void
    {
        config(['services.plasgate.private_key' => 'key', 'services.plasgate.secret' => 'secret', 'services.plasgate.test_phone' => null]);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        $this->actingAs($this->admin);

        $this->get(SmsTemplateResource::getUrl('index'))->assertOk()->assertSee(__('sms_templates.templates.reset_password_otp'));
        $template = SmsTemplate::query()->where('key', SmsTemplate::RESET_PASSWORD_OTP)->sole();

        $this->get(SmsTemplateResource::getUrl('edit', ['record' => $template]))
            ->assertOk()
            ->assertSee(__('sms_templates.fields.body'))
            ->assertDontSee('insert_variable', false);

        Livewire::test(EditSmsTemplate::class, ['record' => $template->getRouteKey()])
            ->assertSchemaStateSet(['body' => SmsTemplate::defaults(SmsTemplate::RESET_PASSWORD_OTP)['body']])
            ->fillForm(['body' => 'Code {{ code }}'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->callAction('sendTest', ['to' => '015 916 217'])
            ->assertNotified(__('sms_templates.send_test.sent', ['phone' => '015 916 217']));

        $this->assertSame('Code {{ code }}', $template->refresh()->body);
        Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['to'][0] === '85515916217'
            && $request['messages'][0]['content'] === 'Code 123456');
    }

    public function test_custom_variable_names_are_checked(): void
    {
        $this->actingAs($this->admin);
        $template = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);

        foreach (['Hot Line', '1phone', 'code'] as $badName) {
            Livewire::test(EditSmsTemplate::class, ['record' => $template->getRouteKey()])
                ->set('data.custom_variables', ['a' => ['name' => $badName, 'value' => 'x']])
                ->call('save')
                ->assertHasFormErrors(['custom_variables.a.name']);
        }
    }

    public function test_access_follows_role_permissions(): void
    {
        $template = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);
        $this->assertTrue(SmsTemplateResource::hasPage('create'));

        $this->actingAs($this->user('no_access', 'admin'));
        $this->get(SmsTemplateResource::getUrl('index'))->assertForbidden();

        foreach (['ViewAny', 'Update'] as $action) {
            Permission::findOrCreate("{$action}:SmsTemplate", 'web');
        }
        Role::findByName('registrar_officer')->givePermissionTo('ViewAny:SmsTemplate');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // AuthenticateSession ties the session to one user; start a new one.
        $this->flushSession();
        $this->actingAs($this->user('read_only', 'admin', ['registrar_officer']));
        $this->get(SmsTemplateResource::getUrl('index'))->assertOk();
        $this->get(SmsTemplateResource::getUrl('edit', ['record' => $template]))->assertForbidden();
    }

    public function test_preview_and_restore_defaults(): void
    {
        $this->actingAs($this->admin);
        $template = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);
        $template->update(['body' => 'Changed {{ code }}']);

        Livewire::test(EditSmsTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview');

        Livewire::test(EditSmsTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('resetDefaults')
            ->assertSchemaStateSet(['body' => SmsTemplate::defaults(SmsTemplate::RESET_PASSWORD_OTP)['body']]);
    }

    public function test_the_custom_variables_section_lists_the_built_in_variables_with_samples(): void
    {
        $this->actingAs($this->admin);
        $template = SmsTemplate::for(SmsTemplate::TEMPLATES[0]);

        $response = $this->get(SmsTemplateResource::getUrl('edit', ['record' => $template]))->assertOk()
            ->assertSee(__('sms_templates.built_in.heading'));

        foreach (SmsTemplate::VARIABLES[$template->key] as $name) {
            $response->assertSee('&#123;&#123; '.$name.' &#125;&#125;', false)
                ->assertSee(__('sms_templates.built_in.meanings.'.$name))
                ->assertSee(__('sms_templates.built_in.samples.'.$name));
        }
    }

    public function test_sms_text_has_an_insert_variable_dropdown_with_custom_variables(): void
    {
        $this->actingAs($this->admin);
        $template = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);
        $template->update(['custom_variables' => [['name' => 'hotline', 'value' => '023 123 456']]]);

        $response = $this->get(SmsTemplateResource::getUrl('edit', ['record' => $template]))->assertOk();

        // The dropdown targets the textarea with this id.
        $response->assertSee('id="sms-body"', false)
            ->assertSee('data-insert-variable="sms-body"', false);

        foreach ([...SmsTemplate::VARIABLES[SmsTemplate::RESET_PASSWORD_OTP], 'hotline'] as $name) {
            $response->assertSee('insert(\'{{ '.$name.' }}\')', false);
        }

        // A new custom variable shows in the dropdown before saving.
        Livewire::test(EditSmsTemplate::class, ['record' => $template->getRouteKey()])
            ->set('data.custom_variables', ['a' => ['name' => 'website', 'value' => 'uhs.edu.kh']])
            ->assertSee('insert(\'{{ website }}\')', false);
    }

    private function user(string $username, string $registrationType, array $roles = [], string $locale = 'en'): User
    {
        $user = User::query()->forceCreate([
            'registration_type' => $registrationType,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => $locale,
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }
}
