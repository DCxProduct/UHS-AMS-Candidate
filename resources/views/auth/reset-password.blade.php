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
            margin: 0 0 32px;
            font-size: 38px;
            line-height: 1.25;
            font-weight: 800;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .field {
            margin-bottom: 22px;
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
        }

        button {
            width: 100%;
            height: 58px;
            margin-top: 10px;
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
    @includeIf('filament.components.language-toggle')
</div>

<main class="card">
    <div class="logo">
        <img src="{{ asset('images/UHS_logo.png') }}" alt="UHS Logo">
    </div>

    <h1>{{ __('app.reset_password') }}</h1>

    <form method="POST" action="{{ route('admin.password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div class="field">
            <label for="email">
                {{ __('app.email_address') }}<span style="color:#f87171">*</span>
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
                {{ __('app.new_password') }}<span style="color:#f87171">*</span>
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
                {{ __('app.confirm_password') }}<span style="color:#f87171">*</span>
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
        <a href="{{ url('/admin/login') }}">{{ __('app.sign_in') }}</a>
    </div>
</main>
</body>
</html>
