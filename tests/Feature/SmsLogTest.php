<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SmsLogs\Pages\ListSmsLogs;
use App\Filament\Admin\Resources\SmsLogs\SmsLogResource;
use App\Models\Role;
use App\Models\SmsLog;
use App\Models\User;
use App\Support\PlasGateSms;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SmsLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.plasgate.private_key' => 'key',
            'services.plasgate.secret' => 'secret',
            'services.plasgate.test_phone' => null,
        ]);
    }

    public function test_an_accepted_sms_is_recorded(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 7])]);
        $user = $this->user('dara', 'student');

        PlasGateSms::send('012 345 678', 'Your application was accepted.', ['source' => 'workflow', 'user_id' => $user->id]);

        $log = SmsLog::query()->sole();
        $this->assertSame('sent', $log->status);
        $this->assertSame('workflow', $log->source);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('012 345 678', $log->phone);
        $this->assertSame('85512345678', $log->sent_to);
        $this->assertSame('Your application was accepted.', $log->content);
        $this->assertStringContainsString('batchId', $log->response);
    }

    public function test_a_rejected_sms_is_recorded_as_failed(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['message' => '400 Invalid Sender'], 400)]);

        try {
            PlasGateSms::send('012345678', 'Hello');
            $this->fail('PlasGate rejection should throw.');
        } catch (RuntimeException) {
        }

        $log = SmsLog::query()->sole();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Invalid Sender', $log->response);
    }

    public function test_the_test_phone_is_shown_as_the_sent_to_number(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 7])]);
        config(['services.plasgate.test_phone' => '015916217']);

        PlasGateSms::send('012345678', 'Hello');

        $log = SmsLog::query()->sole();
        $this->assertSame('85515916217', $log->sent_to);
        $this->assertSame('[TEST -> 85512345678] Hello', $log->content);
    }

    public function test_password_reset_codes_are_hidden_in_the_history(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 7])]);
        $this->user('dara', 'student', '012345678');

        $this->post(route('student.password.phone'), ['phone' => '012345678']);

        $log = SmsLog::query()->sole();
        $this->assertSame('password_reset', $log->source);
        $this->assertStringContainsString('******', $log->content);
        $this->assertDoesNotMatchRegularExpression('/\b\d{6}\b/', $log->content);
        Http::assertSent(fn ($request): bool => preg_match('/\b\d{6}\b/', $request['messages'][0]['content']) === 1);
    }

    public function test_nothing_is_recorded_when_plasgate_is_not_set_up(): void
    {
        config(['services.plasgate.private_key' => null]);

        PlasGateSms::send('012345678', 'Hello');

        $this->assertSame(0, SmsLog::query()->count());
    }

    public function test_admin_sees_the_history_and_others_need_permission(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web']);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 7])]);
        PlasGateSms::send('012345678', 'History check message');

        $admin = $this->user('admin_user', 'admin');
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $this->get(SmsLogResource::getUrl('index'))->assertOk()->assertSee('History check message');
        Livewire::test(ListSmsLogs::class)
            ->assertCanSeeTableRecords(SmsLog::all())
            ->mountTableAction('details', SmsLog::query()->sole())
            ->assertSee('History check message');
        $this->assertFalse(SmsLogResource::canCreate());

        $this->flushSession();
        $this->actingAs($this->user('no_access', 'admin'));
        $this->get(SmsLogResource::getUrl('index'))->assertForbidden();

        Permission::findOrCreate('ViewAny:SmsLog', 'web');
        Role::findByName('registrar_officer')->givePermissionTo('ViewAny:SmsLog');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->flushSession();
        $reader = $this->user('reader', 'admin');
        $reader->assignRole('registrar_officer');
        $this->actingAs($reader);
        $this->get(SmsLogResource::getUrl('index'))->assertOk();
    }

    private function user(string $username, string $registrationType, ?string $phone = null): User
    {
        return User::query()->forceCreate([
            'registration_type' => $registrationType,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'phone' => $phone,
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => 'en',
        ]);
    }
}
