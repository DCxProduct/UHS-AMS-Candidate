@php
    /*
    |--------------------------------------------------------------------------
    | Bachelor Transfer Application - PDF/Form Preview
    |--------------------------------------------------------------------------
    */

    $isPdfExport = $isPdfExport ?? false;

    if (isset($record) && $record) {
        $data = $record->toArray();
    } else {
        $data = $data ?? ($this->data ?? []);
    }

    $v = function (string $key, string $default = '') use ($data): string {
        $value = data_get($data, $key, $default);

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->format('d/m/Y');
        }

        return filled($value) ? (string) $value : $default;
    };

    $photoPathValue = $v('photo_path') ?: $v('photo');
    $photoUrl = null;

    if ($photoPathValue) {
        if ($isPdfExport) {
            $photoPath = storage_path('app/public/' . $photoPathValue);

            if (file_exists($photoPath)) {
                $photoUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($photoPath));
            }
        } else {
            $photoUrl = \Illuminate\Support\Facades\Storage::url($photoPathValue);
        }
    }

    $male = in_array($v('gender'), ['male', 'ប្រុស'], true);
    $female = in_array($v('gender'), ['female', 'ស្រី'], true);

    $single = in_array($v('marital_status'), ['single', 'នៅលីវ'], true);
    $married = in_array($v('marital_status'), ['married', 'រៀបការ'], true);

    $academicYear = $v('academic_year', __('bachelor_transfer_applications.defaults.academic_year'));

    $bt = fn (string $key, array $replace = []): string => __('bachelor_transfer_applications.pdf.' . $key, $replace);

    $genderText = $male
        ? __('bachelor_transfer_applications.options.gender.male')
        : ($female ? __('bachelor_transfer_applications.options.gender.female') : '');
@endphp

