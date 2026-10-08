<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Admin\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmailTemplateResourceTest extends TestCase
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

    public function test_the_reset_email_uses_the_default_text_before_any_edit(): void
    {
        $message = (new ResetPassword('token-123'))->toMail($this->user('candidate', 'student', locale: 'en'));
        $html = (string) $message->render();

        $this->assertSame('Reset your password', $message->subject);
        $this->assertStringContainsString('You are receiving this email because we received a password reset request for your account.', $html);
        $this->assertStringContainsString('reset-password/token-123', $html);
        $this->assertStringContainsString('expire in 60 minutes', $html);
        $this->assertStringContainsString('UHS-AMS', $html);
        $this->assertStringNotContainsString('laravel.com/img', $html);
    }

    public function test_the_designed_body_and_variables_are_used_in_the_candidates_language(): void
    {
        EmailTemplate::for(EmailTemplate::RESET_PASSWORD)->update([
            'header_title' => 'UHS Admission',
            'subject_en' => 'Password help for {{ name }}',
            'button_en' => 'Choose a new password',
            'body_en' => '<h1 style="color: red;">Hi {{ name }}</h1><p>{{ reset_button }}</p><p>Valid for {{ minutes }} minutes. {{ unknown }}</p>',
            'subject_km' => 'ពាក្យសម្ងាត់ {{ name }}',
        ]);

        $english = (new ResetPassword('t'))->toMail($this->user('dara', 'student', locale: 'en'));
        $html = (string) $english->render();
        $this->assertSame('Password help for dara', $english->subject);
        $this->assertStringContainsString('<h1 style="color: red;">Hi dara</h1>', $html);
        $this->assertStringContainsString('Choose a new password</a>', $html);
        $this->assertStringContainsString('reset-password/t', $html);
        $this->assertStringContainsString('Valid for 60 minutes. {{ unknown }}', $html);
        $this->assertStringContainsString('UHS Admission', $html);

        $khmer = (new ResetPassword('t'))->toMail($this->user('sokha', 'student', locale: 'km'));
        $this->assertSame('ពាក្យសម្ងាត់ sokha', $khmer->subject);
    }

    public function test_custom_variables_are_filled_in_each_language(): void
    {
        EmailTemplate::for(EmailTemplate::RESET_PASSWORD)->update([
            'custom_variables' => [
                ['name' => 'hotline', 'value_en' => '023 123 456', 'value_km' => '០២៣ ១២៣ ៤៥៦'],
                ['name' => 'website', 'value_en' => 'uhs.edu.kh', 'value_km' => null],
            ],
            'subject_en' => 'Call {{ hotline }}',
            'body_en' => '<p>Call {{ hotline }} or visit {{ website }}.</p>',
            'body_km' => '<p>ទូរស័ព្ទ {{ hotline }} ឬ {{ website }}</p>',
        ]);

        $english = (new ResetPassword('t'))->toMail($this->user('dara', 'student', locale: 'en'));
        $this->assertSame('Call 023 123 456', $english->subject);
        $this->assertStringContainsString('Call 023 123 456 or visit uhs.edu.kh.', (string) $english->render());

        $khmer = (string) (new ResetPassword('t'))->toMail($this->user('sokha', 'student', locale: 'km'))->render();
        $this->assertStringContainsString('ទូរស័ព្ទ ០២៣ ១២៣ ៤៥៦ ឬ uhs.edu.kh', $khmer);
    }

    public function test_admin_can_create_custom_variables_and_they_appear_in_insert_variable(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->set('data.custom_variables', ['a' => ['name' => 'hotline', 'value_en' => '023 123 456', 'value_km' => '']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('023 123 456', $template->refresh()->customVariables('km')['hotline']);
        $this->assertContains('hotline', $template->variableNames());

        $this->get(EmailTemplateResource::getUrl('edit', ['record' => $template]))
            ->assertOk()
            ->assertSee('hotline', false);
    }

    public function test_custom_variable_names_are_checked(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        foreach (['Hot Line', '1phone', 'name'] as $badName) {
            Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
                ->set('data.custom_variables', ['a' => ['name' => $badName, 'value_en' => 'x']])
                ->call('save')
                ->assertHasFormErrors(['custom_variables.a.name']);
        }

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->set('data.custom_variables', [
                'a' => ['name' => 'hotline', 'value_en' => 'x'],
                'b' => ['name' => 'hotline', 'value_en' => 'y'],
            ])
            ->call('save')
            ->assertHasFormErrors(['custom_variables.a.name']);
    }

    public function test_admin_can_upload_a_logo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 200, 80)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = $template->refresh()->logo_path;
        $this->assertStringStartsWith('email-templates/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_logo_is_embedded_in_the_sent_email_and_shown_in_the_preview(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('email-templates/logo.png', UploadedFile::fake()->image('logo.png', 200, 80)->getContent());
        EmailTemplate::for(EmailTemplate::RESET_PASSWORD)->update(['logo_path' => 'email-templates/logo.png']);
        $user = $this->user('candidate', 'student', locale: 'en');

        // Preview: the embedded image is shown inline.
        $this->assertStringContainsString('src="data:image/png;base64,', (string) (new ResetPassword('t'))->toMail($user)->render());

        // Real email: the image travels inside the email, not as a link to this server.
        $user->notify(new ResetPassword('t'));
        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertStringContainsString('src="cid:', $sent->getHtmlBody());
        $this->assertCount(1, $sent->getAttachments());
    }

    public function test_no_logo_means_no_image(): void
    {
        $html = (string) (new ResetPassword('t'))->toMail($this->user('candidate', 'student', locale: 'en'))->render();

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_variable_values_are_escaped_in_the_body(): void
    {
        EmailTemplate::for(EmailTemplate::RESET_PASSWORD)->update(['body_en' => '<p>Hi {{ name }}</p>']);
        $user = $this->user('candidate', 'student', locale: 'en');
        $user->forceFill(['name' => '<script>alert(1)</script>'])->save();

        $html = (string) (new ResetPassword('t'))->toMail($user)->render();

        $this->assertStringContainsString('Hi &lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_an_empty_field_falls_back_to_the_default(): void
    {
        EmailTemplate::for(EmailTemplate::RESET_PASSWORD)->update(['body_en' => null, 'button_en' => null]);

        $html = (string) (new ResetPassword('t'))->toMail($this->user('candidate', 'student', locale: 'en'))->render();

        $this->assertStringContainsString('You are receiving this email because we received a password reset request for your account.', $html);
        $this->assertStringContainsString('Reset Password</a>', $html);
    }

    public function test_admin_can_list_and_edit_templates_but_not_create_them(): void
    {
        $this->actingAs($this->admin);

        $this->get(EmailTemplateResource::getUrl('index'))->assertOk()->assertSee(__('email_templates.templates.reset_password'));
        $template = EmailTemplate::query()->where('key', EmailTemplate::RESET_PASSWORD)->sole();

        $this->get(EmailTemplateResource::getUrl('edit', ['record' => $template]))
            ->assertOk()
            ->assertSee('insert_variable', false)
            ->assertSee('reset_button', false);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->assertSchemaStateSet(['subject_en' => 'Reset your password', 'header_title' => 'UHS-AMS'])
            ->fillForm(['subject_en' => 'New subject', 'body_km' => '<p>ជម្រាបសួរ {{ name }}</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New subject', $template->refresh()->subject_en);
        $this->assertSame('<p>ជម្រាបសួរ {{ name }}</p>', $template->body_km);
        $this->assertFalse(EmailTemplateResource::hasPage('create'));
    }

    public function test_subject_and_button_are_required(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->fillForm(['subject_en' => '', 'button_km' => ''])
            ->call('save')
            ->assertHasFormErrors(['subject_en' => 'required', 'button_km' => 'required']);
    }

    public function test_access_follows_role_permissions(): void
    {
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);

        $this->actingAs($this->user('no_access', 'admin'));
        $this->get(EmailTemplateResource::getUrl('index'))->assertForbidden();

        foreach (['ViewAny', 'Update'] as $action) {
            Permission::findOrCreate("{$action}:EmailTemplate", 'web');
        }
        Role::findByName('registrar_officer')->givePermissionTo('ViewAny:EmailTemplate');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // AuthenticateSession ties the session to one user; start a new one.
        $this->flushSession();
        $this->actingAs($this->user('read_only', 'admin', ['registrar_officer']));
        $this->get(EmailTemplateResource::getUrl('index'))->assertOk();
        $this->get(EmailTemplateResource::getUrl('edit', ['record' => $template]))->assertForbidden();
    }

    public function test_send_test_email_uses_the_saved_text(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);
        $template->update(['subject_en' => 'Test subject']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('sendTest', ['to' => 'developer@example.test', 'locale' => 'en'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('email_templates.send_test.sent', ['email' => 'developer@example.test']));

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('developer@example.test', $sent->getTo()[0]->getAddress());
        $this->assertSame('Test subject', $sent->getSubject());
    }

    public function test_preview_and_restore_defaults(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);
        $template->update(['subject_en' => 'Changed']);

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->mountAction('preview')
            ->assertActionMounted('preview');

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('resetDefaults')
            ->assertSchemaStateSet(['subject_en' => 'Reset your password']);

        $this->assertSame('Reset your password', $template->refresh()->subject_en);
    }

    public function test_the_custom_variables_section_lists_the_built_in_variables_with_samples(): void
    {
        $this->actingAs($this->admin);
        $template = EmailTemplate::for(EmailTemplate::TEMPLATES[0]);

        $response = $this->get(EmailTemplateResource::getUrl('edit', ['record' => $template]))->assertOk()
            ->assertSee(__('email_templates.built_in.heading'));

        foreach (EmailTemplate::VARIABLES[$template->key] as $name) {
            $response->assertSee('&#123;&#123; '.$name.' &#125;&#125;', false)
                ->assertSee(__('email_templates.built_in.meanings.'.$name))
                ->assertSee(__('email_templates.built_in.samples.'.$name));
        }
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
