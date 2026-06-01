@php
    /*
    |--------------------------------------------------------------------------
    | Shared PDF/Form view
    |--------------------------------------------------------------------------
    | Live Filament form:
    | - Uses $this->data from the Livewire resource page.
    |
    | PDF export:
    | - Pass $record and $isPdfExport = true from pdf-export.blade.php.
    | - The same HTML/CSS is reused, then JS fills wire:model fields from $record.
    */

    $isPdfExport = $isPdfExport ?? false;

    if (isset($record) && $record) {
        $data = $record->toArray();
    } else {
        $data = $data ?? ($this->data ?? []);
    }

    $logoPath = public_path('images/UHS_logo.png');

    $logoUrl = $isPdfExport
        ? (file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : '')
        : asset('images/UHS_logo.png');

    $photo = data_get($data, 'photo');
    $photoUrl = null;

    if ($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $photoUrl = $photo->temporaryUrl();
    } elseif (is_string($photo) && filled($photo)) {
        if ($isPdfExport) {
            $photoPath = storage_path('app/public/' . $photo);

            if (file_exists($photoPath)) {
                $photoUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($photoPath));
            }
        } else {
            $photoUrl = \Illuminate\Support\Facades\Storage::url($photo);
        }
    }

    $requestType = data_get($data, 'request_type');

    $locale = app()->getLocale();

    $t = function (string $km, string $en) use ($locale): string {
        return $locale === 'km' ? $km : $en;
    };

    $pair = function (string $km, string $en) use ($locale): string {
        return $locale === 'km'
            ? $km . '<br><span class="uhs-en">' . e($en) . '</span>'
            : $en . '<br><span class="uhs-en">' . e($km) . '</span>';
    };

    $inlinePair = function (string $km, string $en) use ($locale): string {
        return $locale === 'km'
            ? $km . ' <span class="uhs-en">(' . e($en) . ')</span>'
            : $en . ' <span class="uhs-en">(' . e($km) . ')</span>';
    };

@endphp

