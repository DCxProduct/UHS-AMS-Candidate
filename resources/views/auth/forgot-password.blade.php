<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.forgot_password') }}</title>

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

        /* 1. Scaled down the main card container */
        .card {
            width: 100%;
            max-width: 440px; /* Reduced from 760px */
            background: #18181b;
            border: 1px solid #2f2f33;
            padding: 40px 32px; /* Reduced padding */
            border-radius: 16px; /* Added nice rounded corners to the card */
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5); /* Subtle depth */
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo img {
            width: 72px; /* Reduced from 96px */
            height: 72px;
            object-fit: contain;
        }

        h1 {
            margin: 0 0 10px;
            font-size: 26px; /* Reduced from 38px */
            line-height: 1.3;
            font-weight: 800;
            text-align: center;
        }

        .description {
            margin: 0 auto 28px;
            color: #a1a1aa;
            text-align: center;
            font-size: 14px; /* Reduced from 16px */
            line-height: 1.6;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px; /* Reduced from 18px */
            font-weight: 600;
        }

        /* 2. Scaled down the input field */
        input {
            width: 100%;
            height: 48px; /* Reduced from 58px */
            border-radius: 10px;
            border: 1px solid #52525b;
            background: #27272a;
            color: #ffffff;
            padding: 0 16px;
            font-size: 15px; /* Reduced from 18px */
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2); /* Softer focus ring */
        }

        .error {
            margin-top: 6px;
            color: #f87171;
            font-size: 13px;
            line-height: 1.5;
        }

        .status {
            margin-bottom: 20px;
            border-radius: 10px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
            padding: 12px 16px;
            font-size: 14px;
            line-height: 1.5;
        }

        /* 3. Scaled down the button */
        button {
            width: 100%;
            height: 48px; /* Reduced from 58px */
            margin-top: 24px;
            border: 0;
            border-radius: 10px;
            background: #1e40af;
            color: #ffffff;
            font-size: 15px; /* Reduced from 18px */
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
        }

        button:hover {
            background: #1e3a8a;
        }

        button:active {
            transform: scale(0.98); /* Nice click effect */
        }

        .back {
            margin-top: 28px;
            text-align: center;
            font-size: 14px; /* Reduced from 18px */
            color: #a1a1aa;
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

        /* Language Switcher Overrides */
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

        /* Mobile adjustments */
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

    <form method="POST" action="{{ route('admin.password.email') }}">
        @csrf

        <label for="email">
            {{ __('app.email_address') }}<span style="color:#f87171; margin-left: 4px;">*</span>
        </label>

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
