<?php

namespace App\Support;

use App\Models\SmsTemplate;
use App\Models\User;

/**
 * The phone reset-password SMS text, from the admin-editable template.
 */
final class PasswordResetOtpSms
{
    public static function text(User $user, string $code, ?string $locale = null): string
    {
        $template = SmsTemplate::for(SmsTemplate::RESET_PASSWORD_OTP);
        $locale ??= NotificationLanguage::localeForUser($user);

        return trim($template->text('body', $locale, [
            ...$template->customVariables($locale),
            'name' => (string) ($user->name ?: $user->username),
            'phone' => (string) $user->phone,
            'code' => $code,
            'minutes' => PhonePasswordResetOtp::LIFETIME_MINUTES,
            'app' => $template->appName(),
        ]));
    }
}
