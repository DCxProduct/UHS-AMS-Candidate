<?php

use App\Models\EmailTemplate;
use App\Models\SmsTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The reset-password texts before the UHS defaults. A field still holding one of
     * these (or empty) gets the new UHS text; a field an admin changed is kept.
     */
    private const OLD_EMAIL = [
        'header_title' => 'UHS-AMS',
        'subject' => 'Reset your password',
        'button' => 'Reset Password',
        'body' => '<h2>Hello!</h2>'
            .'<p>You are receiving this email because we received a password reset request for your account.</p>'
            .'<p style="text-align: center;">{{ reset_button }}</p>'
            .'<p>This password reset link will expire in {{ minutes }} minutes.</p>'
            .'<p>If you did not request a password reset, no further action is required.</p>'
            .'<p>Regards,<br>{{ app }}</p>',
    ];

    private const OLD_SMS = [
        'app_name' => 'UHS-AMS',
        'body' => '{{ app }}: Your password reset code is {{ code }}. It expires in {{ minutes }} minutes. Do not share this code.',
    ];

    public function up(): void
    {
        $this->refresh('email_templates', EmailTemplate::RESET_PASSWORD, self::OLD_EMAIL, EmailTemplate::defaults(EmailTemplate::RESET_PASSWORD));
        $this->refresh('sms_templates', SmsTemplate::RESET_PASSWORD_OTP, self::OLD_SMS, SmsTemplate::defaults(SmsTemplate::RESET_PASSWORD_OTP));
    }

    public function down(): void
    {
        // The UHS texts are kept; they can be edited in the template menus.
    }

    private function refresh(string $table, string $key, array $old, array $new): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $row = DB::table($table)->where('key', $key)->first();

        if (! $row) {
            return;
        }

        $updates = [];

        foreach ($old as $column => $oldValue) {
            $current = $row->{$column} ?? null;

            if (blank($current) || $current === $oldValue) {
                $updates[$column] = $new[$column];
            }
        }

        if ($updates !== []) {
            DB::table($table)->where('id', $row->id)->update([...$updates, 'updated_at' => now()]);
        }
    }
};
