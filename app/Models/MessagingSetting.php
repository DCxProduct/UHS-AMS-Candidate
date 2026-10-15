<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Gmail (SMTP) and PlasGate settings, edited in Settings → Email & SMS Settings.
 *
 * A filled value overrides the matching .env value; an empty one keeps .env.
 */
class MessagingSetting extends Model
{
    /**
     * Column => config key it overrides.
     */
    public const CONFIG_KEYS = [
        'mail_host' => 'mail.mailers.smtp.host',
        'mail_port' => 'mail.mailers.smtp.port',
        'mail_username' => 'mail.mailers.smtp.username',
        'mail_password' => 'mail.mailers.smtp.password',
        'mail_from_address' => 'mail.from.address',
        'mail_from_name' => 'mail.from.name',
        'plasgate_private_key' => 'services.plasgate.private_key',
        'plasgate_secret' => 'services.plasgate.secret',
        'plasgate_sender' => 'services.plasgate.sender',
        'plasgate_test_phone' => 'services.plasgate.test_phone',
    ];

    /**
     * Stored encrypted; shown in the form as password fields.
     */
    public const SECRETS = ['mail_password', 'plasgate_private_key', 'plasgate_secret'];

    protected $fillable = [
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
        'plasgate_private_key',
        'plasgate_secret',
        'plasgate_sender',
        'plasgate_test_phone',
    ];

    protected function casts(): array
    {
        return [
            'mail_port' => 'integer',
            'mail_password' => 'encrypted',
            'plasgate_private_key' => 'encrypted',
            'plasgate_secret' => 'encrypted',
        ];
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    /**
     * Put the saved values into config so mail and SMS use them.
     */
    public static function applyToConfig(): void
    {
        try {
            $settings = static::query()->find(1);
        } catch (Throwable) {
            // Table not migrated yet (fresh install, tests): keep .env.
            return;
        }

        if (! $settings) {
            return;
        }

        foreach (self::CONFIG_KEYS as $column => $configKey) {
            try {
                $value = $settings->getAttribute($column);
            } catch (Throwable $exception) {
                // An encrypted value saved with another APP_KEY cannot be read; keep .env.
                report($exception);

                continue;
            }

            if (filled($value)) {
                config([$configKey => $value]);
            }
        }
    }
}
