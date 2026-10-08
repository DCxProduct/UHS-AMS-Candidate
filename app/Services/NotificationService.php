<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\MultiChannelNotification;
use App\Support\CandidateDisplayName;
use App\Support\NotificationLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

class NotificationService
{
    /**
     * Send a notification to a specific user (User or SystemUser) across configured channels.
     *
     * @param array{
     *     template_code?: string,
     *     template_id?: int,
     *     subject?: string,
     *     title?: string,
     *     message?: string,
     *     body?: string,
     *     channels?: array<int, string>,
     *     stage_name?: string,
     *     notes?: string,
     *     review_note?: string,
     *     form_name?: string,
     *     action_url?: string|null,
     *     action_text?: string|null,
     *     action_label?: string|null,
     *     action_type?: string|null,
     *     icon?: string|null,
     *     color?: string|null,
     *     recipient_email?: string|null,
     *     locale?: string|null,
     *     variables?: array<string, mixed>,
     *     metadata?: array<string, mixed>,
     * } $payload
     */
    public function sendToUser(Model $recipient, array $payload): NotificationLog
    {
        $template = $this->resolveTemplate($payload);
        $locale = $this->resolveLocale($recipient, $payload);
        $variables = $this->buildVariables($recipient, $payload);

        $subject = $this->resolveSubject($template, $payload, $locale, $variables);
        $content = $this->resolveContent($template, $payload, $locale, $variables);

        $channels = $this->resolveChannels($template, $payload);
        $actionType = (string) ($payload['action_type'] ?? $template?->code ?? 'NOTIFICATION');
        $recipientEmail = (string) ($payload['recipient_email'] ?? $recipient->email ?? '');
        $recipientEmail = filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) ? $recipientEmail : null;

        $metadata = array_merge((array) ($payload['metadata'] ?? []), [
            'template_code' => $template?->code,
            'template_id' => $template?->id,
            'locale' => $locale,
            'variables' => $variables,
        ]);

        $notificationData = [
            'title' => $subject,
            'subject' => $subject,
            'message' => $content,
            'body' => $content,
            'icon' => $payload['icon'] ?? 'heroicon-o-bell',
            'color' => $payload['color'] ?? 'primary',
            'action_url' => $payload['action_url'] ?? null,
            'action_text' => $payload['action_text'] ?? $payload['action_label'] ?? null,
            'channels' => $channels,
            'recipient_email' => $recipientEmail,
            'metadata' => $metadata,
        ];

        $dispatchStatus = 'queued';
        try {
            if (method_exists($recipient, 'notify')) {
                $recipient->notify(new MultiChannelNotification($notificationData));
            }
        } catch (Throwable $e) {
            report($e);
            $dispatchStatus = 'failed';
            $metadata['error'] = $e->getMessage();
        }

