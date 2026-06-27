<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DbSyncApiService
{
    public function sync(bool $dryRun = false, bool $fullResync = false): array
    {
        $response = $this->sendSyncRequest($this->buildPayload($dryRun, $fullResync));

        return $this->normalizeResponse($response);
    }

    protected function buildPayload(bool $dryRun, bool $fullResync): array
    {
        return [
            'source_db' => config('sync.source_db'),
            'local_db' => config('sync.local_db'),
            'tables' => config('sync.tables'),
            'dry_run' => $dryRun,
            'full_resync' => $fullResync,
        ];
    }

    protected function sendSyncRequest(array $payload): Response
    {
        $apiUrl = config('sync.api_url');

        if (blank($apiUrl)) {
            throw new RuntimeException('Sync service is not configured. Please set the sync API URL.');
        }

        Log::debug('DB sync request started.', [
            'api_url' => $apiUrl,
            'payload' => $this->sanitizePayload($payload),
            'has_api_key' => filled(config('sync.api_key')),
        ]);

        try {
            $response = Http::timeout(120)
                ->acceptJson()
                ->asJson()
                ->withHeaders(array_filter([
                    'X-API-Key' => config('sync.api_key'),
                ]))
                ->post($apiUrl, $payload);
        } catch (ConnectionException $exception) {
            Log::error('DB sync connection failed.', [
                'api_url' => $apiUrl,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException($this->connectionFailureMessage($exception), previous: $exception);
        } catch (Throwable $exception) {
            Log::error('DB sync request failed unexpectedly.', [
                'api_url' => $apiUrl,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw new RuntimeException('The sync service did not respond correctly. Please try again.', previous: $exception);
        }

        Log::debug('DB sync response received.', [
            'api_url' => $apiUrl,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $this->responseBodyForLog($response),
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->failureMessage($response));
        }

        return $response;
    }

    protected function normalizeResponse(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException('The sync service returned an unreadable response.');
        }

        $success = (bool) ($body['success'] ?? false);

        return [
            'success' => $success,
            'message' => $this->messageFromBody($body, $success),
            'data' => [
                'mode' => $body['mode'] ?? null,
                'full_resync' => $body['full_resync'] ?? null,
                'auto_full_resync' => $body['auto_full_resync'] ?? null,
                'dry_run' => $body['dry_run'] ?? null,
                'tables' => data_get($body, 'data.tables'),
                'summary' => $body['summary'] ?? null,
                'plan' => $body['plan'] ?? data_get($body, 'data.plan'),
            ],
            'errors' => $this->normalizeErrors($body['errors'] ?? []),
            'raw' => $body,
        ];
    }

    protected function failureMessage(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            $detail = $body['detail'] ?? null;

            if (is_array($detail)) {
                $firstMessage = data_get($detail, '0.msg');

                if (is_string($firstMessage) && filled($firstMessage)) {
                    return 'The sync request was not accepted: '.$firstMessage;
                }
            }

            if (is_string($detail) && filled($detail)) {
                return 'The sync request was not accepted: '.$detail;
            }

            if (is_string($body['message'] ?? null) && filled($body['message'])) {
                return $body['message'];
            }
        }

        return match ($response->status()) {
            401, 403 => 'Sync is not allowed. Please check the API key and try again.',
            404 => 'The sync service endpoint was not found. Please check the sync API URL.',
            422 => 'The sync request has invalid settings. Please review the database sync configuration.',
            500, 502, 503, 504 => 'The sync service is temporarily unavailable. Please try again later.',
            default => 'Sync failed before it could finish. Please try again.',
        };
    }

    protected function connectionFailureMessage(ConnectionException $exception): string
    {
        $message = $exception->getMessage();

        if (Str::contains($message, 'cURL error 77')) {
            return 'Sync cannot start because PHP cannot read its SSL certificate file. Please fix C:\\php\\cacert.pem or update the curl.cainfo path in php.ini.';
        }

        if (Str::contains($message, 'cURL error 7')) {
            return 'The sync service is online in DNS, but this computer cannot connect to it right now. Please check the sync API server, firewall, VPN, or network connection and try again.';
        }

        return 'We could not reach the sync service. Please check your connection and try again.';
    }

    protected function messageFromBody(array $body, bool $success): string
    {
        $message = $body['message'] ?? null;

        if (is_string($message) && filled($message)) {
            return $message;
        }

        return $success
            ? 'Sync completed successfully.'
            : 'Sync finished, but some data could not be updated.';
    }

    protected function normalizeErrors(array $errors): array
    {
        return collect($errors)
            ->filter(fn (mixed $error): bool => is_array($error))
            ->map(fn (array $error): array => [
                'code' => $error['code'] ?? null,
                'table' => $error['table'] ?? null,
                'message' => $error['message'] ?? 'Something went wrong while syncing this item.',
                'details' => $error['details'] ?? [],
            ])
            ->values()
            ->all();
    }

    protected function sanitizePayload(array $payload): array
    {
        return collect($payload)
            ->map(function (mixed $value, string|int $key): mixed {
                if (is_string($key) && Str::contains($key, ['password', 'api_key', 'token', 'secret'], ignoreCase: true)) {
                    return filled($value) ? '[hidden]' : null;
                }

                if (is_array($value)) {
                    return $this->sanitizePayload($value);
                }

                return $value;
            })
            ->all();
    }

    protected function responseBodyForLog(Response $response): mixed
    {
        $json = $response->json();

        if (is_array($json)) {
            return $json;
        }

        return Str::limit($response->body(), 2000);
    }
}
