@php
    $registerRoute = 'filament.admin.auth.register';

    $registerUrl = \Illuminate\Support\Facades\Route::has($registerRoute)
        ? route($registerRoute)
        : null;
@endphp

<style>
    .fi-simple-header-subheading,
    .fi-simple-header .fi-simple-header-subheading,
    .fi-simple-header > p,
    .fi-simple-header .fi-link {
        display: none !important;
    }
</style>

@if ($registerUrl)
    <div style="margin-top: 1.25rem; text-align: center; font-size: 1rem;">
        <span style="color: #9ca3af;">{{ __('app.or') }}</span>

        <a
            href="{{ $registerUrl }}"
            style="color: #f59e0b; font-weight: 600; text-decoration: none;"
        >
            {{ __('app.sign_up_new_account') }}
        </a>
    </div>
@endif