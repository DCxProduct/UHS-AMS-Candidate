<?php

namespace App\Support;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds the reset-password email from the admin-designed template.
 */
final class ResetPasswordEmail
{
    /**
     * The template text is sent as written; $locale only sets the built-in
     * line under the button (the recipient's language by default).
     */
    public static function build(object $notifiable, string $url, ?string $locale = null): MailMessage
    {
        $template = EmailTemplate::for(EmailTemplate::RESET_PASSWORD);
        $locale ??= NotificationLanguage::localeForUser($notifiable instanceof User ? $notifiable : null);

        $variables = [
            ...$template->customVariables(),
            'name' => (string) ($notifiable->name ?? $notifiable->username ?? ''),
            'email' => (string) ($notifiable->email ?? ''),
            'minutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            'app' => $template->headerTitle(),
            'reset_url' => $url,
        ];

        $buttonText = $template->text('button', $variables);

        return (new MailMessage)
            ->subject($template->text('subject', $variables))
            ->view('emails.reset-password', [
                'headerTitle' => $template->headerTitle(),
                'logoPath' => $template->logoFullPath(),
                'body' => $template->renderBody($variables, [
                    'reset_button' => view('emails.partials.button', ['url' => $url, 'text' => $buttonText])->render(),
                ]),
                'subcopy' => __('email_templates.subcopy', ['button' => $buttonText], $locale),
                'url' => $url,
                'locale' => $locale,
            ]);
    }
}
