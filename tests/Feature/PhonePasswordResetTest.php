<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PhonePasswordResetOtp;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class PhonePasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.plasgate.private_key' => 'test-private-key',
            'services.plasgate.secret' => 'test-secret',
            'services.plasgate.test_phone' => null,
        ]);

        $this->user = User::query()->forceCreate([
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

    public function test_forgot_page_offers_email_and_phone(): void
    {
        $this->get(route('student.password.request'))
            ->assertOk()
            ->assertSee(route('student.password.email'), false)
            ->assertDontSee(route('student.password.phone'), false);

        $this->get(route('student.password.request', ['method' => 'phone']))
            ->assertOk()
            ->assertSee(route('student.password.phone'), false)
            ->assertSee('name="phone"', false);
    }

    public function test_the_email_reset_link_still_works_as_before(): void
    {
        Notification::fake();

        $this->post(route('student.password.email'), ['email' => 'candidate@example.test'])
            ->assertSessionHas('status', __('app.password_reset_link_sent'));

        Notification::assertSentTo($this->user, ResetPassword::class);
    }

    public function test_a_code_is_sent_by_sms_and_resets_the_password_once(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);

        $this->post(route('student.password.phone'), ['phone' => '+855 12 345 678'])
            ->assertRedirect(route('student.password.phone.verify', ['phone' => '+855 12 345 678']))
            ->assertSessionHas('status', __('app.reset_otp_sent'));

        $code = $this->sentCode();

        $this->get(route('student.password.phone.verify', ['phone' => '+855 12 345 678']))
            ->assertOk()
            ->assertSee('name="code"', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('data-password-toggle="password_confirmation"', false);

        $this->post(route('student.password.phone.update'), $this->resetData($code))
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('NewPassword1!', $this->user->refresh()->password));

        // The code is used up.
        $this->from(route('student.password.phone.verify'))
            ->post(route('student.password.phone.update'), $this->resetData($code, 'Another123!'))
            ->assertSessionHasErrors('code');
    }

    public function test_a_wrong_code_is_rejected_and_too_many_tries_cancel_the_code(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);
        $this->post(route('student.password.phone'), ['phone' => '012345678']);
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, PhonePasswordResetOtp::MAX_ATTEMPTS) as $attempt) {
            $this->from(route('student.password.phone.verify'))
                ->post(route('student.password.phone.update'), $this->resetData($wrong))
                ->assertSessionHasErrors('code');
        }

        $this->from(route('student.password.phone.verify'))
            ->post(route('student.password.phone.update'), $this->resetData($code))
            ->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password', $this->user->refresh()->password));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);
        $this->post(route('student.password.phone'), ['phone' => '012345678']);
        $code = $this->sentCode();

        $this->travel(PhonePasswordResetOtp::LIFETIME_MINUTES + 1)->minutes();

        $this->from(route('student.password.phone.verify'))
            ->post(route('student.password.phone.update'), $this->resetData($code))
            ->assertSessionHasErrors('code');
    }

    public function test_a_new_code_cannot_be_requested_within_a_minute(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);

        $this->post(route('student.password.phone'), ['phone' => '012345678']);
        $this->from(route('student.password.request', ['method' => 'phone']))
            ->post(route('student.password.phone'), ['phone' => '012345678'])
            ->assertSessionHasErrors('phone');

        Http::assertSentCount(1);
    }

    public function test_unknown_phone_gets_an_error(): void
    {
        Http::fake();

        $this->from(route('student.password.request', ['method' => 'phone']))
            ->post(route('student.password.phone'), ['phone' => '099999999'])
            ->assertSessionHasErrors(['phone' => __('app.reset_otp_account_not_found')]);

        Http::assertNothingSent();
    }

    public function test_phone_reset_is_unavailable_until_plasgate_is_configured(): void
    {
        Http::fake();
        config(['services.plasgate.private_key' => null]);

        $this->from(route('student.password.request', ['method' => 'phone']))
            ->post(route('student.password.phone'), ['phone' => '012345678'])
            ->assertSessionHasErrors(['phone' => __('app.reset_otp_unavailable')]);

        Http::assertNothingSent();
    }

    public function test_a_failed_sms_shows_an_error_and_keeps_no_code(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response('Server error', 500)]);

        $this->from(route('student.password.request', ['method' => 'phone']))
            ->post(route('student.password.phone'), ['phone' => '012345678'])
            ->assertSessionHasErrors(['phone' => __('app.reset_otp_failed')]);

        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_phone_codes_do_not_touch_email_reset_links(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['queue_id' => 'abc'])]);
        DB::table('password_reset_tokens')->insert([
            'email' => 'candidate@example.test',
            'token' => Hash::make('email-link-token'),
            'created_at' => now(),
        ]);

        $this->post(route('student.password.phone'), ['phone' => '012345678']);
        $this->post(route('student.password.phone.update'), $this->resetData($this->sentCode()));

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'candidate@example.test']);
    }

    public function test_the_code_is_logged_for_local_testing(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        Log::spy();

        $this->post(route('student.password.phone'), ['phone' => '012345678']);

        Log::shouldHaveReceived('info')->withArgs(fn (string $message): bool => str_contains($message, $this->sentCode()))->once();
    }

    public function test_the_code_is_never_logged_in_production(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        $this->app['env'] = 'production';
        Log::spy();

        $this->post(route('student.password.phone'), ['phone' => '012345678']);

        Log::shouldNotHaveReceived('info', [Mockery::on(fn ($message): bool => str_contains((string) $message, 'Password reset code'))]);
    }

    private function sentCode(): string
    {
        $request = Http::recorded()->last()[0];
        preg_match('/\b(\d{6})\b/', (string) $request['messages'][0]['content'], $matches);

        $this->assertSame('85512345678', $request['messages'][0]['to'][0]);

        return $matches[1];
    }

    private function resetData(string $code, string $password = 'NewPassword1!'): array
    {
        return [
            'phone' => '012 345 678',
            'code' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
