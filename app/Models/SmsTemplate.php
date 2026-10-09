<?php

namespace App\Models;

use App\Models\Concerns\HasTemplateText;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable system SMS: plain text with {{ variables }}, written in Khmer
 * or English and sent to everyone as written.
 * Empty fields fall back to the defaults below.
 */
class SmsTemplate extends Model
{
    use HasTemplateText;

    public const RESET_PASSWORD_OTP = 'reset_password_otp';

    public const TEMPLATES = [self::RESET_PASSWORD_OTP];

    /** Variables offered for each SMS. */
    public const VARIABLES = [
        self::RESET_PASSWORD_OTP => ['name', 'phone', 'code', 'minutes', 'app'],
    ];

    /** Variables offered by templates an admin creates. */
    public const GENERAL_VARIABLES = ['name', 'phone', 'app'];

    public const LANG_FILE = 'sms_templates';

    protected $fillable = [
        'key',
        'name',
        'app_name',
        'custom_variables',
        'body',
    ];

    /**
     * Built-in texts. They match the SMS that was sent before templates existed.
     */
    public static function defaults(string $key): array
    {
        return match ($key) {
            self::RESET_PASSWORD_OTP => [
                'app_name' => 'UHS-AMS',
                'body' => '{{ app }}: Your password reset code is {{ code }}. It expires in {{ minutes }} minutes. Do not share this code.',
            ],
            default => [],
        };
    }

    public function appName(): string
    {
        return filled($this->app_name)
            ? (string) $this->app_name
            : (string) (self::defaults($this->key)['app_name'] ?? config('app.name'));
    }
}
