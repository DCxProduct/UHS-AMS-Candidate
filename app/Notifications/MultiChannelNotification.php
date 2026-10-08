<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MultiChannelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public array $data;

    /**
     * Create a new notification instance.
     *
     * @param array{
     *     title: string,
     *     message?: string,
     *     body?: string,
     *     subject?: string|null,
     *     icon?: string,
     *     color?: string,
     *     iconColor?: string,
     *     action_url?: string|null,
     *     action_text?: string|null,
     *     action_label?: string|null,
     *     channels?: array<int, string>,
     *     recipient_email?: string|null,
     *     metadata?: array<string, mixed>,
     * } $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $configuredChannels = (array) ($this->data['channels'] ?? ['database', 'mail']);
        $channels = [];

        foreach ($configuredChannels as $channel) {
            $normalized = strtolower(trim((string) $channel));

            if ($normalized === 'database') {
                $channels[] = 'database';
            } elseif ($normalized === 'mail') {
                $email = $this->data['recipient_email'] ?? $notifiable->email ?? null;

                if (! empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $channels[] = 'mail';
                }
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $subject = $this->data['subject'] ?? $this->data['title'] ?? ('Notification - '.config('app.name', 'UHS-AMS'));
        $body = $this->data['message'] ?? $this->data['body'] ?? '';
        $actionUrl = $this->data['action_url'] ?? null;
        $actionText = $this->data['action_text'] ?? $this->data['action_label'] ?? __('View Details');

        $mail = (new MailMessage)
            ->subject($subject);

        if (! empty($this->data['greeting'])) {
            $mail->greeting($this->data['greeting']);
        }

        $lines = preg_split("/\r\n|\n|\r/", (string) $body);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $mail->line($trimmed);
            }
        }

        if (! empty($actionUrl)) {
            $mail->action($actionText, $actionUrl);
        }

        $appName = (string) config('app.name', 'UHS-AMS');
        $mail->salutation(__('Best regards,')."\n".$appName);

        return $mail;
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * Aligns with Filament's DatabaseNotifications structure.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $body = $this->data['message'] ?? $this->data['body'] ?? '';
        $iconColor = $this->data['color'] ?? $this->data['iconColor'] ?? 'primary';
        $actionUrl = $this->data['action_url'] ?? null;

        $actions = [];
        if (! empty($actionUrl)) {
            $actions[] = [
                'name' => 'view',
                'label' => $this->data['action_text'] ?? $this->data['action_label'] ?? __('View Details'),
                'url' => $actionUrl,
                'color' => $iconColor,
                'shouldOpenInNewTab' => false,
            ];
        }

        return [
            'id' => (string) Str::uuid(),
            'title' => $this->data['title'] ?? $this->data['subject'] ?? '',
            'body' => $body,
            'message' => $body,
            'icon' => $this->data['icon'] ?? 'heroicon-o-bell',
            'iconColor' => $iconColor,
            'color' => $iconColor,
            'action_url' => $actionUrl,
            'actions' => $actions,
            'metadata' => (array) ($this->data['metadata'] ?? []),
        ];
    }
}
