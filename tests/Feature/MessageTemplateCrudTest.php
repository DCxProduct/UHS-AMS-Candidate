<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Filament\Admin\Resources\SmsTemplates\Pages\CreateSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\Pages\EditSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\Pages\ListSmsTemplates;
use App\Models\EmailTemplate;
use App\Models\Role;
use App\Models\SmsTemplate;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessageTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web']);
        $this->admin = $this->user('admin_user', ['admin']);
        $this->actingAs($this->admin);
    }

    public function test_the_lists_have_a_create_button(): void
    {
        Livewire::test(ListEmailTemplates::class)->assertActionExists('create');
        Livewire::test(ListSmsTemplates::class)->assertActionExists('create');
    }

    public function test_an_email_template_can_be_created_previewed_and_deleted(): void
    {
        Livewire::test(CreateEmailTemplate::class)
            ->assertSchemaStateSet(['name' => null])
            ->fillForm([
                'name' => 'Interview invitation',
                'header_title' => 'UHS-AMS',
                'subject' => 'Interview for {{ name }}',
                'button' => 'Open',
                'body' => '<p>Hello {{ name }}, please come to {{ app }}.</p>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = EmailTemplate::query()->where('key', 'interview_invitation')->sole();
        $this->assertSame('Interview invitation', $template->label());
        $this->assertFalse($template->isBuiltIn());
        $this->assertSame(['name', 'email', 'app', 'form', 'stage', 'message', 'status'], EmailTemplate::builtInVariablesFor($template));

        Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('sendTest', ['to' => 'developer@example.test'])
            ->assertHasNoActionErrors()
            ->assertActionHidden('resetDefaults')
            ->callAction('delete');

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('Interview for admin_user', $sent->getSubject());
        $this->assertStringContainsString('Hello admin_user, please come to UHS-AMS.', $sent->getHtmlBody());
        $this->assertModelMissing($template);
    }

    public function test_an_sms_template_can_be_created_and_deleted_from_the_list(): void
    {
        Livewire::test(CreateSmsTemplate::class)
            ->fillForm([
                'name' => 'Exam reminder',
                'app_name' => 'UHS-AMS',
                'body' => '{{ app }}: Hi {{ name }}, your exam is tomorrow.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = SmsTemplate::query()->where('key', 'exam_reminder')->sole();
        $this->assertSame('Exam reminder', $template->label());

        Livewire::test(ListSmsTemplates::class)
            ->assertTableActionVisible('delete', $template)
            ->callTableAction('delete', $template);

        $this->assertModelMissing($template);
    }

    public function test_a_name_is_required_and_keys_stay_unique(): void
    {
        Livewire::test(CreateSmsTemplate::class)
            ->fillForm(['name' => '', 'app_name' => 'UHS-AMS', 'body' => 'Hi'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        foreach (['Exam reminder', 'Exam reminder'] as $name) {
            Livewire::test(CreateSmsTemplate::class)
                ->fillForm(['name' => $name, 'app_name' => 'UHS-AMS', 'body' => 'Hi'])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(['exam_reminder', 'exam_reminder_1'], SmsTemplate::query()->orderBy('id')->pluck('key')->all());
    }

    public function test_an_email_template_can_be_set_for_one_action_only(): void
    {
        $form = [
            'header_title' => 'UHS-AMS',
            'subject' => 'Hi',
            'button' => 'Open',
            'body' => '<p>{{ message }}</p>',
        ];

        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([...$form, 'name' => 'Accepted', 'action' => 'accept'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('accept', EmailTemplate::forAction('accept')?->action);
        Livewire::test(ListEmailTemplates::class)
            ->assertTableColumnStateSet('action', 'accept', EmailTemplate::forAction('accept'));

        Livewire::test(CreateEmailTemplate::class)
            ->fillForm([...$form, 'name' => 'Accepted again', 'action' => 'accept'])
            ->call('create')
            ->assertHasFormErrors(['action' => 'unique']);
    }

    public function test_an_sms_template_can_be_set_for_one_action_only(): void
    {
        Livewire::test(CreateSmsTemplate::class)
            ->fillForm(['name' => 'Accepted SMS', 'action' => 'accept', 'app_name' => 'UHS-AMS', 'body' => '{{ message }}'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('accept', SmsTemplate::forAction('accept')?->action);
        Livewire::test(ListSmsTemplates::class)
            ->assertTableColumnStateSet('action', 'accept', SmsTemplate::forAction('accept'));

        Livewire::test(CreateSmsTemplate::class)
            ->fillForm(['name' => 'Accepted again', 'action' => 'accept', 'app_name' => 'UHS-AMS', 'body' => 'Hi'])
            ->call('create')
            ->assertHasFormErrors(['action' => 'unique']);
    }

    public function test_built_in_templates_cannot_be_deleted(): void
    {
        $email = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);
        $sms = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);

        Livewire::test(ListEmailTemplates::class)->assertTableActionHidden('delete', $email);
        Livewire::test(ListSmsTemplates::class)->assertTableActionHidden('delete', $sms);
        Livewire::test(EditEmailTemplate::class, ['record' => $email->getRouteKey()])
            ->assertActionHidden('delete')
            ->assertActionVisible('resetDefaults')
            ->assertDontSee(__('email_templates.fields.template_name'));
        Livewire::test(EditSmsTemplate::class, ['record' => $sms->getRouteKey()])->assertActionHidden('delete');

        $this->assertFalse($this->user('staff', [])->can('delete', $email));
    }

    public function test_create_and_delete_follow_role_permissions(): void
    {
        foreach (['ViewAny', 'Create', 'Update', 'Delete'] as $action) {
            Permission::findOrCreate("{$action}:SmsTemplate", 'web');
        }
        Role::findByName('registrar_officer')->givePermissionTo(['ViewAny:SmsTemplate', 'Create:SmsTemplate']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $custom = SmsTemplate::query()->create(['key' => 'custom', 'name' => 'Custom', 'app_name' => 'UHS-AMS', 'body' => 'Hi']);
        $officer = $this->user('officer', ['registrar_officer']);

        $this->assertTrue($officer->can('create', SmsTemplate::class));
        $this->assertFalse($officer->can('delete', $custom));

        Role::findByName('registrar_officer')->givePermissionTo('Delete:SmsTemplate');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertTrue($officer->fresh()->can('delete', $custom));
    }

    private function user(string $username, array $roles): User
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

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        return $user;
    }
}
