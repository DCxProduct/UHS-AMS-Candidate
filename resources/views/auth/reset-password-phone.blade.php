<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.reset_password') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}">

    @vite(['resources/css/app.css'])

    <script>
        (function () {
            const html = document.documentElement;

            const savedTheme =
                localStorage.getItem('theme') ||
                localStorage.getItem('appearance') ||
                localStorage.getItem('filament-theme') ||
                localStorage.getItem('color-theme') ||
                'light';

            const isDark =
                savedTheme === 'dark' ||
                (
                    savedTheme === 'system' &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches
                );

            html.classList.toggle('dark', isDark);
            html.classList.toggle('light', !isDark);
        })();
    </script>

    <style>
        :root,
        html.light {
            --page-bg: #f8fafc;
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --text-main: #111827;
            --text-muted: #64748b;
            --input-border: #d1d5db;
            --input-text: #111827;
            --input-placeholder: #6b7280;
            --button-bg: #1d4ed8;
            --button-hover: #1e40af;
            --link-color: #f59e0b;
            --error-color: #dc2626;
            --status-bg: #ecfdf5;
            --status-border: #bbf7d0;
            --status-text: #166534;
            --shadow: 0 20px 35px -15px rgba(15, 23, 42, 0.18);
        }

        html.dark {
            --page-bg: #050506;
            --card-bg: #18181b;
            --card-border: #2f2f33;
            --text-main: #ffffff;
            --text-muted: #a1a1aa;
            --input-bg: #27272a;
            --input-border: #52525b;
            --input-text: #ffffff;
            --input-placeholder: #71717a;
            --button-bg: #1e40af;
            --button-hover: #1e3a8a;
            --link-color: #f59e0b;
            --error-color: #f87171;
            --status-bg: rgba(34, 197, 94, 0.12);
            --status-border: rgba(34, 197, 94, 0.35);
            --status-text: #86efac;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page-bg);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            transition: background-color 0.25s ease, color 0.25s ease;
        }

        .language {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 50;
        }

        .card {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 48px 36px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .logo img {
            object-fit: contain !important;
            width: 90px !important;
            height: 90px !important;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 24px; /* Increased size to match login */
            line-height: 1.4;
            text-align: center;
            color: var(--text-main);
        }

        .description {
            margin: 0 auto 32px;
            color: var(--text-muted);
            text-align: center;
            font-size: 15px;
            line-height: 1.6;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            color: var(--text-main);
        }

        /* Increased height to match login fields */
        input {
            width: 100%;
            height: 52px;
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--input-text);
            padding: 6px 16px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.25s ease, color 0.25s ease;
        }

        input::placeholder {
            color: var(--input-placeholder);
        }

        input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .input-wrapper {
            width: 100%;
            height: 52px; /* Matched height */
            display: flex;
            align-items: center;
            overflow: hidden;
            border-radius: 8px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            transition: border-color 0.2s, box-shadow 0.2s, background-color 0.25s ease;
        }
        .input-wrapper:focus-within {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .input-prefix {
            width: 56px;
            min-width: 56px;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid var(--input-border);
            color: var(--input-placeholder);
            background: transparent;
        }

        .input-prefix svg {
            width: 22px;
            height: 22px;
        }

        .input-wrapper input {
            height: 100%;
            border: 0;
            border-radius: 0;
            box-shadow: none;
            background: transparent;
            padding: 0 16px;
        }

        .input-wrapper input:focus {
            border-color: transparent;
            box-shadow: none;
        }

        .error {
            margin-top: 8px;
            color: var(--error-color);
            font-size: 14px;
            line-height: 1.5;
        }

        .status {
            margin-bottom: 24px;
            border-radius: 8px;
            background: var(--status-bg);
            border: 1px solid var(--status-border);
            color: var(--status-text);
            padding: 14px 16px;
            font-size: 15px;
            line-height: 1.5;
        }

        button {
            width: 100%;
            height: auto;
            margin-top: 28px;
            border: 0;
            border-radius: 8px;
            background: var(--button-bg);
            color: #ffffff;
            font-size: 16px; /* Adjust as needed */
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            padding: 12px 16px;
        }

        button:hover {
            background: var(--button-hover);
        }

        button:active {
            transform: scale(0.98);
        }

        .back {
            margin-top: 32px;
            text-align: center;
            font-size: 15.2px;
            color: var(--text-muted);
        }

        .back a {
            color: var(--link-color);
            text-decoration: none;
            font-weight: 700;
            transition: opacity 0.2s;
        }

        .back a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        .field {
            margin-bottom: 20px;
        }

        .field button {
            margin-top: 8px;
        }

        /* Email / phone choice */
        .reset-tabs {
            display: flex;
            gap: 6px;
            margin: 0 0 28px;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid var(--card-border);
            background: var(--page-bg);
        }

        .reset-tabs a {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: background-color 0.2s, color 0.2s;
        }

        .reset-tabs a.active {
            background: var(--button-bg);
            color: #ffffff;
        }

        /* Language Switcher */
        .fls-display-on {
            position: fixed !important;
            top: 18px !important;
            left: 18px !important;
            right: auto !important;
            bottom: auto !important;
            z-index: 9999 !important;
            padding: 0 !important;
        }

        .fls-display-on > div {
            background: transparent !important;
        }

        .uhs-one-click-language,
        .language-switch-trigger {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 9999px !important;
            overflow: hidden !important;
            text-decoration: none !important;
            padding: 0 !important;
        }

        .uhs-one-click-language:hover,
        .language-switch-trigger:hover {
            border-color: #f59e0b !important;
        }

        .uhs-one-click-language-flag,
        .uhs-one-click-language img,
        .language-switch-trigger img {
            width: 36px !important;
            height: 36px !important;
            min-width: 36px !important;
            min-height: 36px !important;
            border-radius: 9999px !important;
            object-fit: cover !important;
            object-position: center !important;
        }

        @media (max-width: 640px) {
            .card {
                padding: 36px 24px;
            }

            h1 {
                font-size: 24px;
            }
        }
        /* Show / hide password */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 52px;
        }

        .password-wrapper .password-toggle {
            position: absolute;
            top: 0;
            right: 0;
            width: 52px;
            height: 52px;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 0 8px 8px 0;
            background: transparent;
            color: var(--input-placeholder);
            cursor: pointer;
        }

        .password-wrapper .password-toggle:hover {
            background: transparent;
            color: var(--text-main);
        }

        .password-wrapper .password-toggle:active {
            transform: none;
        }

        .password-wrapper .password-toggle svg {
            width: 22px;
            height: 22px;
        }

        .password-wrapper .password-toggle .icon-hide,
        .password-wrapper .password-toggle.is-visible .icon-show {
            display: none;
        }

        .password-wrapper .password-toggle.is-visible .icon-hide {
            display: block;
        }
    </style>
