@php
    $registerRoute = 'filament.student.auth.register';

    $registerUrl = \Illuminate\Support\Facades\Route::has($registerRoute)
        ? route($registerRoute)
        : url('register');
@endphp

<style>
    .fi-simple-header-subheading,
    .fi-simple-header .fi-simple-header-subheading,
    .fi-simple-header > p,
    .fi-simple-header .fi-link {
        display: none !important;
    }

    .uhs-auth-links {
        margin-top: 1.75rem;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 1.75rem;
        font-size: 0.95rem;
        font-weight: 800;
    }

    .uhs-auth-link {
        color: #f59e0b;
        text-decoration: none;
        background: transparent !important;
        transition: all 0.2s ease;
    }

    .uhs-auth-link:hover {
        color: #fbbf24;
        text-decoration: underline;
        text-underline-offset: 4px;
    }

    .uhs-auth-divider {
        width: 1px;
        height: 18px;
        background: rgba(156, 163, 175, 0.45);
    }

    @media (max-width: 480px) {
        .uhs-auth-links {
            flex-direction: column;
            gap: 0.65rem;
        }

        .uhs-auth-divider {
            display: none;
        }
    }
</style>

<div class="uhs-auth-links">
    <a href="{{ route('student.password.request') }}" class="uhs-auth-link">
        {{ __('auth.forgot_password.label') }}
    </a>

    @if ($registerUrl)
        <span class="uhs-auth-divider"></span>

        <a href="{{ $registerUrl }}" class="uhs-auth-link">
            {{ __('auth.register.sign_up_new_account') }}
        </a>
    @endif
</div>
