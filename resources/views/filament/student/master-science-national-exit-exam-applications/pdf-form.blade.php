@php
    /*
    |--------------------------------------------------------------------------
    | Master Science National Exit Exam Application - PDF/Form Preview
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

    $photoPathValue = $v('photo_path');
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

    $academicYear = $v('academic_year', '២០១៩ - ២០២០');

    $t = fn (string $key): string => __('master_science_national_exit_exam_applications.pdf.' . $key);
    $genderText = $male
        ? __('master_science_national_exit_exam_applications.options.gender.male')
        : ($female ? __('master_science_national_exit_exam_applications.options.gender.female') : '');
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
        border-radius: 16px;
        border: 1px solid #d8dee8;
        overflow-x: auto;
        transition: background-color .2s ease, border-color .2s ease;
    }

    .nee-page {
        width: 794px;
        min-height: 1123px;
        margin: 0 auto 24px;
        background: var(--nee-paper) !important;
        color: var(--nee-text) !important;
        box-shadow: var(--nee-shadow);
        color-scheme: light;
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
    | Dark Mode / Light Mode Preview
    |--------------------------------------------------------------------------
    | Filament dark mode changes the surrounding UI. The document paper must
    | stay white with black text, like the document-requests preview.
    */

    html.dark .nee-wrapper,
    body.dark .nee-wrapper,
    .dark .nee-wrapper,
    [data-theme="dark"] .nee-wrapper {
        background: #05070d !important;
        border-color: #27272a !important;
    }

    html.dark .nee-page,
    body.dark .nee-page,
    .dark .nee-page,
    [data-theme="dark"] .nee-page {
        background: #ffffff !important;
        color: #111111 !important;
        color-scheme: light !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .68) !important;
    }

    html.dark .nee-page *,
    html.dark .nee-page *::before,
    html.dark .nee-page *::after,
    body.dark .nee-page *,
    body.dark .nee-page *::before,
    body.dark .nee-page *::after,
    .dark .nee-page *,
    .dark .nee-page *::before,
    .dark .nee-page *::after,
    [data-theme="dark"] .nee-page *,
    [data-theme="dark"] .nee-page *::before,
    [data-theme="dark"] .nee-page *::after {
        color: #111111 !important;
    }

    html.dark .nee-line,
    html.dark .nee-line-fixed,
    html.dark .nee-input,
    body.dark .nee-line,
    body.dark .nee-line-fixed,
    body.dark .nee-input,
    .dark .nee-line,
    .dark .nee-line-fixed,
    .dark .nee-input,
    [data-theme="dark"] .nee-line,
    [data-theme="dark"] .nee-line-fixed,
    [data-theme="dark"] .nee-input {
        border-bottom-color: #111111 !important;
    }

    html.dark .nee-cover-border,
    html.dark .nee-photo-box,
    html.dark .nee-check,
    html.dark .nee-table th,
    html.dark .nee-table td,
    body.dark .nee-cover-border,
    body.dark .nee-photo-box,
    body.dark .nee-check,
    body.dark .nee-table th,
    body.dark .nee-table td,
    .dark .nee-cover-border,
    .dark .nee-photo-box,
    .dark .nee-check,
    .dark .nee-table th,
    .dark .nee-table td,
    [data-theme="dark"] .nee-cover-border,
    [data-theme="dark"] .nee-photo-box,
    [data-theme="dark"] .nee-check,
    [data-theme="dark"] .nee-table th,
    [data-theme="dark"] .nee-table td {
        border-color: #111111 !important;
    }

    html.dark .nee-input,
    html.dark .nee-page input,
    html.dark .nee-page textarea,
    html.dark .nee-page select,
    body.dark .nee-input,
    body.dark .nee-page input,
    body.dark .nee-page textarea,
    body.dark .nee-page select,
    .dark .nee-input,
    .dark .nee-page input,
    .dark .nee-page textarea,
    .dark .nee-page select,
    [data-theme="dark"] .nee-input,
    [data-theme="dark"] .nee-page input,
    [data-theme="dark"] .nee-page textarea,
    [data-theme="dark"] .nee-page select {
        background: transparent !important;
        color: #111111 !important;
        -webkit-text-fill-color: #111111 !important;
        border-color: #111111 !important;
        box-shadow: none !important;
        color-scheme: light !important;
    }

    html.dark .nee-check,
    body.dark .nee-check,
    .dark .nee-check,
    [data-theme="dark"] .nee-check {
        background-color: #ffffff !important;
        accent-color: #111111 !important;
        -webkit-text-fill-color: initial !important;
    }

    html.dark .nee-input::placeholder,
    html.dark .nee-page input::placeholder,
    body.dark .nee-input::placeholder,
    body.dark .nee-page input::placeholder,
    .dark .nee-input::placeholder,
    .dark .nee-page input::placeholder,
    [data-theme="dark"] .nee-input::placeholder,
    [data-theme="dark"] .nee-page input::placeholder {
        color: #6b7280 !important;
        -webkit-text-fill-color: #6b7280 !important;
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
                    {{ $t('serial_no') }}
                    <span class="nee-line-fixed" style="width: 125px;">
                        {{ $v('application_no') }}
                    </span>
                </div>
            </div>

            <div style="height: 150px;"></div>

            <div class="nee-title-main">
                {{ $t('application_title') }}<br>
                {{ $t('degree_title') }}
            </div>

            <div style="height: 28px;"></div>

            <div class="nee-title-mid">
                {{ $t('test_title') }}<br>
                {{ $t('exam_session_year') }} {{ $academicYear }}
            </div>

            <div class="nee-divider">────────────</div>

            <div style="width: 610px; margin: 50px auto 0;">
                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('full_name') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.name">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span class="nee-bold">Family Name:</span>
                        <input class="nee-input" type="text" wire:model.blur="data.last_name">
                    </div>

                    <div class="nee-fieldline">
                        <span class="nee-bold">Given Names:</span>
                        <input class="nee-input" type="text" wire:model.blur="data.first_name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('dob') }}</span>
                        <span class="nee-line">{{ $v('date_of_birth') }}</span>
                        <span>{{ $t('gender') }}</span>
                        <span class="nee-line" style="max-width: 120px;">
                            {{ $genderText }}
                        </span>
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('birth_place_full') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.birth_place">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('current_address') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.current_address">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('exam_subject') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.major_applied">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('current_job') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.current_job">
                    </div>

                    <div class="nee-fieldline">
                        <span>{{ $t('workplace') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.workplace">
                    </div>
                </div>

                <div class="nee-grid-2 nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('phone') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.phone">
                    </div>

                    <div class="nee-fieldline">
                        <span>{{ $t('email') }}</span>
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
                        {{ $t('receipt_no') }}
                        <span class="nee-line-fixed" style="width: 145px;">
                            {{ $v('receipt_no') }}
                        </span>
                    </div>

                    <div class="nee-photo-box">
                        @if ($photoUrl)
                            <img src="{{ $photoUrl }}" alt="Photo">
                        @else
                            {!! $t('photo_placeholder') !!}
                        @endif
                    </div>
                </div>

                <div class="nee-title-mid" style="margin-top: 8px;">
                    {{ $t('receipt_title') }}
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('full_name_kh') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('latin_name_en') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.latin_name">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $t('gender') }}</span>
                        <span class="nee-line" style="max-width: 80px;">
                            {{ $genderText }}
                        </span>

                        <span>{{ $t('nationality') }}</span>
                        <span class="nee-line" style="max-width: 115px;">
                            {{ $v('nationality', __('master_science_national_exit_exam_applications.defaults.khmer')) }}
                        </span>

                        <span>{{ $t('dob_plain') }}</span>
                        <span class="nee-line" style="max-width: 150px;">
                            {{ $v('date_of_birth') }}
                        </span>
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>- {{ $t('birth_place') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.birth_place">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('current_workplace') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.workplace">
                    </div>
                </div>

                <div class="nee-row">
                    <div class="nee-fieldline">
                        <span>{{ $t('contact_phone') }}</span>
                        <input class="nee-input" type="text" wire:model.blur="data.phone">
                    </div>
                </div>

                <div class="nee-note">
                    {{ $t('receipt_note') }}
                    {{ $t('degree_title') }} {{ $t('exam_session_year') }} {{ $academicYear }}។
                </div>

                <div class="nee-signature-grid">
                    <div>
                        {{ $t('date_day') }}<span class="nee-line-fixed" style="width: 35px;"></span>
                        {{ $t('date_month') }}<span class="nee-line-fixed" style="width: 45px;"></span>
                        {{ $t('date_year') }}<span class="nee-line-fixed" style="width: 55px;"></span><br>
                        {{ $t('signature') }}<br>
                        {{ $t('receiver') }}
                    </div>

                    <div>
                        {{ $t('date_day') }}<span class="nee-line-fixed" style="width: 35px;"></span>
                        {{ $t('date_month') }}<span class="nee-line-fixed" style="width: 45px;"></span>
                        {{ $t('date_year') }}<span class="nee-line-fixed" style="width: 55px;"></span><br>
                        {{ $t('candidate_signature') }}
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
                <div class="nee-kingdom-title">{{ $t('kingdom') }}</div>
                <div class="nee-kingdom-title">{{ $t('nation_religion_king') }}</div>
                <div class="nee-divider">────────</div>
            </div>

            <div class="nee-photo-box">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo">
                @else
                    {!! $t('photo_placeholder') !!}
                @endif
            </div>
        </div>

        <div class="nee-title-small" style="margin-top: 8px;">
            {{ $t('application_title') }}<br>
            {{ $t('degree_title') }}<br>
            {{ $t('exam_session_year') }} {{ $academicYear }}
        </div>

        <div class="nee-divider">────────────</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('applicant_name_kh') }}</span>
                <span class="nee-line">{{ $v('name') }}</span>
                <span>{{ $t('english_language') }}</span>
                <span class="nee-line">{{ $v('latin_name') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('gender') }}</span>
                <span class="nee-line" style="max-width: 70px;">
                    {{ $genderText }}
                </span>

                <span>{{ $t('nationality') }}</span>
                <span class="nee-line" style="max-width: 105px;">
                    {{ $v('nationality', __('master_science_national_exit_exam_applications.defaults.khmer')) }}
                </span>

                <span>{{ $t('dob_plain') }}</span>
                <span class="nee-line" style="max-width: 145px;">
                    {{ $v('date_of_birth') }}
                </span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('birth_place') }}</span>
                <span class="nee-line">{{ $v('birth_place') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('current_address_house') }}</span>
                <span class="nee-line">{{ $v('current_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('current_workplace') }}</span>
                <span class="nee-line">{{ $v('workplace') }}</span>
                <span>{{ $t('position') }}</span>
                <span class="nee-line" style="max-width: 150px;">{{ $v('position') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('respect_to') }}</span>
                <span class="nee-line">
                    {{ $t('rector') }}
                </span>
            </div>
        </div>

        <div class="nee-center nee-muol" style="margin-top: 8px;">
            {{ $t('respect_title') }}
        </div>

        <div class="nee-note">
            {{ __('master_science_national_exit_exam_applications.pdf.request_paragraph', ['year' => $academicYear]) }}
        </div>

        <div class="nee-note">
            {{ $t('truth_paragraph') }}
        </div>

        <div class="nee-section-title">{{ $t('documents_title') }}</div>

        <div class="nee-doc-list">
            <div class="nee-doc-row">
                <div>១.</div>
                <div>{{ $t('doc_application_form') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_application_form" @checked(data_get($data, 'has_application_form'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>២.</div>
                <div>{{ $t('doc_biography') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_biography" @checked(data_get($data, 'has_biography'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៣.</div>
                <div>{{ $t('doc_certificate') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_certificate" @checked(data_get($data, 'has_certificate'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៤.</div>
                <div>{{ $t('doc_transcript') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_transcript" @checked(data_get($data, 'has_transcript'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៥.</div>
                <div>{{ $t('doc_permission_letter') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_permission_letter" @checked(data_get($data, 'has_permission_letter'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៦.</div>
                <div>{{ $t('doc_osce_result') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_osce_result" @checked(data_get($data, 'has_osce_result'))>
                    {{ $t('one_copy') }}
                </div>
            </div>

            <div class="nee-doc-row">
                <div>៧.</div>
                <div>{{ $t('doc_photo_4x6') }}</div>
                <div>
                    <input class="nee-check" type="checkbox" wire:model.live="data.has_photo_4x6" @checked(data_get($data, 'has_photo_4x6'))>
                    {{ $t('six_photos') }}
                </div>
            </div>
        </div>

        <div class="nee-note">
            {{ $t('document_note') }}
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
            {{ $t('date_day') }}<span class="nee-line-fixed" style="width: 38px;"></span>
            {{ $t('date_month') }}<span class="nee-line-fixed" style="width: 50px;"></span>
            {{ $t('date_year') }}<span class="nee-line-fixed" style="width: 55px;"></span>
        </div>

        <div class="nee-signature-grid">
            <div>
                {{ $t('approved_by') }}<br>
                {{ $t('guardian') }}
            </div>

            <div>
                {{ $t('candidate_signature') }}
            </div>
        </div>
    </div>

    {{-- PAGE 4: BIOGRAPHY --}}
    <div class="nee-page">
        <div class="nee-flex">
            <div class="nee-empty-left"></div>

            <div class="nee-kingdom">
                <div class="nee-kingdom-title">{{ $t('kingdom') }}</div>
                <div class="nee-kingdom-title">{{ $t('nation_religion_king') }}</div>
                <div class="nee-divider">────────</div>
            </div>

            <div class="nee-photo-box">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Photo">
                @else
                    {!! $t('photo_placeholder') !!}
                @endif
            </div>
        </div>

        <div class="nee-title-mid" style="margin-top: 4px;">
            {{ $t('biography_title') }}
        </div>

        <div class="nee-subtitle">
            {{ $t('bio_subtitle') }}
        </div>

        <div class="nee-section-title">{{ $t('personal_info') }}</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('full_name_bio') }}</span>
                <span class="nee-line">{{ $v('name') }}</span>
                <span>{{ $t('latin_alphabet') }}</span>
                <span class="nee-line">{{ $v('latin_name') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('gender') }}</span>
                <label><input class="nee-check" type="checkbox" @checked($male)> {{ __('master_science_national_exit_exam_applications.options.gender.male') }}</label>
                <label><input class="nee-check" type="checkbox" @checked($female)> {{ __('master_science_national_exit_exam_applications.options.gender.female') }}</label>

                <span>{{ $t('nationality') }}</span>
                <span class="nee-line" style="max-width: 100px;">{{ $v('nationality', __('master_science_national_exit_exam_applications.defaults.khmer')) }}</span>

                <span>{{ $t('religion') }}</span>
                <span class="nee-line" style="max-width: 95px;">{{ $v('religion') }}</span>

                <span>{{ $t('status') }}</span>
                <label><input class="nee-check" type="checkbox" @checked($single)> {{ $t('single') }}</label>
                <label><input class="nee-check" type="checkbox" @checked($married)> {{ $t('married') }}</label>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('dob') }}</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('date_of_birth') }}</span>
                <span>{{ $t('birth_place') }}</span>
                <span class="nee-line">{{ $v('birth_place') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('current_address') }}</span>
                <span class="nee-line">{{ $v('current_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('permanent_address') }}</span>
                <span class="nee-line">{{ $v('permanent_address') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>- {{ $t('phone') }}</span>
                <span class="nee-line" style="max-width: 165px;">{{ $v('phone') }}</span>
                <span>{{ $t('email') }}</span>
                <span class="nee-line" style="max-width: 225px;">{{ $v('email') }}</span>
            </div>
        </div>

        <div class="nee-section-title">{{ $t('family_info') }}</div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('father_name') }}</span>
                <span class="nee-line">{{ $v('father_name') }}</span>
                <span>{{ $t('birth_date') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('father_date_of_birth') }}</span>
                <span>{{ $t('occupation') }}</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('father_occupation') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('mother_name') }}</span>
                <span class="nee-line">{{ $v('mother_name') }}</span>
                <span>{{ $t('birth_date') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('mother_date_of_birth') }}</span>
                <span>{{ $t('occupation') }}</span>
                <span class="nee-line" style="max-width: 135px;">{{ $v('mother_occupation') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('spouse_name') }}</span>
                <span class="nee-line">{{ $v('spouse_name') }}</span>
                <span>{{ $t('occupation') }}</span>
                <span class="nee-line" style="max-width: 145px;">{{ $v('spouse_occupation') }}</span>
                <span>{{ $t('phone') }}</span>
                <span class="nee-line" style="max-width: 120px;">{{ $v('spouse_phone') }}</span>
            </div>
        </div>

        <div class="nee-row">
            <div class="nee-fieldline">
                <span>{{ $t('guardian_contact') }}</span>
                <span class="nee-line">{{ $v('guardian_name') }}</span>
                <span>{{ $t('relationship') }}</span>
                <span class="nee-line" style="max-width: 105px;">{{ $v('guardian_relationship') }}</span>
                <span>{{ $t('phone') }}</span>
                <span class="nee-line" style="max-width: 115px;">{{ $v('guardian_phone') }}</span>
            </div>
        </div>

        <div class="nee-section-title">{{ $t('education_history') }}</div>

        <table class="nee-table">
            <thead>
                <tr>
                    <th style="width: 26%;">{{ $t('school') }}</th>
                    <th style="width: 22%;">{{ $t('degree_major') }}</th>
                    <th style="width: 18%;">{{ $t('study_place') }}</th>
                    <th style="width: 14%;">{{ $t('duration') }}</th>
                    <th style="width: 20%;">{{ $t('certificate') }}</th>
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

        <div class="nee-section-title">{{ $t('work_history') }}</div>

        <table class="nee-table">
            <thead>
                <tr>
                    <th style="width: 26%;">{{ $t('institution') }}</th>
                    <th style="width: 18%;">{{ $t('job_position') }}</th>
                    <th style="width: 18%;">{{ $t('department') }}</th>
                    <th style="width: 18%;">{{ $t('duration') }}</th>
                    <th style="width: 20%;">{{ $t('other') }}</th>
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
            {{ $t('bio_truth_paragraph') }}
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 14px;">
            {{ $t('date_day') }}<span class="nee-line-fixed" style="width: 38px;"></span>
            {{ $t('date_month') }}<span class="nee-line-fixed" style="width: 50px;"></span>
            {{ $t('date_year') }}<span class="nee-line-fixed" style="width: 55px;"></span>
        </div>

        <div class="nee-signature-grid">
            <div></div>

            <div>
                {{ $t('candidate_signature') }}
            </div>
        </div>
    </div>
</div>