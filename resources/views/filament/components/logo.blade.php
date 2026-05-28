@php
    $isLoggedIn = \Filament\Facades\Filament::auth()->check();
@endphp

@if ($isLoggedIn)
    <div style="display: flex; align-items: center; gap: 12px;">
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            style="height: 80px; width: 80px; object-fit: contain;"
        >

        <div style="line-height: 1.15; min-width: 0;">
            <div style="font-weight: 700; font-size: 14px; font-family: 'Kh Muol'; white-space: nowrap;">
                សាកលវិទ្យាល័យវិទ្យាសាស្រ្តសុខាភិបាល
            </div>

            <div style="font-weight: 700; font-size: 16px; white-space: nowrap; font-family: 'Times New Roman'; margin-top: 5px;">
                University of Health Sciences
            </div>
        </div>
    </div>
@else
    <div style="display: flex; align-items: center; justify-content: center;">
        <img
            src="{{ asset('images/UHS_logo.png') }}"
            alt="UHS Logo"
            style="height: 90px; width: 90px; object-fit: contain;"
        >
    </div>
@endif