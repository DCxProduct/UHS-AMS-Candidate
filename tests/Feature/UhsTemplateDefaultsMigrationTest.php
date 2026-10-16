<?php

namespace Tests\Feature;

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UhsTemplateDefaultsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_default_texts_get_the_uhs_text_and_edited_ones_are_kept(): void
    {
        DB::table('email_templates')->insert([
            'key' => EmailTemplate::RESET_PASSWORD,
            'header_title' => 'UHS-AMS',
            'subject' => 'Reset your password',
            'button' => 'Reset Password',
            'body' => '<p>Our own edited text</p>',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sms_templates')->insert([
            'key' => SmsTemplate::RESET_PASSWORD_OTP,
            'app_name' => 'UHS-AMS',
            'body' => '{{ app }}: Your password reset code is {{ code }}. It expires in {{ minutes }} minutes. Do not share this code.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_10_16_000001_use_uhs_defaults_for_reset_password_templates.php'))->up();

        $email = DB::table('email_templates')->where('key', EmailTemplate::RESET_PASSWORD)->first();
        $newEmail = EmailTemplate::defaults(EmailTemplate::RESET_PASSWORD);
        $this->assertSame($newEmail['header_title'], $email->header_title);
        $this->assertSame($newEmail['subject'], $email->subject);
        $this->assertSame($newEmail['button'], $email->button);
        $this->assertSame('<p>Our own edited text</p>', $email->body);

        $sms = DB::table('sms_templates')->where('key', SmsTemplate::RESET_PASSWORD_OTP)->first();
        $newSms = SmsTemplate::defaults(SmsTemplate::RESET_PASSWORD_OTP);
        $this->assertSame($newSms['app_name'], $sms->app_name);
        $this->assertSame($newSms['body'], $sms->body);
    }
}
