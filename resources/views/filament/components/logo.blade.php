@php
    $isLoggedIn = \Filament\Facades\Filament::auth()->check();
    $locale = app()->getLocale();

    $brandName = $locale === 'km'
        ? 'សាកលវិទ្យាល័យវិទ្យាសាស្រ្តសុខាភិបាល'
        : 'University of Health Sciences';
@endphp

@if ($isLoggedIn)
    <div class="uhs-brand-logo">
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            class="uhs-brand-logo-image"
        >

        <div class="uhs-brand-logo-text" title="{{ $brandName }}">
            {{ $brandName }}
        </div>
    </div>
@else
    <div class="uhs-auth-logo">
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            class="uhs-auth-logo-image"
        >
    </div>
@endif
