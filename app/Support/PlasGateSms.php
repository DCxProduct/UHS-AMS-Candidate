<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends SMS through the PlasGate REST API.
 */
final class PlasGateSms
{
    public static function isConfigured(): bool
    {
        return filled(config('services.plasgate.private_key')) && filled(config('services.plasgate.secret'));
    }

    /**
     * Cambodian numbers in international form without "+", as PlasGate expects:
     * 012 345 678, +855 12 345 678 and 85512345678 all become 85512345678.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '855')) {
            $digits = '855'.ltrim(substr($digits, 3), '0');
        } elseif (str_starts_with($digits, '0')) {
            $digits = '855'.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 13 ? $digits : null;
    }

    /**
     * Returns false when PlasGate is not set up or the number is unusable;
     * throws when PlasGate rejects the message.
     */
    public static function send(?string $phone, string $content): bool
    {
        $to = self::normalizePhone($phone);

        if (! self::isConfigured() || $to === null || trim($content) === '') {
            return false;
        }

        // Developer test phone: every SMS goes there, with the real number in front.
        $testPhone = self::normalizePhone(config('services.plasgate.test_phone'));

        if ($testPhone !== null && ! app()->isProduction()) {
            $content = '[TEST -> '.$to.'] '.$content;
            $to = $testPhone;
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders(['X-Secret' => (string) config('services.plasgate.secret')])
            ->withQueryParameters(['private_key' => (string) config('services.plasgate.private_key')])
            // The batch endpoint is used even for one message: /send returns HTTP 500 for this account.
            ->post(rtrim((string) config('services.plasgate.base_url'), '/').'/batch-send', [
                'globals' => ['sender' => (string) config('services.plasgate.sender')],
                'messages' => [['to' => [$to], 'content' => $content]],
            ]);

        if ($response->failed()) {
            Log::warning('PlasGate SMS failed', ['status' => $response->status(), 'body' => $response->body()]);

            throw new RuntimeException('PlasGate SMS failed with HTTP '.$response->status());
        }

        return true;
    }
}
