<?php

namespace App\Support;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds an email from a template an admin created, with the general
 * variables ({{ name }}, {{ email }}, {{ app }}) and custom variables.
 */
final class TemplateEmail
{
    public static function build(EmailTemplate $template, ?User $user): MailMessage
    {
        $variables = [
            ...$template->customVariables(),
            'name' => (string) ($user?->name ?? ''),
            'email' => (string) ($user?->email ?? ''),
            'app' => $template->headerTitle(),
        ];

        return (new MailMessage)
            ->subject($template->text('subject', $variables))
            ->view('emails.template', [
                'headerTitle' => $template->headerTitle(),
                'logoPath' => $template->logoFullPath(),
                'body' => $template->renderBody($variables),
            ]);
    }
}