<style>
    :root {
        --uhs-shell-bg: #eef2f7;
        --uhs-shell-border: #d6dce5;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #1f2937;
        --uhs-muted: #4b5563;
        --uhs-input-bg: #ffffff;
        --uhs-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
    }

    html.dark {
        --uhs-shell-bg: #000000;
        --uhs-shell-border: #374151;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #1f2937;
        --uhs-muted: #4b5563;
        --uhs-input-bg: #ffffff;
        --uhs-shadow: 0 18px 45px rgba(0, 0, 0, 0.55);
    }

    .uhs-wrapper {
        background: var(--uhs-shell-bg);
        border: 1px solid var(--uhs-shell-border);
        border-radius: 18px;
        overflow-x: auto;
    }

    .uhs-photo-upload-label {
        width: 100%;
        height: 100%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .uhs-photo-upload-text {
        text-align: center;
        line-height: 1.7;
        font-weight: 600;
    }

    .uhs-photo-file-input { display: none; }

    .uhs-page {
        width: 900px;
        min-height: 1240px;
        margin: 0 auto 20px;
        background: var(--uhs-page-bg);
        color: var(--uhs-page-text);
        box-shadow: var(--uhs-shadow);
        padding: 22px 32px;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
        font-size: 11.5px;
        line-height: 1.25;
    }

    .uhs-page * { box-sizing: border-box; }

    .uhs-page input,
    .uhs-page textarea { color: #111111 !important; }

    /* HEADER */
    .uhs-header {
        display: grid;
        grid-template-columns: 215px 1fr 125px;
        gap: 10px;
        align-items: start;
    }

    .uhs-logo-area {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .uhs-logo {
        width: 74px;
        height: 74px;
        object-fit: contain;
    }

    .uhs-logo-title {
        font-size: 29px;
        font-weight: 900;
        letter-spacing: 1px;
        line-height: 1;
    }

    .uhs-logo-subtitle {
        font-size: 9.5px;
        margin-top: 4px;
        line-height: 1.35;
    }

    .uhs-center-block {
        text-align: center;
        padding-top: 4px;
    }

    .uhs-title-kh {
        font-size: 20px;
        font-weight: 900;
        line-height: 1.2;
    }

    .uhs-title-en {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.8px;
        margin-top: 3px;
    }

    .uhs-id-row {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 8px;
    }

    .uhs-id-label {
        font-size: 11px;
        text-align: right;
        white-space: nowrap;
        line-height: 1.4;
    }

    .uhs-photo-box {
        width: 125px;
        height: 138px;
        border: 1.5px solid var(--uhs-line);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 12px;
        overflow: hidden;
    }

    .uhs-photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Check note */
    .uhs-check-note {
        font-size: 10.5px;
        margin: 5px 0 3px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    /* Box-letter input (for Student ID) */
    .uhs-box-letter {
        height: 30px;
        border: 1.5px solid var(--uhs-line);
        background-image: linear-gradient(to right, transparent 0, transparent 22px, var(--uhs-line) 23px);
        background-size: 23px 100%;
        background-color: var(--uhs-input-bg);
        font-size: 15px;
        letter-spacing: 10px;
        padding-left: 5px;
        outline: none;
        width: 220px;
    }

    /* Tables */
    .uhs-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .uhs-table td,
    .uhs-table th {
        border: 1.4px solid var(--uhs-line);
        padding: 3px 5px;
        vertical-align: middle;
    }

    .uhs-label {
        font-size: 10.5px;
        font-weight: 400;
        line-height: 1.2;
    }

    .uhs-en {
        font-family: Arial, sans-serif;
        font-size: 8.5px;
        color: var(--uhs-muted);
    }

    .uhs-input {
        display: block;
        width: 100%;
        height: 20px;
        border: 0;
        border-bottom: 1px dotted var(--uhs-line);
        background: transparent;
        outline: none;
        font-size: 11.5px;
        padding: 0 2px;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
    }

    .uhs-input-boxes {
        display: block;
        width: 100%;
        height: 28px;
        border: 0;
        background-image: linear-gradient(to right, transparent 0, transparent 23px, var(--uhs-line) 24px);
        background-size: 24px 100%;
        background-color: transparent;
        outline: none;
        font-size: 17px;
        line-height: 28px;
        letter-spacing: 0.33em;
        padding: 0 6px;
        font-family: "Courier New", monospace;
        text-transform: uppercase;
        white-space: nowrap;
        overflow: hidden;
    }

    .uhs-input-boxes.is-date {
        background-image: linear-gradient(to right, transparent 0, transparent 26px, var(--uhs-line) 27px);
        background-size: 27px 100%;
        font-size: 16px;
        letter-spacing: 0.4em;
        padding-left: 8px;
        font-variant-numeric: tabular-nums;
    }

    .uhs-radio-row {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .uhs-check-label {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 11px;
        white-space: nowrap;
    }

    .uhs-page input[type="radio"],
    .uhs-page input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        border: 1.5px solid var(--uhs-line);
        background: #ffffff;
        border-radius: 0;
        margin: 0;
        position: relative;
        flex: 0 0 auto;
        cursor: pointer;
    }

    .uhs-page input[type="radio"]:checked::after,
    .uhs-page input[type="checkbox"]:checked::after {
        content: "✓";
        position: absolute;
        left: 1px;
        top: -5px;
        font-size: 17px;
        font-weight: 900;
        color: #111111;
    }

    .uhs-section-title {
        text-align: center;
        font-weight: 700;
        margin: 8px 0 4px;
        font-size: 12px;
        line-height: 1.5;
    }

    /* Request grid */
    .uhs-request-grid {
        display: grid;
        grid-template-columns: 38% 62%;
        border: 1.4px solid var(--uhs-line);
        margin-top: 6px;
    }

    .uhs-request-left {
        border-right: 1.4px solid var(--uhs-line);
    }

    .uhs-request-left-title,
    .uhs-language-title {
        border-bottom: 1.4px solid var(--uhs-line);
        padding: 4px 6px;
        font-weight: 500;
        font-size: 11.5px;
    }

    .uhs-request-option {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        min-height: 30px;
        padding: 4px 10px;
        font-size: 11px;
        border-bottom: 1px solid #e5e7eb;
        line-height: 1.5;
    }

    .uhs-request-option:last-child { border-bottom: none; }
    .uhs-request-option input { margin-top: 2px; }

    .uhs-language-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .uhs-language-table td,
    .uhs-language-table th {
        border: 1.4px solid var(--uhs-line);
        padding: 3px 4px;
        vertical-align: middle;
        font-size: 11px;
        text-align: center;
    }

    .uhs-number-input {
        width: 45px;
        height: 18px;
        border: 0;
        border-bottom: 1px dotted var(--uhs-line);
        background: transparent;
        outline: none;
        text-align: center;
        font-size: 11px;
    }

    .uhs-note-text {
        margin-top: 8px;
        font-size: 11px;
        line-height: 1.7;
        text-align: justify;
    }

    .uhs-student-type-options {
        display: flex;
        align-items: flex-start;
        gap: 18px;
        margin-top: 4px;
        flex-wrap: nowrap;
    }

    .uhs-student-type-option {
        display: inline-flex;
        align-items: flex-start;
        gap: 8px;
        white-space: nowrap;
    }

    .uhs-student-type-option .uhs-en {
        display: block;
        margin-top: 2px;
    }

    .uhs-signature-row {
        display: grid;
        grid-template-columns: 1fr 250px;
        gap: 16px;
        margin-top: 8px;
        align-items: start;
    }

    .uhs-signature {
        text-align: center;
        line-height: 1.9;
        font-size: 11.5px;
    }

    .uhs-signature-date-line {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 5px;
        white-space: nowrap;
        flex-wrap: nowrap;
        line-height: 1.4;
    }

    .uhs-dotted-line {
        border-bottom: 1px dotted var(--uhs-line);
        display: inline-block;
        min-width: 110px;
        height: 15px;
        vertical-align: bottom;
    }

    /* Inline input to replace dotted ........ areas without changing your layout */
    .uhs-inline-input {
        display: inline-block;
        height: 18px;
        border: 0;
        border-bottom: 1px dotted var(--uhs-line);
        background: transparent;
        outline: none;
        padding: 0 2px;
        text-align: center;
        font-size: 11px;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
        vertical-align: baseline;
    }

    .uhs-inline-input.is-xs { width: 34px; }
    .uhs-inline-input.is-sm { width: 54px; }
    .uhs-inline-input.is-md { width: 88px; }
    .uhs-inline-input.is-lg { width: 180px; }
    .uhs-inline-input.is-office-line { width: 430px; max-width: calc(100% - 130px); }
    .uhs-inline-input.is-signature-name { width: 170px; margin-top: 8px; }

    /* Office */
    .uhs-office {
        margin-top: 26px;
        border-top: 2px solid var(--uhs-line);
        padding-top: 6px;
    }

    .uhs-office-title {
        font-weight: 800;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .uhs-office-grid {
        display: grid;
        grid-template-columns: 1fr 155px;
        gap: 0;
        border: 1.4px solid var(--uhs-line);
    }

    .uhs-office-left {
        padding: 6px 8px;
        border-right: 1.4px solid var(--uhs-line);
    }

    .uhs-stamp-box {
        min-height: 110px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        padding-bottom: 8px;
        font-size: 11px;
        text-align: center;
    }

    /* Page 2 */
    .uhs-page-two-title {
        text-align: center;
        font-weight: 800;
        font-size: 14px;
        margin-bottom: 14px;
    }

    .uhs-info-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 11.5px;
    }

    .uhs-info-table td,
    .uhs-info-table th {
        border: 1.4px solid var(--uhs-line);
        padding: 6px 5px;
        vertical-align: middle;
        text-align: center;
    }

    .uhs-info-table th { font-weight: 700; }

    .uhs-info-left { text-align: left !important; line-height: 1.35; }

    .uhs-shade { background: #cfcfcf; }

    .uhs-price-title {
        text-align: center;
        font-weight: 800;
        font-size: 14px;
        margin: 26px 0 8px;
    }

    .uhs-notes {
        margin-top: 26px;
        font-size: 11.5px;
        line-height: 1.7;
    }

    .uhs-page-gap { height: 24px; }



    /* Page 2: match original scanned attachment/pricing page */
    .uhs-page-two-original {
        padding: 22px 34px 28px;
    }

    .uhs-page-two-original .uhs-page-two-title {
        text-align: center;
        font-weight: 800;
        font-size: 13px;
        line-height: 1.3;
        margin: 0 0 8px;
    }

    .uhs-page2-main-table,
    .uhs-page2-price-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        color: var(--uhs-page-text);
    }

    .uhs-page2-main-table th,
    .uhs-page2-main-table td {
        border: 1.25px solid var(--uhs-line);
        padding: 3px 3px;
        vertical-align: middle;
        text-align: center;
        font-size: 9.7px;
        line-height: 1.35;
        height: 31px;
    }

    .uhs-page2-main-table thead th {
        font-weight: 800;
        height: 50px;
    }

    .uhs-page2-main-table thead tr:first-child th {
        height: 42px;
    }

    .uhs-page2-main-table .uhs-doc-type {
        text-align: left;
        padding-left: 7px;
        padding-right: 5px;
        line-height: 1.3;
    }

    .uhs-page2-main-table .uhs-doc-note {
        display: block;
        font-size: 8.2px;
        font-style: italic;
        line-height: 1.25;
        margin-top: 1px;
    }

    .uhs-page2-shade {
        background: #c9c9c9;
    }

    .uhs-page2-check {
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-weight: 700;
        line-height: 1;
    }

    .uhs-page2-footnotes {
        font-size: 9.7px;
        line-height: 1.45;
        margin: 10px 0 15px 0px;
    }

    .uhs-page2-price-title {
        text-align: center;
        font-weight: 800;
        font-size: 12.5px;
        line-height: 1.3;
        margin: 0 0 8px;
    }

    .uhs-page2-price-table th,
    .uhs-page2-price-table td {
        border: 1.25px solid var(--uhs-line);
        padding: 5px 6px;
        vertical-align: middle;
        text-align: center;
        font-size: 10px;
        line-height: 1.35;
        height: 30px;
    }

    .uhs-page2-price-table th {
        font-weight: 800;
    }

    .uhs-page2-price-table .uhs-price-doc {
        text-align: left;
        padding-left: 10px;
    }

    .uhs-page2-price-note-row td {
        text-align: left;
        font-size: 9.7px;
        line-height: 1.5;
        padding: 5px 8px;
    }

    .uhs-page2-guide {
        margin-top: 10px;
        font-size: 10px;
        line-height: 1.7;
    }

    .uhs-page2-guide-title {
        display: inline-block;
        font-weight: 800;
        margin-bottom: 3px;
    }

    .uhs-page2-guide {
        font-family: "Khmer OS Battambang", "Noto Sans Khmer", sans-serif;
        font-size: 10px;
        line-height: 1.85;
        color: #111;
        text-align: left;
    }

    .uhs-page2-guide-title {
        font-weight: 700;
        font-size: 10px;
        text-decoration: underline;
    }

    .uhs-guide-row {
        padding-left: 30px;
    }

    .uhs-guide-sub {
        padding-left: 60px;
        font-weight: 600;
        line-height: 1.9;
    }

    @media print {
        .uhs-wrapper {
            background: #ffffff;
            border: none;
            padding: 0;
        }
        .uhs-page {
            box-shadow: none;
            margin: 0;
            page-break-after: always;
        }
    }

    @if ($isPdfExport)
    @page {
        size: A4;
        margin: 0;
    }

    html,
    body {
        width: 210mm !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .uhs-wrapper {
        width: 210mm !important;
        background: #ffffff !important;
        border: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
    }

    .uhs-page-gap {
        display: none !important;
        height: 0 !important;
    }

    /*
       Keep the same 900px design from your live pdf-form,
       then scale it down to fit exactly one A4 page.
       This avoids redesigning the form and keeps the style consistent.
    */
    .uhs-page {
        width: 900px !important;
        min-height: 1240px !important;
        height: 1240px !important;
        margin: 0 !important;
        padding: 22px 32px !important;
        background: #ffffff !important;
        color: #111827 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        overflow: hidden !important;
        page-break-after: always !important;
        break-after: page !important;
        zoom: 0.88;
    }

    .uhs-page:last-child {
        page-break-after: auto !important;
        break-after: auto !important;
    }

    .uhs-photo-upload-label {
        cursor: default !important;
    }

    .uhs-photo-file-input,
    .uhs-photo-upload-text span {
        display: none !important;
    }

    .uhs-page input,
    .uhs-page textarea,
    .uhs-page select {
        pointer-events: none !important;
    }

    .uhs-page input[type="radio"],
    .uhs-page input[type="checkbox"] {
        cursor: default !important;
    }
    @endif

</style>

<div class="uhs-wrapper">
    <div class="uhs-page-gap"></div>

    {{-- ═══════════════════════ PAGE 1 ═══════════════════════ --}}
    <div class="uhs-page">

        {{-- ── HEADER ── --}}
        <div class="uhs-header">

            {{-- Logo left --}}
            <div class="uhs-logo-area">
                <img src="{{ $logoUrl }}" class="uhs-logo" alt="UHS Logo">
                <div>
                    <div class="uhs-logo-title">UHS</div>
                    <div class="uhs-logo-subtitle">University of Health Sciences</div>
                </div>
            </div>

            {{-- Title center --}}
            <div class="uhs-center-block">
                <div class="uhs-title-kh">{{ $t('ពាក្យស្នើសុំ', 'Request Form') }}</div>
                <div class="uhs-title-en">{{ $t('REQUEST FORM', 'ពាក្យស្នើសុំ') }}</div>

                <div class="uhs-id-row">
                    <div class="uhs-id-label">
                        {!! $pair('អត្តលេខនិស្សិត', 'UHS ID') !!}
                    </div>
                    <input class="uhs-box-letter" type="text" maxlength="10" wire:model.blur="data.student_id">
                </div>
            </div>

            {{-- Photo right --}}
            <div class="uhs-photo-box">
                <label class="uhs-photo-upload-label">
                    <input type="file" class="uhs-photo-file-input"
                           accept="image/png,image/jpeg,image/jpg,image/webp"
                           wire:model.live="data.photo">
                    @if ($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Student Photo">
                    @else
                        <div class="uhs-photo-upload-text">
                            {{ $t('រូបថត', 'Photo') }}<br>
                            4 x 4<br>
                            <span style="font-size:9px; font-weight:400;">{{ $t('ចុចដើម្បីបញ្ចូលរូបថត', 'Click to upload') }}</span>
                        </div>
                    @endif
                </label>
            </div>
        </div>

        {{-- Check-box note --}}
        <div class="uhs-check-note">
            <span>{{ $t('(សូមគូសសញ្ញាខ្វែង', '(Please tick') }}</span>
            <input type="checkbox" style="pointer-events:none; width:13px; height:13px;">
            <span>{{ $t('នៅក្នុងប្រអប់)', 'in the box)') }}</span>
        </div>

        {{-- ── MAIN FORM TABLE ── --}}
        <table class="uhs-table">

            {{-- Row 1: Name KH | Family | First | Sex --}}
            <tr>
                <td colspan="3" style="width:35%;">
                    <div class="uhs-label">{!! $pair('ឈ្មោះជាភាសាខ្មែរ', 'Name in Khmer') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.name_kh">
                </td>
                <td style="width:16%;">
                    <div class="uhs-label">{!! $pair('នាមត្រកូល', 'Family Name') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.family_name_kh">
                </td>
                <td style="width:16%;">
                    <div class="uhs-label">{!! $pair('នាមខ្លួន', 'First Name') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.first_name_kh">
                </td>
                <td style="width:9%; text-align:center;">
                    <div class="uhs-label">{!! $pair('ភេទ', 'Sex') !!}</div>
                </td>
                <td style="width:12%; text-align:center;">
                    <label class="uhs-check-label" style="flex-direction:column; align-items:center; gap:2px;">
                        <input type="radio" value="male" wire:model.live="data.gender">
                        <span>{!! $pair('ប្រុស', 'Male') !!}</span>
                    </label>
                </td>
                <td style="width:12%; text-align:center;">
                    <label class="uhs-check-label" style="flex-direction:column; align-items:center; gap:2px;">
                        <input type="radio" value="female" wire:model.live="data.gender">
                        <span>{!! $pair('ស្រី', 'Female') !!}</span>
                    </label>
                </td>
            </tr>

            {{-- Row 2: Name Latin | Student Type --}}
            <tr>
                <td colspan="5">
                    <div class="uhs-label">{!! $pair('ឈ្មោះជាអក្សរឡាតាំង', 'Name in Latin BLOCK LETTER') !!}</div>
                    <input class="uhs-input-boxes" type="text" spellcheck="false" autocomplete="off" wire:model.live.debounce.250ms="data.family_name_en">
                </td>
                <td colspan="3">
                    <div class="uhs-student-type-options">
                        <label class="uhs-student-type-option">
                            <span style="font-size: 10px">{!! $pair('ប្រភេទនិស្សិត', "Student's Type") !!}</span>
                        </label>
                        <label class="uhs-student-type-option">
                            <input type="radio" value="scholarship" wire:model.live="data.student_type">
                            <span style="font-size: 10px">{!! $pair('អាហារូបករណ៍', 'Scholarship') !!}</span>
                        </label>
                        <label class="uhs-student-type-option">
                            <input type="radio" value="regular" wire:model.live="data.student_type">
                            <span style="font-size: 10px">{!! $pair('បង់ថ្លៃ', 'Regular') !!}</span>
                        </label>
                    </div>
                </td>
            </tr>

            {{-- Row 3: DOB | Place of Birth --}}
            <tr>
                <td colspan="2">
                    <div class="uhs-label">{!! $pair('ថ្ងៃ-ខែ-ឆ្នាំកំណើត', 'Date of Birth') !!}</div>
                    <input class="uhs-input-boxes is-date" type="text" placeholder="dd/mm/yyyy" spellcheck="false" autocomplete="off" wire:model.live.debounce.250ms="data.birth_date">
                </td>
                <td colspan="6">
                    <div class="uhs-label">{!! $pair('ទីកន្លែងកំណើត (រាជធានី/ខេត្ត)', 'Place of Birth (City/Province)') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.birth_place">
                </td>
            </tr>

            {{-- Row 4: Current Address | Village | Province --}}
            <tr>
                <td colspan="1">
                    <div class="uhs-label">{{ $t('អាសយដ្ឋានបច្ចុប្បន្ន ផ្ទះលេខ', 'Current Address / House No.') }}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.current_address">
                </td>
                <td colspan="1">
                    <div class="uhs-label">{{ $t('ផ្លូវ', 'Street') }}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.current_address">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{{ $t('សង្កាត់/ឃុំ', 'Commune / Sangkat') }}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.village">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{{ $t('ខណ្ឌ/ស្រុក', 'District / Khan') }}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.village">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{{ $t('រាជធានី/ខេត្ត', 'Capital / Province') }}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.province">
                </td>
            </tr>

            {{-- Row 5: Phone | Email --}}
            <tr>
                <td colspan="3">
                    <div class="uhs-label">{!! $pair('លេខទូរស័ព្ទ', 'Phone Number') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.phone">
                </td>
                <td colspan="5">
                    <div class="uhs-label">{!! $pair('អ៊ីមែល', 'E-mail') !!}</div>
                    <input class="uhs-input" type="email" wire:model.blur="data.email">
                </td>
            </tr>

            {{-- Row 6: Current Status --}}
            <tr>
                <td style="width:auto;">
                    <div class="uhs-label">{!! $pair('បច្ចុប្បន្នភាព', 'Current Status') !!}</div>
                </td>
                <td>
                    <div class="uhs-label">{!! $pair('និស្សិតថ្នាក់ទី', 'Year') !!}</div>
                    <input class="uhs-input" type="text" style="text-align:center;" wire:model.blur="data.current_year">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{!! $pair('ឆ្នាំសិក្សា', 'Academic Year') !!}</div>
                    <input class="uhs-input" type="text" style="text-align:center;" wire:model.blur="data.academic_year">
                </td>
                <td style="text-align:center;">
                    <label class="uhs-check-label" style="justify-content:center;">
                        <input type="checkbox" wire:model.live="data.current_studying">
                    </label>
                    <div style="font-size:9px; text-align:center; margin-top:2px;">{{ $t('បានបញ្ចប់ការសិក្សា', 'Graduated') }}</div>
                </td>
                <td colspan="2">
                    <div style="font-size:10.5px; line-height:1.6;">
                        <label class="uhs-check-label" style="display:flex; gap:4px; margin-bottom:2px;">
                            <input type="radio" value="has_sponsor" wire:model.live="data.current_status" style="margin-top:1px;">
                            <span>{{ $t('មានគ្រូបង/សណ្ណា', 'Has supervisor') }}</span>
                        </label>
                        <label class="uhs-check-label" style="display:flex; gap:4px;">
                            <input type="radio" value="no_sponsor" wire:model.live="data.current_status" style="margin-top:1px;">
                            <span>{{ $t('មិនទាន់មានគ្រូបង/សណ្ណា', 'No supervisor yet') }}</span>
                        </label>
                    </div>
                </td>
                <td>
                    <label class="uhs-check-label" style="flex-direction:column; align-items:center; gap:2px;">
                        <input type="radio" value="other_status" wire:model.live="data.current_status">
                        <span style="font-size:10px; text-align:center;">{{ $t('មិនតម្រូវការ', 'Not required') }}</span>
                    </label>
                </td>
            </tr>

            {{-- Row 7: Promotion | Major | Enrollment | Graduation --}}
            <tr>
                <td>
                    <div class="uhs-label">{!! $pair('ជំនាន់ទី', 'Promotion') !!}</div>
                    <input class="uhs-input" type="text" style="text-align:center;" wire:model.blur="data.promotion">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{!! $pair('ជំនាញ', 'Major') !!}</div>
                    <input class="uhs-input" type="text" wire:model.blur="data.major">
                </td>
                <td colspan="2">
                    <div class="uhs-label">{!! $pair('ឆ្នាំចូលរៀន', 'Year of Enrollment') !!}</div>
                    <input class="uhs-input" type="text" style="text-align:center;" wire:model.blur="data.year_enrollment">
                </td>
                <td colspan="3">
                    <div class="uhs-label">{!! $pair('ឆ្នាំបញ្ចប់ ឬ រំពឹងបញ្ចប់សិក្សា', 'Year of Graduation / Expected') !!}</div>
                    <input class="uhs-input" type="text" style="text-align:center;" wire:model.blur="data.graduation_year">
                </td>
            </tr>

            {{-- Row 8: Faculty --}}
            <tr>
                <td colspan="8">
                    <div class="uhs-label">{!! $inlinePair('មហាវិទ្យាល័យ/សាលា/ដេប៉ាតឺម៉ង់', 'Faculty / School / Department') !!} ៖</div>
                    <div class="uhs-radio-row" style="margin-top:5px; gap:18px; flex-wrap:nowrap;">
                        <label class="uhs-check-label">
                            <input type="radio" value="medicine" wire:model.live="data.faculty">
                            <span>{!! $pair('វេជ្ជសាស្ត្រ', 'Medicine') !!}</span>
                        </label>
                        <label class="uhs-check-label">
                            <input type="radio" value="pharmacy" wire:model.live="data.faculty">
                            <span>{!! $pair('ឱសថសាស្ត្រ', 'Pharmacy') !!}</span>
                        </label>
                        <label class="uhs-check-label">
                            <input type="radio" value="dentistry" wire:model.live="data.faculty">
                            <span>{!! $pair('ទន្តវទន្តសាស្ត្រ', 'Dentistry') !!}</span>
                        </label>
                        <label class="uhs-check-label">
                            <input type="radio" value="public_health" wire:model.live="data.faculty">
                            <span>{!! $pair('សុខភាពសាធារណៈ', 'Public Health') !!}</span>
                        </label>
                        <label class="uhs-check-label">
                            <input type="radio" value="tsmc" wire:model.live="data.faculty">
                            <span>{!! $pair('ស.ប.ន.ថ', 'TSMC') !!}</span>
                        </label>
                        <label class="uhs-check-label">
                            <input type="radio" value="foundation_year" wire:model.live="data.faculty">
                            <span>{!! $pair('ថ្នាក់ឆ្នាំសិក្សាមូលដ្ឋាន', 'Foundation Year') !!}</span>
                        </label>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Section title --}}
        <div class="uhs-section-title">
            {!! $t(
                'សូមគោរពជូន<br>ឯកឧត្តមសាកលវិទ្យាធិការ សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល<br>តាមរយះ៖ ការិយាល័យចុះឈ្មោះ នឹង លិខិតបទដ្ឋាននិស្សិត',
                'Respectfully submitted to<br>His Excellency Rector of the University of Health Sciences<br>Through: Registration Office and Student Regulations Office'
            ) !!}
        </div>

        {{-- ── REQUEST TYPE + LANGUAGE ── --}}
        <div class="uhs-request-grid">

            {{-- Left: document types --}}
            <div class="uhs-request-left">
                <div class="uhs-request-left-title">{{ $t('ប្រភេទឯកសារស្នើសុំ', 'Requested Document Type') }}</div>

                <label class="uhs-request-option">
                    <input type="radio" value="academic_confirmation" wire:model.live="data.request_type">
                    <span>{!! $inlinePair('លិខិតបញ្ជាក់ការសិក្សា', 'Academic Confirmation') !!}</span>
                </label>

                <label class="uhs-request-option">
                    <input type="radio" value="academic_transcript" wire:model.live="data.request_type">
                    <span>{!! $inlinePair('ព្រឹត្តិបត្រពិន្ទុ', 'Academic Transcript') !!}</span>
                </label>

                <label class="uhs-request-option">
                    <input type="radio" value="certificate_of_completion" wire:model.live="data.request_type">
                    <span>{!! $inlinePair('វិញ្ញាបនបត្របញ្ចប់ការសិក្សា', 'Certificate of Completion') !!}</span>
                </label>

                {{-- Diploma with sub-options --}}
                <div class="uhs-request-option" style="flex-direction:column; align-items:flex-start; gap:3px; min-height:auto; padding:4px 10px;">
                    <label class="uhs-check-label">
                        <input type="radio" value="diploma" wire:model.live="data.request_type">
                        <span>{!! $inlinePair('សញ្ញាបត្រ', 'Diploma') !!} ៖</span>
                    </label>
                    <label class="uhs-check-label" style="margin-left:18px; font-size:10.5px; white-space:normal; line-height:1.4;">
                        <input type="checkbox" wire:model.live="data.diploma_original">
                        <span>ស្នើសុំលើទី ១
                            <small>(ខ្ញុំបាទ/នាងខ្ញុំសូមអះអាងថា ពុំដែលបានទទួលញ្ញាបត្រសម្រាប់កម្រិត</small>
                            <input class="uhs-inline-input is-sm" type="text" wire:model.blur="data.diploma_copy_number">
                            <small>ជំនាញ</small>
                            <input class="uhs-inline-input is-sm" type="text" wire:model.blur="data.diploma_copy_number">
                            <small>នេះពីមុនមកទេ។)</small>
                        </span>
                    </label>
                    <div class="uhs-check-label" style="margin-left:18px; font-size:10.5px;">
                        <input type="checkbox" wire:model.live="data.diploma_copy">
                        <span>ស្នើសុំលើ</span>
                        <input class="uhs-inline-input is-sm" type="text" wire:model.blur="data.diploma_copy_number">
                        <span>(ទុតិយតា)</span>
                    </div>
                </div>
            </div>

            {{-- Right: language --}}
            <div>
                <div class="uhs-language-title">{!! $inlinePair('ភាសា', 'Language') !!}</div>

                <table class="uhs-language-table">
                    <tr>
                        <th style="width:30%; text-align:left; padding-left:5px;"></th>
                        <th>
                            <label class="uhs-check-label" style="flex-direction:column; align-items:center; justify-content:center; gap:2px;">
                                <input type="checkbox" value="khmer" wire:model.live="data.languages">
                                <span>{!! $pair('ខ្មែរ', 'Khmer') !!}</span>
                            </label>
                        </th>
                        <th>
                            <label class="uhs-check-label" style="flex-direction:column; align-items:center; justify-content:center; gap:2px;">
                                <input type="checkbox" value="english" wire:model.live="data.languages">
                                <span>{!! $pair('អង់គ្លេស', 'English') !!}</span>
                            </label>
                        </th>
                        <th>
                            <label class="uhs-check-label" style="flex-direction:column; align-items:center; justify-content:center; gap:2px;">
                                <input type="checkbox" value="french" wire:model.live="data.languages">
                                <span>{!! $pair('បារាំង', 'French') !!}</span>
                            </label>
                        </th>
                    </tr>

                    <tr>
                        <td style="text-align:left; padding-left:5px;">{!! $inlinePair('ចំនួន', 'Number') !!}</td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.khmer_copies"> ច្បាប់</td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.english_copies"> ច្បាប់</td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.french_copies"> ច្បាប់</td>
                    </tr>

                    <tr>
                        <td style="text-align:left; padding-left:5px; line-height:1.4; font-size:10px;">
                            {!! $pair('ដាក់ក្នុងស្រោមសំបុត្របិទជិត និងបោះត្រាចំនួន', 'Sealed Envelope & Stamp') !!}
                        </td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.sealed_envelope_copies"> ច្បាប់</td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.stamp_copies"> ច្បាប់</td>
                        <td><input class="uhs-number-input" type="number" min="0" wire:model.blur="data.stamp_copies"> ច្បាប់</td>
                    </tr>

                    <tr>
                        <td colspan="4" style="text-align:left; padding: 4px 6px;">
                            <label class="uhs-check-label">
                                <input type="radio" value="other" wire:model.live="data.request_type">
                                <span>{!! $inlinePair('ផ្សេងទៀត សូមបញ្ជាក់', 'Other, Specify') !!}:</span>
                            </label>
                            <input class="uhs-input" style="display:inline-block; width:62%; margin-left:4px;" type="text" wire:model.blur="data.other_request_type">
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- Note paragraph --}}
        <div class="uhs-note-text">
            {!! $t(
                'ខ្ញុំបាទ/នាងខ្ញុំសូមធានាអះអាងថា ព័ត៌មាននឹងឯកសារទាំងអស់ដែលបានផ្ដល់ជូននេះ គឺពិតជាត្រឹមត្រូវនិងពុំមានការក្លែងបន្លំឡើយ។<br>អាស្រ័យដូចបានជម្រាបខាងលើ សូមឯកឧត្តមសាកលវិទ្យាធិការមេត្តាអនុញ្ញាតអោយខ្ញុំបាទ/នាងខ្ញុំតាមសេចក្ដីស្នើសុំខាងលើ ដើម្បីយកមកប្រើប្រាស់ផ្ទាល់ខ្លួនដោយសេចក្ដីអនុគ្រោះ<br>សូមឯកឧត្តមសាកលវិទ្យាធិការ មេត្តាទទួលនូវការគោរពដ៏ខ្ពង់ខ្ពស់អំពីខ្ញុំបាទ/នាងខ្ញុំ។',
                'I hereby certify that all information and documents provided are true, correct, and not falsified.<br>Based on the request above, I respectfully ask His Excellency Rector to kindly approve my request for personal use.<br>Please accept my highest respect.'
            ) !!}
        </div>

        {{-- Signature row --}}
        <div class="uhs-signature-row">
            {{-- Left: purpose + left-side signature --}}
            <div>
                <div style="margin-top:8px; font-size:11px; text-align:center; line-height:2;">
                    បានពិនិត្យនិងបញ្ជាក់ថា<br>
                    និស្សិតបានបំពេញត្រឹមត្រូវនិងមានឯកសារភ្ជាប់គ្រប់គ្រាន់<br>
                    <div class="uhs-signature-date-line">
                        <span>ថ្ងៃទី</span>
                        <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.received_day">
                        <span>ខែ</span>
                        <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.received_month">
                        <span>ឆ្នាំ ២០</span>
                        <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.received_year">
                    </div>
                    ហត្ថលេខា និង ឈ្មោះអ្នកទទួលពាក្យ
                </div>
            </div>

            {{-- Right: student signature --}}
            <div class="uhs-signature">
                <div class="uhs-signature-date-line">
                    <span>រាជធានីភ្នំពេញ</span>
                    <span>ថ្ងៃទី</span>
                    <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.request_day">
                    <span>ខែ</span>
                    <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.request_month">
                    <span>ឆ្នាំ ២០</span>
                    <input class="uhs-inline-input is-xs" type="text" wire:model.blur="data.request_year">
                </div>
                ហត្ថលេខា និងឈ្មោះសាមីខ្លួន<br><br><br>
                <input class="uhs-inline-input is-signature-name" type="text" wire:model.blur="data.applicant_signature_name">
            </div>
        </div>

        {{-- Office section --}}
        <div class="uhs-office">
            <div class="uhs-office-title">សូមបំពេញផ្នែកនេះនៅពេលនិស្សិតមកទទួលយកឯកសារស្នើសុំ</div>

            <div class="uhs-office-grid">
                <div class="uhs-office-left">
                    <div style="margin-bottom:6px;">
                        ឈ្មោះអ្នកទទួលយកឯកសារ:
                        <input class="uhs-inline-input is-office-line" type="text" wire:model.blur="data.office_permission_no">
                    </div>

                    <div style="margin-bottom:5px; display:flex; align-items:center; gap:10px;">
                        <span>អ្នកទទួលជា៖</span>
                        <label class="uhs-check-label">
                            <input type="radio" value="approved" wire:model.live="data.status"> សាមីខ្លួន
                        </label>
                    </div>

                    <div style="margin-bottom:4px;">
                        <label class="uhs-check-label" style="align-items:flex-start; gap:6px;">
                            <input type="checkbox" wire:model.live="data.verified_signature" style="margin-top:2px;">
                            <span style="font-size:10.5px; line-height:1.5; white-space:normal;">
                                ផ្សេងពីសាមីខ្លួន ( សូមបញ្ចាក់អំពីទំនាក់ទំនងជាមួយសាមីខ្លួន )
                            </span>
                        </label>
                        <div style="font-size:9px; margin-left:20px; color:var(--uhs-muted); line-height:1.45;">
                            ( ករណីអ្នកទទួលមិនមែនជាសាមីខ្លួន ត្រូវមានលិខិតផ្ទេរសិទ្ធពីសាមីខ្លួន រួមជាមួយកិច្ចសន្យាធានា និង ភ្ជាប់អត្តសញ្ញាណបណ្ណថតចំលងរបស់អ្នកទទួល )
                        </div>
                    </div>

                    <div style="margin-top:6px; display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                        <span>កាលបរិច្ឆេកទទួល:</span>
                        <input class="uhs-input" style="display:inline-block; width:300px;" type="text" wire:model.blur="data.office_note">
                        <span>ម៉ោង:</span>
                        <input class="uhs-inline-input is-md" type="text" wire:model.blur="data.office_process">
                    </div>
                </div>

                <div class="uhs-stamp-box">
                    ស្នាមមេដៃស្ដាំអ្នកទទួល
                </div>
            </div>
        </div>
        <div class="uhs-note-text">
            <b>កំណត់ចំណាំ៖</b>សូមពិនិត្យមើលនៅផ្នែកខាងខ្នងស្ដីពីព័ត៍មានទាក់ទងនឹង <b>ឯកសារដែរត្រូវភ្ជាប់មកជាមួយ តម្លៃសេវា និង ការណែនាំអំពីរូបថត។ *ពាក្យស្នើសុំមួយអាចសុំឯកសារបានតែមួយប្រភេទគត់។</b>
        </div>

    </div>{{-- END PAGE 1 --}}

    {{-- ═══════════════════════ PAGE 2 ═══════════════════════ --}}
    <div class="uhs-page uhs-page-two-original">
        <div class="uhs-page-two-title">
            {{ $t('ឯកសារដែលត្រូវភ្ជាប់មកជាមួយតាមប្រភេទការស្នើសុំ', 'Required Attachments by Request Type') }}
        </div>

        <table class="uhs-page2-main-table">
            <colgroup>
                <col style="width:6%;">
                <col style="width:31%;">
                <col style="width:7%;">
                <col style="width:7%;">
                <col style="width:7%;">
                <col style="width:7%;">
                <col style="width:8%;">
                <col style="width:8%;">
                <col style="width:10%;">
                <col style="width:9%;">
            </colgroup>
            <thead>
            <tr>
                <th rowspan="2">{{ $t('ល.រ', 'No.') }}</th>
                <th rowspan="2">{{ $t('ប្រភេទឯកសារស្នើសុំ', 'Requested Document Type') }}</th>
                <th rowspan="2">{{ $t('ចំនួន', 'Quantity') }}</th>
                <th colspan="7">{{ $t('ឯកសារភ្ជាប់ និង លក្ខខណ្ឌ', 'Attachments and Conditions') }}</th>
            </tr>
            <tr>
                <th>{!! $t('ថ្នាក់ឆ្នាំ<br>សិក្សា<br>មូលដ្ឋាន', 'Foundation<br>Year') !!}</th>
                <th>{{ $t('បរិញ្ញាបត្ររង', 'Associate Degree') }}</th>
                <th>{{ $t('បរិញ្ញាបត្រ', 'Bachelor') }}</th>
                <th>{!! $t('វេជ្ជបណ្ឌិត<br>/ ទន្តបណ្ឌិត', 'Medical Doctor<br>/ Dentist') !!}</th>
                <th>{!! $t('ថ្នាក់ឯកទេស<br>(DES) ឬ<br>ថ្នាក់ជំនាញ (DU)', 'Specialist<br>(DES) or DU') !!}</th>
                <th>{!! $t('លិខិតបញ្ជាក់<br>ការសិក្សា ឬ<br>ព្រឹត្តិបត្រ', 'Academic Confirmation<br>or Transcript') !!}</th>
                <th>{!! $t('សុំកែតម្រូវ<br>ព័ត៍មាន<br>ផ្ទាល់ខ្លួន', 'Correction of<br>Personal Information') !!}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>១</td>
                <td class="uhs-doc-type">{{ $t('ពាក្យស្នើសុំ ( មានបោះត្រាបេឡា បន្ទាប់ពីបានបង់ថ្លៃសេវារួច )', 'Application form with cashier stamp after service fee payment') }}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
            </tr>
            <tr>
                <td>២</td>
                <td class="uhs-doc-type">{!! $t('រូបថត 4x6 <small style="font-style: italic">( មានសរសេរឈ្មោះ និង អត្តលេខនិស្សិតខាងខ្នងរូបថត និង សូមអានការណែនាំអំពីរូបថតនៅខាងក្រោម )</small>', '4x6 Photo <small style="font-style: italic">(write name and UHS ID on the back; read photo instructions below)</small>') !!}</td>
                <td>{{ $t('៦ សន្លឹក', '6 photos') }}</td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ក)</small></td>
                <td><span class="uhs-page2-check">✓</span></td>
            </tr>
            <tr>
                <td>៣</td>
                <td class="uhs-doc-type">{!! $t('សញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'High School Diploma <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
            </tr>
            <tr>
                <td>៤</td>
                <td class="uhs-doc-type">{!! $t('វិញ្ញាបនបត្រឆ្នាំសិក្សាមូលដ្ឋាន <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'Foundation Year Certificate <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
            </tr>
            <tr>
                <td>៥</td>
                <td class="uhs-doc-type">{!! $t('វិញ្ញាបនបត្របរិញ្ញាបត្រ <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'Bachelor Certificate <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
            </tr>
            <tr>
                <td>៦</td>
                <td class="uhs-doc-type">{!! $t('វិញ្ញាបនបត្រ ឬ សញ្ញាបត្រគ្រូពេទ្យមធ្យម <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'Certificate or Diploma of Medium Medical Staff <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
            </tr>
            <tr>
                <td>៧</td>
                <td class="uhs-doc-type">{!! $t('វិញ្ញាបនបត្រ ពាក់ព័ន្ធនឹងកម្រិតជំនាញចុងក្រោយ <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'Certificate related to the latest specialist level <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td><span class="uhs-page2-check">✓</span><br><small>(ខ)</small></td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
            </tr>
            <tr>
                <td>៨</td>
                <td class="uhs-doc-type">{!! $t('កំណត់ហេតុការពារនិក្ខេបបទ/សារណា <small style="font-style: italic">(ច្បាប់ដើម)</small>', 'Thesis/Dissertation Defense Minutes <small style="font-style: italic">(original)</small>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td class="uhs-page2-shade"></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span><br><strong style="font-size:8px;">{!! $t('ប្រសិនបើ<br>បានការពាររួច', 'If already<br>defended') !!}</strong></td>
                <td class="uhs-page2-shade"></td>
            </tr>
            <tr>
                <td>៩</td>
                <td class="uhs-doc-type">{!! $t('សំបុត្រកំណើត <small style="font-style: italic">(មានអក្សរឡាតាំង)</small> និង អត្តសញ្ញាណប័ណ្ណសញ្ញាតិខ្មែរ <span class="uhs-doc-note">(ថតចម្លង មានបញ្ជាក់ពីសាលារាជធានី/ខេត្ត ឫ អាជ្ញាធរខណ្ឌ/ស្រុក)</span>', 'Birth Certificate <small style="font-style: italic">(with Latin letters)</small> and Khmer National ID Card <span class="uhs-doc-note">(certified copy by municipal/provincial school or district authority)</span>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td><span class="uhs-page2-check">✓</span></td>
                <td class="uhs-page2-shade"></td>
                <td><span class="uhs-page2-check">✓</span></td>
            </tr>
            </tbody>
        </table>

        <div class="uhs-page2-footnotes">
            {!! $t(
                'កំណត់សម្គាល់៖<br>(ក) សម្រាប់តែនិស្សិតដែលចូលរៀនដោយមិនឆ្លងកាត់ការប្រឡងថ្នាក់ជាតិប៉ុណ្ណោះ<br>(ខ) ភ្ជាប់មកមួយណាក៏បាន<br>(គ) សម្រាប់តែការសុំព្រឹត្តិបត្រពិន្ទុ ឬ អតីតនិស្សិតដែលសុំលិខិតបញ្ជាក់ការសិក្សា ប៉ុន្តែពុំមានរូបថតក្នុងប្រព័ន្ធទិន្នន័យបច្ចុប្បន្នរបស់សវស។',
                'Notes:<br>(a) Only for students admitted without passing the national examination.<br>(b) Attach any one of the listed documents.<br>(c) Only for transcript requests or former students requesting academic confirmation when no current photo exists in the UHS database.'
            ) !!}
        </div>

        <div class="uhs-page2-price-title">{{ $t('តារាងតម្លៃសេវា', 'Service Fee Table') }}</div>

        <table class="uhs-page2-price-table">
            <colgroup>
                <col style="width:8%;">
                <col style="width:47%;">
                <col style="width:12%;">
                <col style="width:16.5%;">
                <col style="width:16.5%;">
            </colgroup>
            <tr>
                <th>{{ $t('ល.រ', 'No.') }}</th>
                <th>{{ $t('ប្រភេទឯកសារ/លិខិតដែលស្នើសុំ', 'Requested Document / Letter Type') }}</th>
                <th>{{ $t('ចំនួន', 'Quantity') }}</th>
                <th colspan="2">{{ $t('តម្លៃ', 'Fee') }}</th>
            </tr>
            <tr>
                <td>១</td>
                <td class="uhs-price-doc">{{ $t('ព្រឹត្តិបត្រពិន្ទុ', 'Academic Transcript') }}</td>
                <td>{{ $t('៣ ច្បាប់', '3 copies') }}</td>
                <td>{!! $t('១០,០០០ រៀល<br><small>(ថ្នាក់ឆ្នាំសិក្សាមូលដ្ឋាន)</small>', '10,000 Riel<br><small>(Foundation Year)</small>') !!}</td>
                <td>{!! $t('២០,០០០ រៀល<br><small>(ថ្នាក់ផ្សេងទៀត)</small>', '20,000 Riel<br><small>(Other levels)</small>') !!}</td>
            </tr>
            <tr>
                <td>២</td>
                <td class="uhs-price-doc">{{ $t('លិខិតបញ្ជាក់ការសិក្សា', 'Academic Confirmation') }}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td colspan="2">{{ $t('៨,០០០ រៀល', '8,000 Riel') }}</td>
            </tr>
            <tr>
                <td>៣</td>
                <td class="uhs-price-doc">{{ $t('វិញ្ញាបនបត្រ ឬ សញ្ញាបត្រ', 'Certificate or Diploma') }}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td colspan="2">{{ $t('២០,០០០ រៀល', '20,000 Riel') }}</td>
            </tr>
            <tr>
                <td>៤</td>
                <td class="uhs-price-doc">{{ $t('សុំកែតម្រូវព័ត៍មានផ្ទាល់ខ្លួន', 'Correction of Personal Information') }}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td colspan="2">{{ $t('៤,០០០ រៀល', '4,000 Riel') }}</td>
            </tr>
            <tr>
                <td>៥</td>
                <td class="uhs-price-doc">{!! $t('សុំធ្វើបណ្ណសម្គាល់ខ្លួននិស្សិតឡើងវិញ <small>(បាត់ ឬ ខូច)</small><br><small>(បណ្ណសម្គាល់ខ្លួននិស្សិត ធ្វើជូនដោយឥតគិតថ្លៃ នៅពេលចុះឈ្មោះចូលរៀនរៀងរាល់ដើមឆ្នាំសិក្សា)</small>', 'Student ID card replacement <small>(lost or damaged)</small><br><small>(Student ID cards are issued free of charge at registration each academic year)</small>') !!}</td>
                <td>{{ $t('១ ច្បាប់', '1 copy') }}</td>
                <td colspan="2">{{ $t('១០,០០០ រៀល', '10,000 Riel') }}</td>
            </tr>
            <tr class="uhs-page2-price-note-row">
                <td><strong>{{ $t('ចំណាំ៖', 'Note:') }}</strong></td>
                <td colspan="4">
                    {!! $t(
                        'សូមបង់ថ្លៃសេវានៅបញ្ជរបេឡាបន្ទាប់ពីបំពេញពាក្យស្នើសុំរួច ហើយត្រូវយកមកដាក់នៅបញ្ជរទទួលពាក្យ។<br>តម្លៃសេវាដែលបង់ហើយពុំអាចដកវិញបានទេក្នុងគ្រប់ករណីទាំងអស់។',
                        'Please pay the service fee at the cashier after completing the request form, then submit it at the request receiving counter.<br>Paid service fees are non-refundable in all cases.'
                    ) !!}
                </td>
            </tr>
        </table>

        <div class="uhs-page2-guide">
            <span class="uhs-page2-guide-title">{{ $t('ការណែនាំអំពីរូបថត៖', 'Photo Instructions:') }}</span><br>

            {{ $t('សំរាប់រូបថតដែលភ្ជាប់មកជាមួយឯកសារ ត្រូវគោរពតាមលក្ខខណ្ឌដូចខាងក្រោម៖', 'Photos attached with documents must follow the conditions below:') }}<br>

            <div class="uhs-guide-row">{{ $t('១. ជារូបថតដែលទើបនឹងថតក្នុងពេលថ្មីៗ យ៉ាងយូរត្រឹមអំឡុងពេល៦ខែមុន', '1. The photo must be recently taken, within the last 6 months.') }}</div>
            <div class="uhs-guide-row">{{ $t('២. រូបថតផ្ដិតពណ៌ធម្មជាតិ (color) ពុំមែនជារូបស្កេន', '2. Natural color photo, not a scanned image.') }}</div>
            <div class="uhs-guide-row">{{ $t('៣. ទំហំ ៤×៦ (ឬ ៣.៥ × ៤.៥ស.ម.)', '3. Size 4×6 or 3.5×4.5 cm.') }}</div>
            <div class="uhs-guide-row">{{ $t('៤. ថតត្រង់ចំពីមុខពេញ ឱ្យឃើញត្រចៀកសងខាង', '4. Full front-facing photo showing both ears.') }}</div>
            <div class="uhs-guide-row">{!! $t('៥. ផ្ទៃខាងក្រោយខ្នងត្រូវជាផ្ទៃលាត <b>ពណ៌ស</b>', '5. Background must be plain <b>white</b>.') !!}</div>
            <div class="uhs-guide-row">{{ $t('៦. សំលៀកបំពាក់៖', '6. Dress code:') }}</div>

            <div class="uhs-guide-sub">
                {!! $t(
                    '-បុរស៖ <b>ពាក់អាវសីមីកខុប ពណ៌ស មានក្រវ៉ាត់ក និង ពាក់អាវធំពណ៌ខ្មៅពីខាងក្រៅ</b><br>-នារី៖ <b>ពាក់អាវសីមីកខុប ពណ៌ស និង ពាក់អាវធំពណ៌ខ្មៅពីខាងក្រៅ</b>',
                    '-Male: <b>wear a white shirt with tie and black jacket</b><br>-Female: <b>wear a white shirt and black jacket</b>'
                ) !!}
            </div>

            <div class="uhs-guide-row">
                {{ $t(
                    '៧. ថតក្នុងសភាពធម្មជាតិ ដោយពុំមានតុបតែង ឬ ធ្វើម៉ូតសក់ លាបពណ៌ឬហាយឡាយសក់ ពុំមានពាក់គ្រឿងអលង្ការ ពុំមានពាក់វ៉ែនតា និង ញញឹមបិទមាត់',
                    '7. Take the photo naturally without heavy makeup, styled or dyed/highlighted hair, jewelry, glasses, and with a closed-mouth smile.'
                ) }}
            </div>
        </div>
    </div>{{-- END PAGE 2 --}}

</div>


@if ($isPdfExport)
    <script>
        window.__UHS_DOCUMENT_REQUEST_DATA__ = @json($data);

        function fillUhsDocumentRequestForm() {
            const data = window.__UHS_DOCUMENT_REQUEST_DATA__ || {};

            const getModelPath = (element) => {
                for (const attr of element.attributes) {
                    if (attr.name.startsWith('wire:model')) {
                        return attr.value || '';
                    }
                }

                return '';
            };

            const normalizeValue = (value) => {
                if (value === null || value === undefined) {
                    return '';
                }

                if (typeof value === 'object') {
                    return '';
                }

                return String(value);
            };

            document.querySelectorAll('input, textarea, select').forEach((element) => {
                const modelPath = getModelPath(element);

                if (!modelPath || !modelPath.startsWith('data.')) {
                    return;
                }

                const key = modelPath.replace(/^data\./, '');
                const savedValue = data[key];

                if (element.type === 'file') {
                    return;
                }

                if (element.type === 'radio') {
                    element.checked = String(savedValue ?? '') === String(element.value ?? '');
                    return;
                }

                if (element.type === 'checkbox') {
                    if (Array.isArray(savedValue)) {
                        element.checked = savedValue.map(String).includes(String(element.value));
                        return;
                    }

                    if (typeof savedValue === 'string') {
                        try {
                            const decoded = JSON.parse(savedValue);

                            if (Array.isArray(decoded)) {
                                element.checked = decoded.map(String).includes(String(element.value));
                                return;
                            }
                        } catch (error) {
                            // Keep normal boolean handling below.
                        }
                    }

                    element.checked = Boolean(savedValue);
                    return;
                }

                element.value = normalizeValue(savedValue);
                element.setAttribute('value', normalizeValue(savedValue));
                element.setAttribute('readonly', 'readonly');
            });
        }

        fillUhsDocumentRequestForm();
        document.addEventListener('DOMContentLoaded', fillUhsDocumentRequestForm);
    </script>
@endif

