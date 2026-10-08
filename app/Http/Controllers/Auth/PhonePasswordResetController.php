<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\NotificationLanguage;
use App\Support\PhonePasswordResetOtp;
use App\Support\PlasGateSms;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Forgot password by phone: a 6-digit code is sent by PlasGate SMS.
 * The email reset link in PasswordResetController stays as it is.
 */
class PhonePasswordResetController extends Controller
{
    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [
            'phone.required' => __('app.phone_required'),
        ]);

        if (! PlasGateSms::isConfigured()) {
            return $this->backWithError($request, 'phone', __('app.reset_otp_unavailable'));
        }

        $user = self::findUser((string) $request->input('phone'));

        if (! $user) {
            return $this->backWithError($request, 'phone', __('app.reset_otp_account_not_found'));
        }

        $waitSeconds = PhonePasswordResetOtp::secondsUntilResend($user);

        if ($waitSeconds > 0) {
            return $this->backWithError($request, 'phone', __('app.reset_otp_wait', ['seconds' => $waitSeconds]));
        }

        $code = PhonePasswordResetOtp::issue($user);

        // Local testing only: the code is also written to storage/logs/laravel.log,
        // in case the SMS is slow or not delivered. Never in production.
        if (! app()->isProduction()) {
            Log::info('Password reset code for phone '.$user->phone.': '.$code);
        }

        try {
            $sent = PlasGateSms::send($user->phone, trans('app.reset_otp_sms', [
                'code' => $code,
                'minutes' => PhonePasswordResetOtp::LIFETIME_MINUTES,
            ], NotificationLanguage::localeForUser($user)));
        } catch (Throwable $exception) {
            report($exception);
            $sent = false;
        }

        if (! $sent) {
            PhonePasswordResetOtp::forget($user);

            return $this->backWithError($request, 'phone', __('app.reset_otp_failed'));
        }

        return redirect()
            ->route('student.password.phone.verify', ['phone' => trim((string) $request->input('phone'))])
            ->with('status', __('app.reset_otp_sent'));
    }

    public function showVerifyForm(Request $request): View
    {
        return view('auth.reset-password-phone', [
            'phone' => trim((string) $request->query('phone')),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'digits:6'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^[!-~]+$/',
            ],
        ], [
            'phone.required' => __('app.phone_required'),

            'code.required' => __('app.reset_otp_code_required'),
            'code.digits' => __('app.reset_otp_code_digits'),

            'password.required' => __('app.password_required'),
            'password.min' => __('app.password_min'),
            'password.confirmed' => __('app.password_confirmed'),
            'password.regex' => __('app.password_english_only'),
        ]);

        $user = self::findUser((string) $request->input('phone'));

        if (! $user || ! PhonePasswordResetOtp::consume($user, (string) $request->input('code'))) {
            return $this->backWithError($request, 'code', __('app.reset_otp_invalid'));
        }

        $user->forceFill([
            'password' => Hash::make((string) $request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        return redirect('/login')
            ->with('status', __('app.password_reset_success'));
    }

    /**
     * The account for a phone number typed as 012 345 678, +855 12 345 678 or 85512345678.
     */
    private static function findUser(string $phone): ?User
    {
        $international = PlasGateSms::normalizePhone($phone);

        if ($international === null) {
            return null;
        }

        // Phones are stored in local form, e.g. 012345678.
        return User::query()
            ->whereIn('phone', ['0'.substr($international, 3), $international])
            ->first();
    }

    private function backWithError(Request $request, string $field, string $message): RedirectResponse
    {
        return back()
            ->withInput($request->only('phone'))
            ->withErrors([$field => $message]);
    }
}
