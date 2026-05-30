@php
    $isLoggedIn = \Filament\Facades\Filament::auth()->check();
@endphp

@if ($isLoggedIn)
    {{-- wire:ignore added here to lock the header and prevent Livewire flashes --}}
    <div class="uhs-brand-logo" wire:ignore>
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            class="uhs-brand-logo-image"
        >

        <div class="uhs-brand-logo-text">
            <div class="uhs-brand-logo-kh">
                សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល
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
