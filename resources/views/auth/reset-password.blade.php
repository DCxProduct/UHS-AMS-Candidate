<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.reset_password') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Kantumruy+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: #050506;
            color: #ffffff;
            font-family: 'Kantumruy Pro', 'Inter', system-ui, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .language {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 50;
        }

        /* Scaled down premium card */
        .card {
            width: 100%;
            max-width: 440px; /* Matched to forgot password page */
            background: #18181b;
            border: 1px solid #2f2f33;
            padding: 40px 32px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo img {
            width: 72px; /* Scaled down */
            height: 72px;
            object-fit: contain;
        }

        h1 {
            margin: 0 0 28px;
            font-size: 26px; /* Scaled down */
            line-height: 1.3;
            font-weight: 800;
            text-align: center;
        }

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px; /* Scaled down */
            font-weight: 600;
        }

        /* Scaled down inputs */
        input {
            width: 100%;
            height: 48px; /* Standard 48px height */
            border-radius: 10px;
            border: 1px solid #52525b;
            background: #27272a;
            color: #ffffff;
            padding: 0 16px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .error {
            margin-top: 6px;
            color: #f87171;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Modern button */
        button {
            width: 100%;
            height: 48px;
            margin-top: 8px;
            border: 0;
            border-radius: 10px;
            background: #1e40af;
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        button:hover {
            background: #1e3a8a;
        }

        button:active {
            transform: scale(0.98);
        }

        .back {
            margin-top: 28px;
            text-align: center;
            font-size: 14px;
        }

        .back a {
            color: #f59e0b;
            text-decoration: none;
            font-weight: 700;
            transition: opacity 0.2s;
        }

        .back a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }

        /* Borderless Language Switcher Overrides */
        .fls-display-on {
            position: fixed !important;
            top: 18px !important;
            left: 18px !important;
            right: auto !important;
            bottom: auto !important;
            z-index: 9999 !important;
            padding: 0 !important;
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
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            overflow: hidden !important;
            padding: 0 !important;
            text-decoration: none !important;
        }

        .uhs-one-click-language:hover,
        .language-switch-trigger:hover {
            opacity: 0.8 !important;
        }

        .uhs-one-click-language-flag,
        .uhs-one-click-language img,
        .language-switch-trigger img {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            border-radius: 9999px !important;
            object-fit: cover !important;
            object-position: center !important;
            border: none !important;
        }

        @media (max-width: 640px) {
            .card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
<div class="language">
    @includeIf('filament.components.language-toggle')
</div>

<main class="card">
    <div class="logo">
        <img src="{{ asset('images/UHS_logo.png') }}" alt="UHS Logo">
    </div>

    <h1>{{ __('app.reset_password') }}</h1>

    <form method="POST" action="{{ route('student.password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">
                {{ __('app.email_address') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email', $email) }}"
                placeholder="{{ __('app.enter_email_address') }}"
                autocomplete="email"
                required
            >

            @error('email')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password">
                {{ __('app.new_password') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <input
                id="password"
                name="password"
                type="password"
                placeholder="{{ __('app.enter_new_password') }}"
                autocomplete="new-password"
                required
            >

            @error('password')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">
                {{ __('app.confirm_password') }}<span style="color:#f87171; margin-left: 4px;">*</span>
            </label>

            <input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                placeholder="{{ __('app.enter_confirm_password') }}"
                autocomplete="new-password"
                required
            >
        </div>

        <button type="submit">
            {{ __('app.reset_password') }}
        </button>
    </form>

    <div class="back">
        <a href="{{ url('/login') }}">{{ __('app.sign_in') }}</a>
    </div>
</main>
</body>
</html>
