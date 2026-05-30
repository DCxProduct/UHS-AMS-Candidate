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
            padding: 32px 16px;
        }

        .language {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 50;
        }

        .card {
            width: 100%;
            max-width: 760px;
            background: #18181b;
            border: 1px solid #2f2f33;
            padding: 56px;
        }

        .logo {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo img {
            width: 96px;
            height: 96px;
            object-fit: contain;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 38px;
            line-height: 1.25;
            font-weight: 800;
            text-align: center;
        }

        .description {
            margin: 0 auto 32px;
            max-width: 580px;
            color: #a1a1aa;
            text-align: center;
            font-size: 16px;
            line-height: 1.7;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        input {
            width: 100%;
            height: 58px;
            border-radius: 14px;
            border: 1px solid #52525b;
            background: #27272a;
            color: #ffffff;
            padding: 0 18px;
            font-size: 18px;
            font-family: inherit;
            outline: none;
        }

        input:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }

        .error {
            margin-top: 8px;
            color: #f87171;
            font-size: 14px;
            line-height: 1.6;
        }

        .status {
            margin-bottom: 20px;
            border-radius: 12px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
            padding: 14px 16px;
            font-size: 15px;
            line-height: 1.6;
        }

        button {
            width: 100%;
            height: 58px;
            margin-top: 28px;
            border: 0;
            border-radius: 14px;
            background: #1e40af;
            color: #ffffff;
            font-size: 18px;
            font-weight: 800;
            font-family: inherit;
            cursor: pointer;
        }

        button:hover {
            background: #1e3a8a;
        }

        .back {
            margin-top: 34px;
            text-align: center;
            font-size: 18px;
            color: #a1a1aa;
        }

        .back a {
            color: #f59e0b;
            text-decoration: none;
            font-weight: 800;
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
            border: 2px solid rgba(255, 255, 255, 0.35) !important;
            background: rgba(255, 255, 255, 0.08) !important;

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
                padding: 36px 22px;
            }

            h1 {
                font-size: 30px;
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
            {{ __('app.email_address') }}<span style="color:#f87171">*</span>
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
        <a href="{{ url('/admin/login') }}">{{ __('app.sign_in') }}</a>
    </div>
</main>
</body>
</html>