        return $this->recordLogs(
            recipient: $recipient,
            template: $template,
            actionType: $actionType,
            channels: $channels,
            recipientEmail: $recipientEmail,
            subject: $subject,
            content: $content,
            status: $dispatchStatus,
            metadata: $metadata,
        );
    }

    /**
     * Broadcast a notification across multiple users (e.g. all administrators).
     *
     * @param  Collection<int, Model>|array<int, Model>  $recipients
     * @return array<int, NotificationLog>
     */
    public function broadcast(Collection|array $recipients, array $payload): array
    {
        $logs = [];

        foreach ($recipients as $recipient) {
            if ($recipient instanceof Model) {
                $logs[] = $this->sendToUser($recipient, $payload);
            }
        }

        return $logs;
    }

    /**
     * Resolve the notification template if specified in the payload.
     */
    protected function resolveTemplate(array $payload): ?NotificationTemplate
    {
        if (! empty($payload['template_code'])) {
            return NotificationTemplate::query()
                ->where('code', $payload['template_code'])
                ->where('is_active', true)
                ->first();
        }

        if (! empty($payload['template_id'])) {
            return NotificationTemplate::query()
                ->where('id', $payload['template_id'])
                ->where('is_active', true)
                ->first();
        }

        return null;
    }

    /**
     * Detect recipient locale, falling back to English if Khmer is not set.
     */
    protected function resolveLocale(Model $recipient, array $payload): string
    {
        if (! empty($payload['locale']) && in_array($payload['locale'], ['km', 'en'], true)) {
            return (string) $payload['locale'];
        }

        if (! empty($recipient->locale) && in_array($recipient->locale, ['km', 'en'], true)) {
            return (string) $recipient->locale;
        }

        if ($recipient instanceof User) {
            return NotificationLanguage::localeForUser($recipient);
        }

        $appLocale = app()->getLocale();

        return in_array($appLocale, ['km', 'en'], true) ? $appLocale : 'en';
    }

    /**
     * Prepare placeholder replacement dictionary.
     */
    protected function buildVariables(Model $recipient, array $payload): array
    {
        $recipientName = $recipient instanceof User
            ? CandidateDisplayName::for($recipient)
            : ($recipient->name ?? $recipient->username ?? 'User');

        return array_merge([
            'candidate_name' => $recipientName,
            'user_name' => $recipientName,
            'stage_name' => $payload['stage_name'] ?? '',
            'notes' => $payload['notes'] ?? $payload['review_note'] ?? '',
            'form_name' => $payload['form_name'] ?? '',
            'app_name' => (string) config('app.name', 'UHS-AMS'),
        ], (array) ($payload['variables'] ?? []));
    }

    /**
     * Resolve final localized and interpolated subject.
     */
    protected function resolveSubject(?NotificationTemplate $template, array $payload, string $locale, array $variables): string
    {
        $rawSubject = '';

        if ($template !== null) {
            $rawSubject = $template->getSubject($locale);
        }

        if (blank($rawSubject)) {
            $rawSubject = (string) ($payload['subject'] ?? $payload['title'] ?? 'Notification');
        }

        return $this->interpolate($rawSubject, $variables);
    }

    /**
     * Resolve final localized and interpolated content/body.
     */
    protected function resolveContent(?NotificationTemplate $template, array $payload, string $locale, array $variables): string
    {
        $rawContent = '';

        if ($template !== null) {
            $rawContent = $template->getBody($locale);
        }

        if (blank($rawContent)) {
            $rawContent = (string) ($payload['message'] ?? $payload['body'] ?? '');
        }

        return $this->interpolate($rawContent, $variables);
    }

    /**
     * Replace placeholders like {candidate_name}, {stage_name}, {notes}.
     */
    protected function interpolate(string $text, array $variables): string
    {
        return (string) preg_replace_callback('/\{\s*([a-zA-Z0-9_.-]+)\s*\}/', function ($matches) use ($variables) {
            $key = $matches[1];

            if (array_key_exists($key, $variables) && ! is_array($variables[$key])) {
                return (string) $variables[$key];
            }

            return $matches[0];
        }, $text);
    }

    /**
     * Resolve delivery channels from payload or template.
     *
     * @return array<int, string>
     */
    protected function resolveChannels(?NotificationTemplate $template, array $payload): array
    {
        $channels = $payload['channels'] ?? $template?->channels ?? ['database', 'mail'];

        if (! is_array($channels)) {
            $channels = ['database', 'mail'];
        }

        return array_values(array_unique(array_filter(array_map('strtolower', $channels))));
    }

    /**
     * Record audit logs for each target delivery channel.
     */
    protected function recordLogs(
        Model $recipient,
        ?NotificationTemplate $template,
        string $actionType,
        array $channels,
        ?string $recipientEmail,
        string $subject,
        string $content,
        string $status,
        array $metadata
    ): NotificationLog {
        $primaryLog = null;

        if (empty($channels)) {
            $channels = ['database'];
        }

        foreach ($channels as $channel) {
            $channelStatus = $status;

            if ($channel === 'mail' && blank($recipientEmail)) {
                $channelStatus = 'failed';
                $metadata['mail_error'] = 'Recipient does not have a valid email address.';
            }

            $log = NotificationLog::create([
                'recipient_type' => $recipient->getMorphClass(),
                'recipient_id' => $recipient->getKey(),
                'template_id' => $template?->id,
                'action_type' => $actionType,
                'channel' => $channel,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'content' => $content,
                'status' => $channelStatus,
                'metadata' => $metadata,
                'sent_at' => ($channelStatus === 'queued' || $channelStatus === 'sent') ? now() : null,
            ]);

            $primaryLog ??= $log;
        }

        return $primaryLog;
    }
}
