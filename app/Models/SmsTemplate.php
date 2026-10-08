<?php

namespace App\Models;

use App\Models\Concerns\HasTemplateText;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable system SMS, in English and Khmer: plain text with {{ variables }}.
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

    protected $fillable = [
        'key',
        'app_name',
        'custom_variables',
        'body_en', 'body_km',
    ];

    /**
     * Built-in texts. They match the SMS that was sent before templates existed.
     */
    public static function defaults(string $key): array
    {
        return match ($key) {
            self::RESET_PASSWORD_OTP => [
                'app_name' => 'UHS-AMS',
                'body_en' => '{{ app }}: Your password reset code is {{ code }}. It expires in {{ minutes }} minutes. Do not share this code.',
                'body_km' => '{{ app }}: លេខកូដកំណត់ពាក្យសម្ងាត់ថ្មីរបស់អ្នកគឺ {{ code }} ។ ផុតកំណត់ក្នុង {{ minutes }} នាទី។ សូមកុំចែករំលែកលេខកូដនេះ។',
            ],
            default => [],
        };
    }

    public function label(): string
    {
        return __('sms_templates.templates.'.$this->key);
    }

    public function appName(): string
    {
        return filled($this->app_name)
            ? (string) $this->app_name
            : (string) (self::defaults($this->key)['app_name'] ?? config('app.name'));
    }
}
