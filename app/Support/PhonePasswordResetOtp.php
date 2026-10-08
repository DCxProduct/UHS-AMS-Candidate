<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Six-digit codes sent by SMS for resetting a password by phone number.
 *
 * Codes are stored hashed in password_reset_tokens under a "phone:" key, so
 * they never mix with the email reset links kept in the same table. A code
 * works once within its lifetime, and too many wrong tries cancel it.
 */
final class PhonePasswordResetOtp
{
    public const LIFETIME_MINUTES = 10;

    public const RESEND_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    /**
     * Seconds left before another code may be sent to this account, or 0.
     */
    public static function secondsUntilResend(User $user): int
    {
        $createdAt = DB::table('password_reset_tokens')->where('email', self::key($user))->value('created_at');

        if (blank($createdAt)) {
            return 0;
        }

        return max(0, self::RESEND_SECONDS - (int) Carbon::parse($createdAt)->diffInSeconds(now(), absolute: true));
    }

    public static function issue(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => self::key($user)],
            ['token' => Hash::make($code), 'created_at' => now()],
        );

        RateLimiter::clear(self::attemptsKey($user));

        return $code;
    }

    /**
     * Check the code. A correct code is used up, so it works only once.
     */
    public static function consume(User $user, string $code): bool
    {
        $attemptsKey = self::attemptsKey($user);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_ATTEMPTS)) {
            self::forget($user);

            return false;
        }

        $row = DB::table('password_reset_tokens')->where('email', self::key($user))->first();

        if (! $row || Carbon::parse($row->created_at)->addMinutes(self::LIFETIME_MINUTES)->isPast()) {
            return false;
        }

        if (! Hash::check($code, $row->token)) {
            RateLimiter::hit($attemptsKey, self::LIFETIME_MINUTES * 60);

            return false;
        }

        self::forget($user);

        return true;
    }

    public static function forget(User $user): void
    {
        DB::table('password_reset_tokens')->where('email', self::key($user))->delete();
        RateLimiter::clear(self::attemptsKey($user));
    }

    private static function key(User $user): string
    {
        return 'phone:'.$user->getKey();
    }

    private static function attemptsKey(User $user): string
    {
        return 'password-reset-phone-otp:'.$user->getKey();
    }
}
