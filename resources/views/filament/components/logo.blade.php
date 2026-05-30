@php
    $isLoggedIn = \Filament\Facades\Filament::auth()->check();
@endphp

@if ($isLoggedIn)
    <div class="uhs-brand-logo">
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            class="uhs-brand-logo-image"
        >

        <div class="uhs-brand-logo-text">
            <div class="uhs-brand-logo-kh">
                សាកលវិទ្យាល័យវិទ្យាសាស្រ្តសុខាភិបាល
            </div>

            <div class="uhs-brand-logo-en">
                University of Health Sciences
            </div>
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
