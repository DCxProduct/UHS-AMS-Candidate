@php
    $registerRoute = 'filament.student.auth.register';

    $registerUrl = \Illuminate\Support\Facades\Route::has($registerRoute)
        ? route($registerRoute)
        : url('/student/register');
@endphp

<style>
    .fi-simple-header-subheading,
    .fi-simple-header .fi-simple-header-subheading,
    .fi-simple-header > p,
    .fi-simple-header .fi-link {
        display: none !important;
    }
</style>

<div style="margin-top: 1.25rem; text-align: center; font-size: 1rem;">
    <a
        href="{{ route('student.password.request') }}"
        style="color: #f59e0b; font-weight: 800; text-decoration: none;"
    >
        {{ __('app.forgot_password') }}
    </a>
</div>

@if ($registerUrl)
    <div style="margin-top: 1.25rem; text-align: center; font-size: 1rem;">
        <span style="color: #9ca3af;">{{ __('app.or') }}</span>

        <a
            href="{{ $registerUrl }}"
            style="color: #f59e0b; font-weight: 800; text-decoration: none;"
        >
            {{ __('app.sign_up_new_account') }}
        </a>
    </div>
@endif
