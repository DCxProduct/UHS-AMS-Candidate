<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PlasGateSms;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PlasGateSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.plasgate.private_key' => 'test-private-key',
            'services.plasgate.secret' => 'test-secret',
            'services.plasgate.sender' => 'UHS',
            'services.plasgate.test_phone' => null,
        ]);
    }

    public function test_phone_numbers_are_sent_in_international_form(): void
    {
        $this->assertSame('85512345678', PlasGateSms::normalizePhone('012 345 678'));
        $this->assertSame('85512345678', PlasGateSms::normalizePhone('+855 12 345 678'));
        $this->assertSame('85512345678', PlasGateSms::normalizePhone('855012345678'));
        $this->assertSame('855961234567', PlasGateSms::normalizePhone('0961234567'));
        $this->assertNull(PlasGateSms::normalizePhone(''));
        $this->assertNull(PlasGateSms::normalizePhone('123'));
    }

    public function test_a_message_is_posted_to_plasgate_with_the_key_and_secret(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);

        $this->assertTrue(PlasGateSms::send('012345678', 'Hello'));

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://cloudapi.plasgate.com/rest/batch-send?')
            && str_contains($request->url(), 'private_key=test-private-key')
            && $request->method() === 'POST'
            && $request->hasHeader('X-Secret', 'test-secret')
            && $request['globals']['sender'] === 'UHS'
            && $request['messages'][0]['to'][0] === '85512345678'
            && $request['messages'][0]['content'] === 'Hello');
    }

    public function test_nothing_is_sent_until_plasgate_is_configured(): void
    {
        Http::fake();
        config(['services.plasgate.private_key' => null]);

        $this->assertFalse(PlasGateSms::send('012345678', 'Hello'));
        Http::assertNothingSent();
    }

    public function test_a_rejected_message_throws(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['error' => 'Invalid key'], 401)]);

        $this->expectException(RuntimeException::class);
        PlasGateSms::send('012345678', 'Hello');
    }

    public function test_the_developer_test_phone_receives_every_sms(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);
        config(['services.plasgate.test_phone' => '099 888 777']);

        PlasGateSms::send('012345678', 'Hello');

        Http::assertSent(fn (Request $request): bool => $request['messages'][0]['to'][0] === '85599888777'
            && $request['messages'][0]['content'] === '[TEST -> 85512345678] Hello');
    }

    public function test_candidate_notifications_are_also_texted(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);
        $student = $this->candidate();

        Notification::make()->title('Application approved')->body('Please pay the fee.')->sendToDatabase($student);

        $this->assertSame(1, $student->notifications()->count());
        Http::assertSent(fn (Request $request): bool => $request['messages'][0]['to'][0] === '85512345678'
            && $request['messages'][0]['content'] === "UHS-AMS: Application approved\nPlease pay the fee.");
    }

    public function test_a_failed_sms_does_not_stop_the_notification(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response('Server error', 500)]);
        $student = $this->candidate();

        Notification::make()->title('Application approved')->sendToDatabase($student);

        $this->assertSame(1, $student->notifications()->count());
    }

    private function candidate(): User
    {
        return User::query()->forceCreate([
            'registration_type' => 'student',
            'name' => 'candidate',
            'username' => 'candidate',
            'email' => 'candidate@example.test',
            'phone' => '012345678',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => 'en',
        ]);
    }
}
