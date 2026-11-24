<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;

class MessagingSecret implements CastsAttributes
{
    private function encrypter(): Encrypter
    {
        $keys = array_values(array_unique(array_filter([
            config('services.messaging.encryption_key') ?: config('app.key'),
            config('app.key'),
            ...config('services.messaging.previous_keys', []),
            ...config('app.previous_keys', []),
        ], fn ($key): bool => is_string($key) && $key !== '')));
        $keys = array_map(function (string $key): string {
            if (! str_starts_with($key, 'base64:')) {
                return $key;
            }
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                throw new \InvalidArgumentException('Invalid messaging encryption key encoding.');
            }
            return $decoded;
        }, $keys);

        return (new Encrypter(array_shift($keys) ?? '', config('app.cipher', 'AES-256-CBC')))
            ->previousKeys($keys);
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : $this->encrypter()->decryptString($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : $this->encrypter()->encryptString($value);
    }
}