<style>
    @font-face {
        font-family: "KhmerOSBattambang";
        src:
            url("{{ asset('KhmerOS_battambang.ttf') }}") format("truetype"),
            url("{{ asset('fonts/KhmerOS_battambang.ttf') }}") format("truetype");
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    @font-face {
        font-family: "KhmerOSMuolLight";
        src:
            url("{{ asset('KhmerOS_muollight.ttf') }}") format("truetype"),
            url("{{ asset('fonts/KhmerOS_muollight.ttf') }}") format("truetype");
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    :root {
        --nee-bg: #eef2f7;
        --nee-paper: #ffffff;
        --nee-text: #111111;
        --nee-line: #111111;
        --nee-blue: #7bb8c4;
        --nee-shadow: 0 12px 28px rgba(15, 23, 42, .18);
    }

    .nee-wrapper {
        width: 100%;
        background: var(--nee-bg);
        padding: 18px;
        border-radius: 14px;
        overflow-x: auto;
    }

    .nee-page {
        width: 794px;
        min-height: 1123px;
        margin: 0 auto 24px;
        background: var(--nee-paper);
        color: var(--nee-text);
        box-shadow: var(--nee-shadow);
        position: relative;
        padding: 22px 28px;
        font-family: "KhmerOSBattambang", "Noto Sans Khmer", Arial, sans-serif !important;
        font-size: 11.2px;
        line-height: 1.45;
    }

    .nee-page:last-child {
        margin-bottom: 0;
    }

    .nee-page *,
    .nee-page *::before,
    .nee-page *::after {
        box-sizing: border-box;
        font-family: "KhmerOSBattambang", "Noto Sans Khmer", Arial, sans-serif !important;
    }

    .nee-muol,
    .nee-title-main,
    .nee-title-mid,
    .nee-title-small,
    .nee-kingdom-title,
    .nee-section-title {
        font-family: "KhmerOSMuolLight", "KhmerOSBattambang", "Noto Sans Khmer", serif !important;
        font-weight: 400 !important;
    }

    .nee-center {
        text-align: center;
    }

    .nee-bold {
        font-weight: 700;
    }

    .nee-photo-box {
        width: 110px;
        height: 140px;
        border: 1px solid var(--nee-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 11px;
        line-height: 1.7;
        color: #333;
        background: #fff;
        overflow: hidden;
    }

    .nee-photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .nee-cover-border {
        border: 4px double #222;
        min-height: 1078px;
        padding: 28px 34px;
        position: relative;
    }

    .nee-flex {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }

    .nee-empty-left {
        width: 190px;
        min-height: 1px;
    }

    .nee-fieldline {
        display: flex;
        align-items: flex-end;
        gap: 5px;
        min-height: 23px;
        flex-wrap: wrap;
    }

    .nee-line {
        flex: 1;
        border-bottom: 1px dotted var(--nee-line);
        min-height: 20px;
        padding: 0 3px;
        line-height: 1.25;
    }

    .nee-line-fixed {
        display: inline-block;
        border-bottom: 1px dotted var(--nee-line);
        min-height: 18px;
        padding: 0 3px;
        vertical-align: bottom;
    }

    .nee-input {
        width: 100%;
        height: 21px;
        border: 0;
        border-bottom: 1px dotted var(--nee-line);
        background: transparent;
        outline: none;
        padding: 0 3px;
        color: #111;
        font-size: 11.2px;
    }

    .nee-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    .nee-row {
        margin-top: 7px;
    }

    .nee-title-main {
        text-align: center;
        font-size: 22px;
        line-height: 1.9;
    }

    .nee-title-mid {
        text-align: center;
        font-size: 18px;
        line-height: 1.8;
    }

    .nee-title-small {
        text-align: center;
        font-size: 15px;
        line-height: 1.65;
    }

    .nee-divider {
        text-align: center;
        margin: 5px 0;
        letter-spacing: 1px;
    }

    .nee-kingdom {
        flex: 1;
        text-align: center;
        line-height: 1.7;
    }

    .nee-kingdom-title {
        font-size: 18px;
        line-height: 1.7;
    }

    .nee-check {
        appearance: none;
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        border: 1px solid #111;
        background: #fff;
        display: inline-block;
        position: relative;
        margin: 0 3px;
        vertical-align: middle;
    }

    .nee-check:checked::after {
        content: "✓";
        position: absolute;
        left: 1px;
        top: -8px;
        font-size: 17px;
        font-weight: 900;
        color: #111;
    }

    .nee-note {
        text-align: justify;
        line-height: 1.75;
        margin-top: 8px;
    }

    .nee-signature-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 45px;
        text-align: center;
        line-height: 1.9;
        margin-top: 18px;
    }

    .nee-receipt-copy {
        min-height: 520px;
        padding: 8px 8px 20px;
        border-bottom: 1px dashed #8b8b8b;
    }

    .nee-receipt-copy:last-child {
        border-bottom: 0;
    }

    .nee-receipt-number {
        width: 270px;
        min-height: 54px;
        border: 1px solid var(--nee-blue);
        padding: 11px 12px;
        font-size: 12px;
    }

    .nee-section-title {
        font-size: 12.8px;
        margin-top: 8px;
        margin-bottom: 4px;
    }

    .nee-doc-list {
        margin-top: 6px;
        line-height: 1.7;
    }

    .nee-doc-row {
        display: grid;
        grid-template-columns: 22px 1fr 70px;
        gap: 6px;
        align-items: center;
        margin-top: 2px;
    }

    .nee-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 6px;
    }

    .nee-table th,
    .nee-table td {
        border: 1px solid #111;
        padding: 4px;
        height: 27px;
        vertical-align: middle;
        font-size: 10.4px;
        line-height: 1.35;
    }

    .nee-table th {
        text-align: center;
        font-weight: 700;
    }

    .nee-subtitle {
        text-align: center;
        font-size: 11px;
        margin-top: 2px;
    }


    /*
    |--------------------------------------------------------------------------
    | Light mode / Dark mode preview - like document-requests
    |--------------------------------------------------------------------------
    | Light mode  = wrapper ពណ៌ប្រផេះស្រាល
    | Dark mode   = wrapper ខ្មៅ
    | Paper form  = នៅតែស
    | Text/line   = នៅតែខ្មៅ
    | Input       = មិនប្តូរទៅពណ៌ dark
    | Checkbox    = នៅស្អាតក្នុង dark mode
    */
    @media screen {
        .nee-wrapper {
            width: 100% !important;
            background: #f8fafc !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 18px !important;
            padding: 24px !important;
            overflow-x: auto !important;
        }

        .nee-page {
            background: #ffffff !important;
            color: #111111 !important;
            color-scheme: light !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .18) !important;
        }

        .nee-page,
        .nee-page *,
        .nee-page *::before,
        .nee-page *::after {
            color: #111111 !important;
            border-color: #111111;
        }

        .nee-page input,
        .nee-page textarea,
        .nee-page select {
            background: transparent !important;
            background-color: transparent !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            border-color: #111111 !important;
            box-shadow: none !important;
            color-scheme: light !important;
        }

        .nee-page input::placeholder,
        .nee-page textarea::placeholder {
            color: #6b7280 !important;
            -webkit-text-fill-color: #6b7280 !important;
            opacity: 1 !important;
        }

        .nee-page input[type="checkbox"],
        .nee-page input[type="radio"] {
            appearance: none !important;
            -webkit-appearance: none !important;
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1.2px solid #111111 !important;
            accent-color: #111111 !important;
            -webkit-text-fill-color: initial !important;
            color: #111111 !important;
        }

        .nee-page input[type="checkbox"]:checked::after,
        .nee-page input[type="radio"]:checked::after,
        .nee-check:checked::after,
        .bio-check input:checked::after,
        .app-check:checked::after {
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
        }

        html.dark .nee-wrapper,
        body.dark .nee-wrapper,
        .dark .nee-wrapper {
            background: #05070d !important;
            border-color: #27272a !important;
        }

        html.dark .nee-page,
        body.dark .nee-page,
        .dark .nee-page {
            background: #ffffff !important;
            color: #111111 !important;
            color-scheme: light !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .65) !important;
        }

        html.dark .nee-page,
        html.dark .nee-page *,
        html.dark .nee-page *::before,
        html.dark .nee-page *::after,
        body.dark .nee-page,
        body.dark .nee-page *,
        body.dark .nee-page *::before,
        body.dark .nee-page *::after,
        .dark .nee-page,
        .dark .nee-page *,
        .dark .nee-page *::before,
        .dark .nee-page *::after {
            color: #111111 !important;
            border-color: #111111 !important;
        }

        html.dark .nee-page input,
        html.dark .nee-page textarea,
        html.dark .nee-page select,
        body.dark .nee-page input,
        body.dark .nee-page textarea,
        body.dark .nee-page select,
        .dark .nee-page input,
        .dark .nee-page textarea,
        .dark .nee-page select {
            background: transparent !important;
            background-color: transparent !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            border-color: #111111 !important;
            box-shadow: none !important;
            color-scheme: light !important;
        }

        html.dark .nee-page input[type="checkbox"],
        html.dark .nee-page input[type="radio"],
        body.dark .nee-page input[type="checkbox"],
        body.dark .nee-page input[type="radio"],
        .dark .nee-page input[type="checkbox"],
        .dark .nee-page input[type="radio"] {
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1.2px solid #111111 !important;
            accent-color: #111111 !important;
            -webkit-text-fill-color: initial !important;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL FIX: Dark / Light mode using actual classes in this Blade
    | Actual wrapper = .nee-wrapper
    | Actual paper   = .nee-page
    |--------------------------------------------------------------------------
    */
    @media screen {
        .nee-wrapper {
            width: 100% !important;
            background: #f8fafc !important;
            border: 1px solid #e5e7eb !important;
            border-radius: 18px !important;
            padding: 24px !important;
            overflow-x: auto !important;
        }

        .nee-page {
            background: #ffffff !important;
            color: #111111 !important;
            color-scheme: light !important;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .18) !important;
        }

        .nee-page,
        .nee-page *,
        .nee-page *::before,
        .nee-page *::after {
            color: #111111 !important;
            border-color: #111111 !important;
        }

        .nee-page input,
        .nee-page textarea,
        .nee-page select {
            background: transparent !important;
            background-color: transparent !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            border-color: #111111 !important;
            box-shadow: none !important;
            color-scheme: light !important;
        }

        .nee-page input::placeholder,
        .nee-page textarea::placeholder {
            color: #6b7280 !important;
            -webkit-text-fill-color: #6b7280 !important;
            opacity: 1 !important;
        }

        .nee-page input[type="checkbox"],
        .nee-page input[type="radio"],
        .nee-page .nee-check {
            appearance: none !important;
            -webkit-appearance: none !important;
            width: 12px !important;
            height: 12px !important;
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1px solid #111111 !important;
            accent-color: #111111 !important;
            -webkit-text-fill-color: initial !important;
            color: #111111 !important;
            position: relative !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }

        .nee-page input[type="checkbox"]:checked::after,
        .nee-page input[type="radio"]:checked::after,
        .nee-page .nee-check:checked::after {
            content: "✓" !important;
            position: absolute !important;
            left: 1px !important;
            top: -8px !important;
            font-size: 17px !important;
            font-weight: 900 !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
        }

        html.dark .nee-wrapper,
        body.dark .nee-wrapper,
        .dark .nee-wrapper {
            background: #05070d !important;
            border-color: #27272a !important;
        }

        html.dark .nee-page,
        body.dark .nee-page,
        .dark .nee-page {
            background: #ffffff !important;
            color: #111111 !important;
            color-scheme: light !important;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .65) !important;
        }

        html.dark .nee-page,
        html.dark .nee-page *,
        html.dark .nee-page *::before,
        html.dark .nee-page *::after,
        body.dark .nee-page,
        body.dark .nee-page *,
        body.dark .nee-page *::before,
        body.dark .nee-page *::after,
        .dark .nee-page,
        .dark .nee-page *,
        .dark .nee-page *::before,
        .dark .nee-page *::after {
            color: #111111 !important;
            border-color: #111111 !important;
        }

        html.dark .nee-page input,
        html.dark .nee-page textarea,
        html.dark .nee-page select,
        body.dark .nee-page input,
        body.dark .nee-page textarea,
        body.dark .nee-page select,
        .dark .nee-page input,
        .dark .nee-page textarea,
        .dark .nee-page select {
            background: transparent !important;
            background-color: transparent !important;
            color: #111111 !important;
            -webkit-text-fill-color: #111111 !important;
            border-color: #111111 !important;
            box-shadow: none !important;
            color-scheme: light !important;
        }

        html.dark .nee-page input[type="checkbox"],
        html.dark .nee-page input[type="radio"],
        html.dark .nee-page .nee-check,
        body.dark .nee-page input[type="checkbox"],
        body.dark .nee-page input[type="radio"],
        body.dark .nee-page .nee-check,
        .dark .nee-page input[type="checkbox"],
        .dark .nee-page input[type="radio"],
        .dark .nee-page .nee-check {
            background: #ffffff !important;
            background-color: #ffffff !important;
            border: 1px solid #111111 !important;
            accent-color: #111111 !important;
            -webkit-text-fill-color: initial !important;
        }
    }

    @media print {
        .nee-wrapper {
            background: #fff;
            padding: 0;
            border-radius: 0;
        }

        .nee-page {
            box-shadow: none;
            margin: 0;
            page-break-after: always;
        }

        .nee-page:last-child {
            page-break-after: auto;
        }
    }

    @if ($isPdfExport)
        @page {
            size: A4;
            margin: 0;
        }

        html,
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: #ffffff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .nee-wrapper {
            width: 210mm !important;
            background: #ffffff !important;
            padding: 0 !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .nee-page {
            width: 794px !important;
            min-height: 1123px !important;
            height: 1123px !important;
            margin: 0 !important;
            box-shadow: none !important;
            overflow: hidden !important;
            page-break-after: always !important;
            break-after: page !important;
        }

        .nee-page:last-child {
            page-break-after: auto !important;
            break-after: auto !important;
        }

        .nee-page input {
            pointer-events: none !important;
        }
    @endif