</head>
<body>

<main class="card">
    <div class="logo">
        <img src="{{ asset('images/UHS_logo.png') }}" alt="UHS Logo">
    </div>

    <h1>{{ __('app.reset_password') }}</h1>

    @if (session('status'))
        <div class="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('student.password.phone.update') }}">
        @csrf

        <div class="field">
            <label for="phone">
                {{ __('app.phone_number') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <input
                id="phone"
                name="phone"
                type="tel"
                inputmode="tel"
                value="{{ old('phone', $phone) }}"
                placeholder="{{ __('app.enter_phone_number') }}"
                autocomplete="tel"
                required
            >

            @error('phone')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="code">
                {{ __('app.reset_otp_code') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="{{ __('app.reset_otp_enter_code') }}"
                autocomplete="one-time-code"
                autofocus
                required
            >

            @error('code')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password">
                {{ __('app.new_password') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <div class="password-wrapper">
                <input
                    id="password"
                    name="password"
                    type="password"
                    placeholder="{{ __('app.enter_new_password') }}"
                    autocomplete="new-password"
                    required
                >
                <button type="button" class="password-toggle" data-password-toggle="password" aria-label="{{ __('app.show_password') }}" title="{{ __('app.show_password') }}">
                    <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>

            @error('password')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">
                {{ __('app.confirm_password') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <div class="password-wrapper">
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    placeholder="{{ __('app.enter_confirm_password') }}"
                    autocomplete="new-password"
                    required
                >
                <button type="button" class="password-toggle" data-password-toggle="password_confirmation" aria-label="{{ __('app.show_password') }}" title="{{ __('app.show_password') }}">
                    <svg class="icon-show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <svg class="icon-hide" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit">
            {{ __('app.reset_password') }}
        </button>
    </form>

    <div class="back">
        <a href="{{ route('student.password.request', ['method' => 'phone']) }}">{{ __('app.reset_otp_send_again') }}</a>
        &nbsp;·&nbsp;
        <a href="{{ url('/login') }}">{{ __('app.sign_in') }}</a>
    </div>
</main>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);
            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.classList.toggle('is-visible', show);
            button.setAttribute('aria-label', show ? @json(__('app.hide_password')) : @json(__('app.show_password')));
            button.setAttribute('title', show ? @json(__('app.hide_password')) : @json(__('app.show_password')));
        });
    });
</script>
</body>
</html>
