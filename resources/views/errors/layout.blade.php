<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} | {{ config('app.name', 'UHS-AMS') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f7fb; color: #172033; }
        main { width: min(92vw, 520px); padding: 3rem 2rem; text-align: center; background: #fff; border: 1px solid #dce3ee; border-radius: 8px; box-shadow: 0 14px 36px rgba(23, 32, 51, .08); }
        img { width: 72px; height: 72px; object-fit: contain; margin-bottom: 1rem; }
        .code { margin: 0; color: #1d4ed8; font-size: 3rem; line-height: 1; }
        h1 { margin: 1rem 0 .5rem; font-size: 1.35rem; }
        p { margin: 0 auto 1.5rem; color: #536174; line-height: 1.6; }
        a { display: inline-block; padding: .7rem 1rem; border-radius: 6px; background: #1d4ed8; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <img src="{{ asset('images/UHS_logo.png') }}" alt="UHS">
    <p class="code">{{ $code }}</p>
    <h1>{{ $title }}</h1>
    <p>{{ $message }}</p>
    <a href="{{ auth()->check() ? url('/dashboard') : url('/login') }}">
        {{ auth()->check() ? __('app.dashboard') : __('app.sign_in') }}
    </a>
</main>
</body>
</html>
