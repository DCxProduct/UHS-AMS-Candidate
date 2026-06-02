<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.forgot_password') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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
            --input-bg: #ffffff;
            --input-border: #d1d5db;
            --input-text: #111827;
            --input-placeholder: #9ca3af;
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
            font-family: 'Kantumruy Pro', 'Inter', system-ui, sans-serif;
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
            max-width: 440px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: var(--shadow);
            transition: background-color 0.25s ease, border-color 0.25s ease;
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo img {
            object-fit: contain !important;
            width: 90px !important;
            height: 90px !important;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 26px;
            line-height: 1.3;
            font-weight: 800;
            text-align: center;
            color: var(--text-main);
        }

        .description {
            margin: 0 auto 28px;
            color: var(--text-muted);
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-main);
        }

        input {
            width: 100%;
            height: 48px;
            border-radius: 10px;
            border: 1px solid var(--input-border);
            background: var(--input-bg);
            color: var(--input-text);
            padding: 6 12px;
            font-size: 15px;
            font-family: inherit;
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
            height: 48px;
            display: flex;
            align-items: center;
            overflow: hidden;
            border-radius: 10px;
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
            margin-top: 6px;
            color: var(--error-color);
            font-size: 13px;
            line-height: 1.5;
        }

        .status {
            margin-bottom: 20px;
            border-radius: 10px;
            background: var(--status-bg);
            border: 1px solid var(--status-border);
            color: var(--status-text);
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        button {
            width: 100%;
            height: 48px;
            margin-top: 24px;
            border: 0;
            border-radius: 10px;
            background: var(--button-bg);
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        button:hover {
            background: var(--button-hover);
        }

        button:active {
            transform: scale(0.98);
        }

        .back {
            margin-top: 28px;
            text-align: center;
            font-size: 16px;
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
                padding: 32px 24px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="language">
    @include('language-switch::language-switch')
</div>

<main class="card">
    <div class="logo">
        <img src="{{ asset('images/UHS_logo.png') }}" alt="UHS Logo">
    </div>

    <h1>{{ __('app.forgot_password') }}</h1>

    <p class="description">
        {{ __('app.forgot_password_description') }}
    </p>

    @if (session('status'))
        <div class="status">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('student.password.email') }}">
        @csrf

        <label for="email">
            {{ __('app.email_address') }}<span style="color:#f87171; margin-left: 4px;">*</span>
        </label>

        <div class="input-wrapper">
            <div class="input-prefix">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21.75 6.75v10.5A2.25 2.25 0 0 1 19.5 19.5h-15A2.25 2.25 0 0 1 2.25 17.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15A2.25 2.25 0 0 0 2.25 6.75m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>

            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                placeholder="{{ __('app.enter_email_address') }}"
                autocomplete="email"
                autofocus
                required
            >
        </div>

        @error('email')
        <div class="error">{{ $message }}</div>
        @enderror

        <button type="submit">
            {{ __('app.send_reset_link') }}
        </button>
    </form>

    <div class="back">
        {{ __('app.remember_password') }}
        <a href="{{ url('/student/login') }}">{{ __('app.sign_in') }}</a>
    </div>
</main>
</body>
</html>
