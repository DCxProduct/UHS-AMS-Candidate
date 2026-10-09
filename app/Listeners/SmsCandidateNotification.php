<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\PlasGateSms;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use Throwable;

/**
 * Texts a short copy of each in-system notification a candidate receives,
 * through PlasGate. Nothing is sent until PlasGate is configured, and a
 * failed SMS is reported and skipped so the notification is never affected.
 */
class SmsCandidateNotification
{
    /** Khmer SMS use 70 characters per part, so long messages are cut. */
    private const MAX_LENGTH = 300;

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database'
            || ! $event->notification instanceof DatabaseNotification
            || ! $event->notifiable instanceof User
            || $event->notifiable->registration_type !== 'student'
            || ! PlasGateSms::isConfigured()
            // Workflow stage messages are texted only when the stage's SMS box is ticked.
            || filled($event->notification->data['viewData']['workflow_entry_id'] ?? null)) {
            return;
        }

        $text = collect([
            $event->notification->data['title'] ?? null,
            $event->notification->data['body'] ?? null,
        ])
            ->map(fn (mixed $value): string => self::plainText($value))
            ->filter()
            ->implode("\n");

        if ($text === '') {
            return;
        }

        try {
            PlasGateSms::send($event->notifiable->phone, 'UHS-AMS: '.Str::limit($text, self::MAX_LENGTH));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function plainText(mixed $value): string
    {
        $text = preg_replace('/<br\s*\/?>|<\/p>/i', "\n", (string) $value);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }
}