</style>

<div class="nee-wrapper">

    {{-- PAGE 1: COVER --}}
    <div class="nee-page">
        <div class="nee-cover-border">
            <div class="nee-flex" style="justify-content: flex-end;">
                <div style="padding-top: 10px; width: 210px;">
                    {{ $bt('serial_no') }}
                    <span class="nee-line-fixed" style="width: 125px;">
                        {{ $v('application_no') }}
                    </span>
                </div>
            </div>

            <div style="height: 150px;"></div>

            <div class="nee-title-main">
                {{ $bt('application_title_line_1') }}<br>
                {{ $bt('application_title_line_2') }}
            </div>

            <div style="height: 28px;"></div>

            <div class="nee-title-mid">
                {{ $bt('competency_test') }}<br>
                {{ $bt('request_note_3', ['year' => $academicYear]) }}
            </div>

            <div class="nee-divider">────────────</div>

            <div style="width: 610px; margin: 50px auto 0;">
                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('full_name') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.name">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span class="nee-bold">{{ $bt('family_name') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.last_name">
                    </div>

                    <div class="nee-fieldline">
                        <span class="nee-bold">{{ $bt('given_names') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.first_name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('date_of_birth') }}</span>
                        <span class="nee-line">{{ $v('date_of_birth') }}</span>
                        <span>{{ $bt('gender') }}</span>
                        <span class="nee-line" style="max-width: 120px;">
                            {{ $genderText }}
                        </span>
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('birth_place_village') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.birth_place">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('current_address') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.current_address">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('exam_subject') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.major_applied">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('current_job') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.current_job">
                    </div>

                    <div class="nee-fieldline">
                        <span>{{ $bt('organization') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.workplace">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $bt('phone') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.phone">
                    </div>

                    <div class="nee-fieldline">
                        <span>{{ $bt('email') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.email">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- PAGE 2: RECEIPT --}}
    <div class="nee-page">
        @for ($copy = 1; $copy <= 2; $copy++)
            <div class="nee-receipt-copy">
                <div class="nee-flex">
                    <div class="nee-empty-left"></div>

                    <div class="nee-receipt-number">
                        {{ $bt('receipt_no') }} :
                        <span class="nee-line-fixed" style="width: 145px;">
                            {{ $v('receipt_no') }}
                        </span>
                    </div>

                    <div class="nee-photo-box">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Photo">
                        @else
                            {{ $bt('photo_placeholder') }}<br>{{ $bt('photo_size') }}
                        @endif
                    </div>
                </div>

                <div class="nee-title-mid" style="margin-top: 8px;">
                    {{ $bt('receipt_title') }}
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('khmer_name_label') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('latin_name_label') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.latin_name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('gender') }}</span>
                        <span class="nee-line" style="max-width: 80px;">
                            {{ $genderText }}
                        </span>

                        <span>{{ $bt('nationality') }}</span>
                        <span class="nee-line" style="max-width: 115px;">
                            {{ $v('nationality', __('bachelor_transfer_applications.defaults.nationality')) }}
                        </span>

                        <span>{{ $bt('date_of_birth') }}</span>
                        <span class="nee-line" style="max-width: 150px;">
                            {{ $v('date_of_birth') }}
                        </span>
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('birth_place') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.birth_place">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('organization') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.workplace">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $bt('contact_phone') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.phone">
                    </div>
                </div>

                <div class="nee-note">
                    {{ $bt('receipt_note_1') }}
                    {{ $bt('request_note_2') }} {{ $bt('request_note_3', ['year' => $academicYear]) }}។
                </div>

                <div class="nee-signature-grid">
                    <div>
                        {{ $bt('day') }}<span class="nee-line-fixed" style="width: 35px;"></span>
                        {{ $bt('month') }}<span class="nee-line-fixed" style="width: 45px;"></span>
                        {{ $bt('year') }}<span class="nee-line-fixed" style="width: 55px;"></span><br>
                        {{ $bt('signature') }}<br>
                        {{ $bt('receiver') }}
                    </div>

                    <div>
                        {{ $bt('day') }}<span class="nee-line-fixed" style="width: 35px;"></span>
                        {{ $bt('month') }}<span class="nee-line-fixed" style="width: 45px;"></span>
                        {{ $bt('year') }}<span class="nee-line-fixed" style="width: 55px;"></span><br>
                        {{ $bt('student_signature_name') }}
                    </div>
                </div>
            </div>
        @endfor
    </div>

    {{-- PAGE 3: APPLICATION --}}
    <div class="nee-page">
        <div class="nee-flex">
            <div class="nee-empty-left"></div>

            <div class="nee-kingdom">
                <div class="nee-kingdom-title">{{ $bt('kingdom') }}</div>
                <div class="nee-kingdom-title">{{ $bt('nation_religion_king') }}</div>
                <div class="nee-divider">────────</div>
            </div>

            <div class="nee-photo-box">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo">
                @else
                    {{ $bt('photo_placeholder') }}<br>{{ $bt('photo_size') }}
                @endif
            </div>
        </div>

        <div class="nee-title-small" style="margin-top: 8px;">
            {{ $bt('application_title_line_1') }}<br>
            {{ $bt('application_title_line_2') }}<br>
            {{ $bt('request_note_3', ['year' => $academicYear]) }}
        </div>

        <div class="nee-divider">────────────</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('khmer_name_label') }} :</span>
                <span class="nee-line">{{ $v('name') }}</span>
                <span>{{ $bt('english_language') }}</span>
                <span class="nee-line">{{ $v('latin_name') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('gender') }}</span>
                <span class="nee-line" style="max-width: 70px;">
                    {{ $genderText }}
                </span>

                <span>{{ $bt('nationality') }}</span>
                <span class="nee-line" style="max-width: 105px;">
                    {{ $v('nationality', __('bachelor_transfer_applications.defaults.nationality')) }}
                </span>

                <span>{{ $bt('date_of_birth') }}</span>
                <span class="nee-line" style="max-width: 145px;">
                    {{ $v('date_of_birth') }}
                </span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('birth_place') }}</span>
                <span class="nee-line">{{ $v('birth_place') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('current_address_house') }}</span>
                <span class="nee-line">{{ $v('current_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('organization') }}</span>
                <span class="nee-line">{{ $v('workplace') }}</span>
                <span>{{ $bt('position') }}</span>
                <span class="nee-line" style="max-width: 150px;">{{ $v('position') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('dear_to') }}</span>
                <span class="nee-line">
                    {{ $bt('rector') }}
                </span>
            </div>
        </div>

        <div class="nee-center nee-muol" style="margin-top: 8px;">
            {{ $bt('respect_title') }}
        </div>

        <div class="nee-note">
            {{ $bt('request_note_1') }}
            {{ $bt('request_note_2') }}
            {{ $bt('request_note_3', ['year' => $academicYear]) }}
            {{ $bt('request_note_4') }}
        </div>

        <div class="nee-note">
            {{ $bt('declare_note_1') }}
            {{ $bt('declare_note_2') }}
            {{ $bt('declare_note_3') }}
        </div>

        <div class="nee-section-title">{{ $bt('attached_documents') }}</div>

        <div class="nee-doc-list">
            <div class="nee-doc-row">
                <div>១.</div>
                <div>{{ $bt('doc_application_form') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_application_form" @checked(data_get($data, 'has_application_form'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>២.</div>
                <div>{{ $bt('biography_title') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_biography" @checked(data_get($data, 'has_biography'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៣.</div>
                <div>{{ $bt('doc_certificate') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_certificate" @checked(data_get($data, 'has_certificate'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៤.</div>
                <div>{{ $bt('doc_transcript') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_transcript" @checked(data_get($data, 'has_transcript'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៥.</div>
                <div>{{ $bt('doc_permission_letter') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_permission_letter" @checked(data_get($data, 'has_permission_letter'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៦.</div>
                <div>{{ $bt('doc_osce_result') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_osce_result" @checked(data_get($data, 'has_osce_result'))>
                    {{ $bt('copy_01') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៧.</div>
                <div>{{ $bt('doc_photo_4x6') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_photo_4x6" @checked(data_get($data, 'has_photo_4x6'))>
                    {{ $bt('photos_06') }}
                </div>
            </div>
        </div>

        <div class="nee-note">
            {{ $bt('document_note_1') }}
            {{ $bt('document_note_2') }}
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
            {{ $bt('day') }}<span class="nee-line-fixed" style="width: 38px;"></span>
            {{ $bt('month') }}<span class="nee-line-fixed" style="width: 50px;"></span>
            {{ $bt('year') }}<span class="nee-line-fixed" style="width: 55px;"></span>
        </div>

        <div class="nee-signature-grid">
            <div>
                {{ $bt('guardian_approval') }}<br>
                {{ $bt('guardian') }}
            </div>

            <div>
                {{ $bt('student_signature_name') }}
            </div>
        </div>
    </div>

    {{-- PAGE 4: BIOGRAPHY --}}
    <div class="nee-page">
        <div class="nee-flex">
            <div class="nee-empty-left"></div>

            <div class="nee-kingdom">
                <div class="nee-kingdom-title">{{ $bt('kingdom') }}</div>
                <div class="nee-kingdom-title">{{ $bt('nation_religion_king') }}</div>
                <div class="nee-divider">────────</div>
            </div>

            <div class="nee-photo-box">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo">
                @else
                    {{ $bt('photo_placeholder') }}<br>{{ $bt('photo_size') }}
                @endif
            </div>
        </div>

        <div class="nee-title-mid" style="margin-top: 4px;">
            {{ $bt('biography_title') }}
        </div>

        <div class="nee-subtitle">
            {{ $bt('biography_subtitle') }}
        </div>

        <div class="nee-section-title">{{ $bt('personal_info') }}</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('name_kh_latin') }}</span>
                <span class="nee-line">{{ $v('name') }}</span>
                <span>{{ $bt('latin_letters') }}</span>
                <span class="nee-line">{{ $v('latin_name') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('gender') }}</span>
                <label><input class="nee-check" type="checkbox" @checked($male)> {{ __('bachelor_transfer_applications.options.gender.male') }}</label>
                <label><input class="nee-check" type="checkbox" @checked($female)> {{ __('bachelor_transfer_applications.options.gender.female') }}</label>

                <span>{{ $bt('nationality') }}</span>
                <span class="nee-line" style="max-width: 100px;">{{ $v('nationality', __('bachelor_transfer_applications.defaults.nationality')) }}</span>

                <span>{{ $bt('religion') }}</span>
                <span class="nee-line" style="max-width: 95px;">{{ $v('religion') }}</span>

                <span>{{ $bt('marital_status') }}</span>
                <label><input class="nee-check" type="checkbox" @checked($single)> {{ __('bachelor_transfer_applications.options.marital_status.single') }}</label>
                <label><input class="nee-check" type="checkbox" @checked($married)> {{ __('bachelor_transfer_applications.options.marital_status.married') }}</label>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('date_of_birth') }}</span>
                <span>ទីកន្លែងកំណើត</span>
                <span class="nee-line">{{ $v('birth_place') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('current_address') }}</span>
                <span class="nee-line">{{ $v('current_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('permanent_address') }}</span>
                <span class="nee-line">{{ $v('permanent_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('phone') }}</span>
                <span class="nee-line" style="max-width: 165px;">{{ $v('phone') }}</span>
                <span>{{ $bt('email') }}</span>
                <span class="nee-line" style="max-width: 225px;">{{ $v('email') }}</span>
            </div>
        </div>

        <div class="nee-section-title">{{ $bt('family_info') }}</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('father_name') }}</span>
                <span class="nee-line">{{ $v('father_name') }}</span>
                <span>{{ $bt('birth_date') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('father_date_of_birth') }}</span>
                <span>{{ $bt('occupation') }}</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('father_occupation') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('mother_name') }}</span>
                <span class="nee-line">{{ $v('mother_name') }}</span>
                <span>{{ $bt('birth_date') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('mother_date_of_birth') }}</span>
                <span>{{ $bt('occupation') }}</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('mother_occupation') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('spouse_name') }}</span>
                <span class="nee-line">{{ $v('spouse_name') }}</span>
                <span>{{ $bt('occupation') }}</span>
                <span class="nee-line" style="max-width: 145px;">{{ $v('spouse_occupation') }}</span>
                <span>{{ $bt('phone') }}</span>
                <span class="nee-line" style="max-width: 120px;">{{ $v('spouse_phone') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $bt('guardian_contact') }}</span>
                <span class="nee-line">{{ $v('guardian_name') }}</span>
                <span>{{ $bt('relationship') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('guardian_relationship') }}</span>
                <span>{{ $bt('phone') }}</span>
                <span class="nee-line" style="max-width: 115px;">{{ $v('guardian_phone') }}</span>
            </div>
        </div>

        <div class="nee-section-title">{{ $bt('education_history') }}</div>

        <table class="nee-table">
            <thead>
                <tr>
                    <th style="width: 26%;">{{ $bt('school') }}</th>
                    <th style="width: 22%;">{{ $bt('degree_major') }}</th>
                    <th style="width: 18%;">{{ $bt('study_location') }}</th>
                    <th style="width: 14%;">{{ $bt('duration') }}</th>
                    <th style="width: 20%;">{{ $bt('certificate') }}</th>
                </tr>
            </thead>

            <tbody>
                @for ($i = 0; $i < 4; $i++)
                    <tr>
                        <td>{{ data_get($data, "education_histories.$i.school_name") }}</td>
                        <td>
                            {{ data_get($data, "education_histories.$i.degree") }}
                            {{ data_get($data, "education_histories.$i.major") ? ' - ' . data_get($data, "education_histories.$i.major") : '' }}
                        </td>
                        <td>{{ data_get($data, "education_histories.$i.location") }}</td>
                        <td>
                            {{ data_get($data, "education_histories.$i.start_year") }}
                            {{ data_get($data, "education_histories.$i.end_year") ? ' - ' . data_get($data, "education_histories.$i.end_year") : '' }}
                        </td>
                        <td>{{ data_get($data, "education_histories.$i.certificate") }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="nee-section-title">{{ $bt('work_history') }}</div>

        <table class="nee-table">
            <thead>
                <tr>
                    <th style="width: 26%;">{{ $bt('institution') }}</th>
                    <th style="width: 18%;">{{ $bt('position') }}</th>
                    <th style="width: 18%;">{{ $bt('department') }}</th>
                    <th style="width: 18%;">{{ $bt('duration') }}</th>
                    <th style="width: 20%;">{{ $bt('other') }}</th>
                </tr>
            </thead>

            <tbody>
                @for ($i = 0; $i < 3; $i++)
                    <tr>
                        <td>{{ data_get($data, "work_histories.$i.institution") }}</td>
                        <td>{{ data_get($data, "work_histories.$i.position") }}</td>
                        <td>{{ data_get($data, "work_histories.$i.department") }}</td>
                        <td>
                            {{ data_get($data, "work_histories.$i.start_date") }}
                            {{ data_get($data, "work_histories.$i.end_date") ? ' - ' . data_get($data, "work_histories.$i.end_date") : '' }}
                        </td>
                        <td>{{ data_get($data, "work_histories.$i.note") }}</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="nee-note">
            {{ $bt('biography_declare_1') }}
            {{ $bt('biography_declare_2') }}
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
            {{ $bt('day') }}<span class="nee-line-fixed" style="width: 38px;"></span>
            {{ $bt('month') }}<span class="nee-line-fixed" style="width: 50px;"></span>
            {{ $bt('year') }}<span class="nee-line-fixed" style="width: 55px;"></span>
        </div>

        <div class="nee-signature-grid">
            <div></div>

            <div>
                {{ $bt('student_signature_name') }}
            </div>
        </div>
    </div>
</div>