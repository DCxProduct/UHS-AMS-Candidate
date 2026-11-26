<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('guide_books.navigation')) — UHS</title>
    <style>
        @font-face{font-family:GuideKhmer;src:url('{{ asset('KhmerOS_battambang.ttf') }}') format('truetype');font-display:swap}
        *{box-sizing:border-box}body{margin:0;background:#f7f8fb;color:#172033;font-family:GuideKhmer,system-ui,sans-serif;line-height:1.8}a{color:#2446b7;text-underline-offset:4px}header{background:#fff;border-bottom:1px solid #e2e6ee}.bar{max-width:1120px;margin:auto;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.brand{display:flex;align-items:center;gap:14px;font-weight:700;color:#172033;text-decoration:none}.brand img{width:48px;height:48px;object-fit:contain}.links{display:flex;gap:18px;align-items:center;flex-wrap:wrap}main{max-width:1120px;margin:auto;padding:40px 24px 64px}h1{font-size:clamp(24px,4vw,34px);margin:0 0 12px;line-height:1.7}h2{font-size:21px;line-height:1.7;margin:12px 0}.muted{color:#647084}.intro{margin-bottom:28px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:20px}.card{background:#fff;border:1px solid #e2e6ee;border-radius:16px;padding:24px;box-shadow:0 2px 5px #17203305}.card p{white-space:pre-line;overflow-wrap:anywhere}.tag{display:inline-block;border-radius:6px;padding:2px 10px;background:#edf2ff;color:#2446b7;font:600 12px/1.8 system-ui,sans-serif}.button{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:10px;background:#2446b7;color:#fff;text-decoration:none;font-weight:600}.actions{display:flex;flex-wrap:wrap;gap:14px;align-items:center;margin:24px 0}.preview{width:100%;height:75vh;min-height:420px;border:1px solid #dce2ed;border-radius:12px;background:#fff}.guide-image{display:block;max-width:100%;height:auto;margin:auto;border-radius:10px}.empty{text-align:center;padding:48px 24px}.pagination{margin-top:28px}.pagination nav{display:flex;gap:18px;align-items:center;justify-content:center}.pagination svg{width:22px;height:22px}:focus-visible{outline:3px solid #7295ff;outline-offset:5px}@media(max-width:520px){.bar,main{padding-left:18px;padding-right:18px}.card{padding:20px}.preview{min-height:360px}}
    </style>
</head>
<body>
<header><div class="bar">
    <a class="brand" href="{{ route('guide-books.index') }}"><img src="{{ asset('images/UHS_logo.png') }}" alt="UHS"><span>{{ __('guide_books.navigation') }}</span></a>
    <nav class="links" aria-label="{{ __('guide_books.navigation') }}">
        <a href="{{ route('language.toggle', ['locale' => 'km']) }}" lang="km">ខ្មែរ</a>
        <a href="{{ route('language.toggle', ['locale' => 'en']) }}" lang="en">English</a>
        <a href="{{ url(config('guide-books.login_path')) }}">{{ __('guide_books.back_to_login') }}</a>
    </nav>
</div></header>
<main>@yield('content')</main>
</body>
</html>
