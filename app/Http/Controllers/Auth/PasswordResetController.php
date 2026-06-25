<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function showForgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ], [
            'email.required' => __('app.email_required'),
            'email.email' => __('app.email_invalid'),
        ]);

        $email = Str::lower(trim((string) $request->input('email')));

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => __('app.account_not_found'),
                ]);
        }

        $status = Password::sendResetLink([
            'email' => $email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __('app.password_reset_link_sent'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => __('app.password_reset_failed'),
            ]);
    }

    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => Str::lower(trim((string) $request->query('email'))),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^[!-~]+$/',
            ],
        ], [
            'email.required' => __('app.email_required'),
            'email.email' => __('app.email_invalid'),

            'password.required' => __('app.password_required'),
            'password.min' => __('app.password_min'),
            'password.confirmed' => __('app.password_confirmed'),
            'password.regex' => __('app.password_english_only'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/login')
                ->with('status', __('app.password_reset_success'));
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => __('app.password_reset_token_invalid'),
            ]);
    }
}
