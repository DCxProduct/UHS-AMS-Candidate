@php
    $isPdfExport = $isPdfExport ?? false;

    if (isset($record) && $record) {
        $data = $record->toArray();
    } else {
        $data = $data ?? ($this->data ?? []);
    }

    $v = function ($key, $default = '') use ($data) {
        $value = data_get($data, $key, $default);

        if (is_array($value) || is_object($value)) {
            return '';
        }

        return $value ?? $default;
    };

    $t = fn (string $key): string => __('national_entrance_exam_applications.pdf.' . $key);

    /*
    |--------------------------------------------------------------------------
    | Gender Fixed
    |--------------------------------------------------------------------------
    | Support:
    | - gender
    | - extra_data.gender
    | - male / female
    | - Male / Female
    | - m / f
    | - ប្រុស / ស្រី
    */
    $genderValue = $v('gender');

    if ($genderValue === '' || $genderValue === null) {
        $genderValue = $v('extra_data.gender');
    }

    $genderKey = strtolower(trim((string) $genderValue));

    $genderText = [
        'male' => __('national_entrance_exam_applications.options.gender.male'),
        'm' => __('national_entrance_exam_applications.options.gender.male'),
        'boy' => __('national_entrance_exam_applications.options.gender.male'),
        'ប្រុស' => __('national_entrance_exam_applications.options.gender.male'),

        'female' => __('national_entrance_exam_applications.options.gender.female'),
        'f' => __('national_entrance_exam_applications.options.gender.female'),
        'girl' => __('national_entrance_exam_applications.options.gender.female'),
        'ស្រី' => __('national_entrance_exam_applications.options.gender.female'),
    ][$genderKey] ?? trim((string) $genderValue);

    /*
    |--------------------------------------------------------------------------
    | Marital Status Fixed
    |--------------------------------------------------------------------------
    */
    $maritalStatus = $v('marital_status');

    if ($maritalStatus === '' || $maritalStatus === null) {
        $maritalStatus = $v('extra_data.marital_status');
    }

    $maritalStatus = trim((string) $maritalStatus);

    $photo = data_get($data, 'photo_path');
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
@endphp

<style>
    @font-face {
        font-family: 'UHS-Battambang';
        src: url('{{ $isPdfExport ? 'file:///' . str_replace('\\', '/', public_path('KhmerOS_battambang.ttf')) : asset('KhmerOS_battambang.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    @font-face {
        font-family: 'UHS-Muol';
        src: url('{{ $isPdfExport ? 'file:///' . str_replace('\\', '/', public_path('KhmerOS_muollight.ttf')) : asset('KhmerOS_muollight.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
        font-display: swap;
    }

    :root {
        --uhs-body-font: 'UHS-Battambang', 'Khmer OS Battambang', 'Noto Sans Khmer', Arial, sans-serif;
        --uhs-title-font: 'UHS-Muol', 'Khmer OS Muol Light', 'Noto Serif Khmer', serif;
    }

    /*
    |--------------------------------------------------------------------------
    | Light / Dark Preview Wrapper
    |--------------------------------------------------------------------------
    | Light mode  = gray wrapper
    | Dark mode   = black wrapper like document-requests
    | Paper form  = always white
    */
    .nee-shell {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 24px;
        overflow-x: auto;
    }

    .nee-paper {
        width: 794px;
        min-height: 1120px;
        margin: 0 auto 28px;
        background: #ffffff !important;
        color: #111111 !important;
        color-scheme: light !important;
        padding: 18px 72px 36px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .18);
        font-family: var(--uhs-body-font) !important;
        font-size: 11.2px;
        line-height: 1.55;
        position: relative;
    }

    .nee-paper * {
        box-sizing: border-box;
    }

    .app-line:focus,
    .app-date input:focus,
    .bio-line:focus,
    .bio-date input:focus,
    .bio-sign-name:focus {
        background: rgba(255, 244, 190, .45);
        outline: 1px solid rgba(245, 158, 11, .7);
        border-radius: 2px;
    }

    .nee-page-number {
        position: absolute;
        right: 62px;
        bottom: 30px;
        font-family: var(--uhs-body-font) !important;
        font-size: 10px;
    }

    /* ==============================
       PAGE 1 - CLEAN APPLICATION
       ============================== */

    .nee-paper.nee-application-page {
        padding: 34px 62px 34px;
        font-family: var(--uhs-body-font) !important;
        font-size: 10.5px;
        line-height: 1.55;
        overflow: hidden;
    }

    .nee-application-page .app-kingdom {
        text-align: center;
        font-family: var(--uhs-title-font) !important;
        font-weight: 400 !important;
        font-size: 12px;
        line-height: 1.5;
        margin-top: 0;
    }

    .nee-application-page .app-year-small {
        text-align: center;
        font-size: 7.5px;
        margin-top: 0;
    }

    .nee-application-page .app-ministry {
        position: absolute;
        top: 86px;
        left: 62px;
        font-family: var(--uhs-title-font) !important;
        font-weight: 400 !important;
        font-size: 9.3px;
        line-height: 1.65;
    }

    .nee-application-page .app-title {
        text-align: center;
        font-family: var(--uhs-title-font) !important;
        font-weight: 400 !important;
        font-size: 11.3px;
        text-decoration: underline;
        margin-top: 52px;
        margin-bottom: 10px;
        line-height: 1.6;
    }

    .nee-application-page .app-row {
        display: grid;
        align-items: end;
        column-gap: 5px;
        margin-bottom: 5px;
        width: 100%;
    }

    .nee-application-page .app-row.two {
        grid-template-columns: auto 1fr auto 1fr;
    }

    .nee-application-page .app-row.identity {
        grid-template-columns:
            auto 52px
            auto 92px
            auto 88px
            auto 62px
            auto 66px;
        column-gap: 4px;
    }

    .nee-application-page .app-row.address {
        grid-template-columns: auto 1fr auto 1fr auto 1fr;
    }

    .nee-application-page .app-row.work {
        grid-template-columns: auto 1fr auto 110px;
    }

    .nee-application-page .app-label {
        white-space: nowrap;
        font-family: var(--uhs-body-font) !important;
        font-weight: 400 !important;
    }

    .nee-application-page .app-line {
        width: 100%;
        min-width: 0;
        height: 18px;
        border: 0;
        border-bottom: 1px dotted #111;
        background: transparent;
        outline: none;
        color: #111 !important;
        font-family: var(--uhs-body-font) !important;
        font-size: 9.8px;
        padding: 0 2px;
    }

    .nee-application-page .app-subtitle {
        text-align: center;
        font-family: var(--uhs-title-font) !important;
        font-weight: 400 !important;
        font-size: 10.5px;
        line-height: 1.7;
        margin: 18px 0 12px;
    }

    .nee-application-page .app-paragraph {
        text-align: justify;
        text-indent: 28px;
        font-family: var(--uhs-body-font) !important;
        font-size: 10.5px;
        line-height: 1.75;
        margin: 5px 0;
    }

    .nee-application-page .app-doc-title {
        text-align: center;
        font-family: var(--uhs-title-font) !important;
        font-weight: 400 !important;
        font-size: 10.6px;
        text-decoration: underline;
        margin: 8px 0 5px;
    }

    .nee-application-page .app-doc-row {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: end;
        column-gap: 6px;
        margin-bottom: 4px;
        font-family: var(--uhs-body-font) !important;
        font-size: 10.5px;
    }

    .nee-application-page .app-signature {
        display: grid;
        grid-template-columns: 1fr 250px;
        gap: 26px;
        margin-top: 16px;
    }

    .nee-application-page .app-sign-box {
        text-align: center;
        line-height: 1.7;
        font-family: var(--uhs-body-font) !important;
        font-size: 10.3px;
        min-height: 90px;
    }

    .nee-application-page .app-date {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 4px;
        white-space: nowrap;
    }

    .nee-application-page .app-date input {
        width: 36px;
        height: 17px;
        border: 0;
        border-bottom: 1px dotted #111;
        background: transparent;
        outline: none;
        text-align: center;
        font-family: var(--uhs-body-font) !important;
        font-size: 10px;
    }

    /* ==============================
       BIOGRAPHY PAGE - CLEAN VERSION
       ============================== */

    .nee-bio-page {
        padding: 44px 74px 44px;
        font-family: var(--uhs-body-font) !important;
        font-size: 12px;
        line-height: 1.6;
        overflow: hidden;
    }

    .nee-bio-page .bio-header {
        text-align: center;
        margin-bottom: 42px;
    }

    .nee-bio-page .bio-kingdom {
        font-family: var(--uhs-title-font) !important;
        font-size: 13px;
        line-height: 1.55;
        font-weight: 400 !important;
    }

    .nee-bio-page .bio-year {
        font-size: 8px;
        margin-top: -2px;
    }

    .nee-bio-page .bio-title {
        font-family: var(--uhs-title-font) !important;
        font-size: 18px;
        line-height: 1.5;
        margin-top: 24px;
        font-weight: 400 !important;
    }

    .nee-bio-page .bio-code {
        font-size: 9px;
        margin-top: -3px;
    }

    .nee-bio-page .bio-photo-box {
        position: absolute;
        top: 48px;
        right: 76px;
        width: 112px;
        height: 136px;
        border: 1.2px solid #111;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-family: var(--uhs-body-font) !important;
        font-size: 13px;
        line-height: 1.8;
        overflow: hidden;
    }

    .nee-bio-page .bio-photo-box input[type="file"] {
        display: none;
    }

    .nee-bio-page .bio-photo-box label {
        width: 100%;
        height: 100%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nee-bio-page .bio-photo-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .nee-bio-page .bio-form {
        margin-top: 8px;
        width: 100%;
    }

    .nee-bio-page .bio-row {
        display: grid;
        grid-template-columns: 28px auto 1fr;
        align-items: end;
        column-gap: 6px;
        margin-bottom: 10px;
        width: 100%;
        font-size: 12px;
    }

    .nee-bio-page .bio-row.two {
        grid-template-columns: 28px auto 1fr auto 1fr;
    }

    .nee-bio-page .bio-row.three {
        grid-template-columns: 28px auto 1fr auto 90px auto 90px;
    }

    .nee-bio-page .bio-row.no-number {
        grid-template-columns: 44px auto 1fr auto 1fr;
    }

    .nee-bio-page .bio-row.family {
        grid-template-columns: 44px auto 1fr auto 120px auto 1fr;
    }

    .nee-bio-page .bio-row.family-small {
        grid-template-columns: 44px auto 1fr auto 1fr;
    }

    .nee-bio-page .bio-no,
    .nee-bio-page .bio-label {
        white-space: nowrap;
    }

    .nee-bio-page .bio-line {
        width: 100%;
        min-width: 0;
        height: 20px;
        border: 0;
        border-bottom: 1.1px dotted #111;
        background: transparent;
        outline: none;
        color: #111 !important;
        font-family: var(--uhs-body-font) !important;
        font-size: 11.5px;
        padding: 0 3px;
    }

    .nee-bio-page .bio-check-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        font-size: 12px;
    }

    .nee-bio-page .bio-check {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }

    .nee-bio-page .bio-check input {
        width: 13px;
        height: 13px;
        margin: 0;
        appearance: none;
        -webkit-appearance: none;
        border: 1.2px solid #111;
        background: #fff;
        position: relative;
    }

    .nee-bio-page .bio-check input:checked::after {
        content: "✓";
        position: absolute;
        top: -9px;
        left: 0;
        font-size: 19px;
        font-weight: 900;
        color: #111;
    }

    .nee-bio-page .bio-confirm {
        text-align: center;
        margin-top: 20px;
        font-size: 12px;
    }

    .nee-bio-page .bio-signature {
        width: 300px;
        margin-left: auto;
        margin-top: 26px;
        text-align: center;
        font-size: 12px;
        line-height: 1.8;
    }

    .nee-bio-page .bio-date {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 6px;
        white-space: nowrap;
    }

    .nee-bio-page .bio-date input,
    .nee-bio-page .bio-sign-name {
        height: 19px;
        border: 0;
        border-bottom: 1.1px dotted #111;
        background: transparent;
        outline: none;
        text-align: center;
        font-family: var(--uhs-body-font) !important;
        font-size: 11px;
    }

    .nee-bio-page .bio-date input {
        width: 42px;
    }

    .nee-bio-page .bio-sign-name {
        width: 60px;
    }

    .nee-bio-page .bio-page-number {
        position: absolute;
        right: 86px;
        bottom: 52px;
        font-size: 11px;
    }

    /*
    |--------------------------------------------------------------------------
    | Dark Mode - keep the paper like a real printed document
    |--------------------------------------------------------------------------
    */
    html.dark .nee-shell,
    body.dark .nee-shell,
    .dark .nee-shell {
        background: #05070d !important;
        border-color: #27272a !important;
    }

    html.dark .nee-paper,
    body.dark .nee-paper,
    .dark .nee-paper {
        background: #ffffff !important;
        color: #111111 !important;
        color-scheme: light !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .65) !important;
    }

    html.dark .nee-paper *,
    body.dark .nee-paper *,
    .dark .nee-paper * {
        color: #111111 !important;
        border-color: #111111 !important;
    }

    html.dark .nee-paper input,
    html.dark .nee-paper textarea,
    html.dark .nee-paper select,
    body.dark .nee-paper input,
    body.dark .nee-paper textarea,
    body.dark .nee-paper select,
    .dark .nee-paper input,
    .dark .nee-paper textarea,
    .dark .nee-paper select {
        background: transparent !important;
        background-color: transparent !important;
        color: #111111 !important;
        -webkit-text-fill-color: #111111 !important;
        border-color: #111111 !important;
        box-shadow: none !important;
    }

    html.dark .nee-paper input::placeholder,
    html.dark .nee-paper textarea::placeholder,
    body.dark .nee-paper input::placeholder,
    body.dark .nee-paper textarea::placeholder,
    .dark .nee-paper input::placeholder,
    .dark .nee-paper textarea::placeholder {
        color: #6b7280 !important;
        -webkit-text-fill-color: #6b7280 !important;
    }

    html.dark .nee-paper input[type="checkbox"],
    html.dark .nee-paper input[type="radio"],
    body.dark .nee-paper input[type="checkbox"],
    body.dark .nee-paper input[type="radio"],
    .dark .nee-paper input[type="checkbox"],
    .dark .nee-paper input[type="radio"] {
        background: #ffffff !important;
        background-color: #ffffff !important;
        border: 1.2px solid #111111 !important;
        accent-color: #111111 !important;
        -webkit-text-fill-color: initial !important;
    }

    html.dark .nee-bio-page .bio-check input:checked::after,
    body.dark .nee-bio-page .bio-check input:checked::after,
    .dark .nee-bio-page .bio-check input:checked::after {
        color: #111111 !important;
    }

    @media print {
        .nee-shell {
            background: #ffffff !important;
            border: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
        }

        .nee-paper {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }

        .nee-paper:last-child {
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
    }

    .nee-shell {
        background: #ffffff !important;
        padding: 0 !important;
        border-radius: 0 !important;
    }

    .nee-paper {
        width: 794px !important;
        min-height: 1120px !important;
        margin: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
    }

    .nee-paper:last-child {
        page-break-after: auto !important;
    }
    @endif
</style>

<div class="nee-shell">
    {{-- PAGE 1 --}}
    <div class="nee-paper nee-application-page">
        <div class="app-kingdom">
            {!! $t('kingdom') !!}
        </div>

        <div class="app-year-small">{{ $t('year_2025') }}</div>

        <div class="app-ministry">
            {!! $t('ministry') !!}
        </div>

        <div class="app-title">
            {{ $t('application_title') }}
        </div>

        <div class="app-row two">
            <span class="app-label">{{ $t('applicant_name') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.name" value="{{ $v('name') }}">

            <span class="app-label">{{ $t('latin_name') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.latin_name" value="{{ $v('latin_name') }}">
        </div>

        <div class="app-row identity">
            <span class="app-label">{{ $t('gender') }}</span>
            <input class="app-line" type="text" value="{{ $genderText }}" readonly>

            <span class="app-label">{{ $t('nationality') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.nationality" value="{{ $v('nationality') }}">

            <span class="app-label">{{ $t('born_day') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.date_of_birth" value="{{ $v('date_of_birth') }}">

            <span class="app-label">{{ $t('month') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.extra_data.birth_month" value="{{ $v('extra_data.birth_month') }}">

            <span class="app-label">{{ $t('year') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.extra_data.birth_year" value="{{ $v('extra_data.birth_year') }}">
        </div>

        <div class="app-row address">
            <span class="app-label">{{ $t('birth_place_village_group') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.birth_village" value="{{ $v('birth_village') }}">

            <span class="app-label">{{ $t('commune') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.birth_commune" value="{{ $v('birth_commune') }}">

            <span class="app-label">{{ $t('district') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.birth_district" value="{{ $v('birth_district') }}">
        </div>

        <div class="app-row two">
            <span class="app-label">{{ $t('province') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.birth_province" value="{{ $v('birth_province') }}">

            <span class="app-label">{{ $t('current_place') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.current_address" value="{{ $v('current_address') }}">
        </div>

        <div class="app-row work">
            <span class="app-label">{{ $t('workplace') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.extra_data.workplace" value="{{ $v('extra_data.workplace') }}">

            <span class="app-label">{{ $t('phone') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.phone" value="{{ $v('phone') }}">
        </div>

        <div class="app-subtitle">
            {!! $t('respect_to') !!}
        </div>

        <div class="app-paragraph">
            {{ $t('purpose_paragraph') }}
        </div>

        <div class="app-row two">
            <span class="app-label">{{ $t('faculty_major') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.faculty_applied" value="{{ $v('faculty_applied') }}">

            <span class="app-label">{{ $t('major') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.major_applied" value="{{ $v('major_applied') }}">
        </div>

        <div class="app-row two">
            <span class="app-label">{{ $t('academic_year') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.academic_year" value="{{ $v('academic_year') }}">

            <span class="app-label">{{ $t('exam_year') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.exam_year" value="{{ $v('exam_year') }}">
        </div>

        <div class="app-paragraph">
            {{ $t('study_condition_paragraph') }}
        </div>

        <div class="app-paragraph">
            {{ $t('agreement_paragraph') }}
        </div>

        <div class="app-doc-title">
            {{ $t('documents_title') }}
        </div>

        <div class="app-doc-row">
            <span>{{ $t('doc_identity') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.national_id" value="{{ $v('national_id') }}">
            <span>{{ $t('one_copy') }}</span>
        </div>

        <div class="app-doc-row">
            <span>{{ $t('doc_transcript') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.bac_certificate_no" value="{{ $v('bac_certificate_no') }}">
            <span>{{ $t('two_copies') }}</span>
        </div>

        <div class="app-doc-row">
            <span>{{ $t('doc_photo') }}</span>
            <input class="app-line" type="text" value="{{ $t('two_photos') }}" readonly>
            <span></span>
        </div>

        <div class="app-doc-row">
            <span>{{ $t('doc_other') }}</span>
            <input class="app-line" type="text" wire:model.blur="data.note" value="{{ $v('note') }}">
            <span>{{ $t('one_copy') }}</span>
        </div>

        <div class="app-paragraph">
            {{ $t('truth_paragraph') }}
        </div>

        <div class="app-signature">
            <div class="app-sign-box">
                {!! $t('checked_complete') !!}
            </div>

            <div class="app-sign-box">
                <div class="app-date">
                    <span>{{ $t('day') }}</span>
                    <input type="text" wire:model.blur="data.extra_data.application_day" value="{{ $v('extra_data.application_day') }}">
                    <span>{{ $t('month') }}</span>
                    <input type="text" wire:model.blur="data.extra_data.application_month" value="{{ $v('extra_data.application_month') }}">
                    <span>{{ $t('year') }}</span>
                    <input type="text" wire:model.blur="data.extra_data.application_year" value="{{ $v('extra_data.application_year') }}">
                </div>

                {{ $t('candidate_signature_name') }}<br><br><br>
                <input class="app-line" style="width: 170px; text-align:center;" type="text" wire:model.blur="data.name" value="{{ $v('name') }}">
            </div>
        </div>

        <div class="nee-page-number">1</div>
    </div>

    {{-- PAGE 2 / BIOGRAPHY CLEAN --}}
    <div class="nee-paper nee-bio-page">
        <div class="bio-header">
            <div class="bio-kingdom">
                {!! $t('kingdom') !!}
            </div>
            <div class="bio-year">{{ $t('year_2025') }}</div>

            <div class="bio-title">{{ $t('bio_title') }}</div>
            <div class="bio-code">{{ $t('bio_code') }}</div>
        </div>

        <div class="bio-photo-box">
            <label>
                <input type="file" accept="image/png,image/jpeg,image/jpg,image/webp" wire:model.live="data.photo_path">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Candidate Photo">
                @else
                    <div>
                        {!! $t('photo_placeholder') !!}
                    </div>
                @endif
            </label>
        </div>

        <div class="bio-form">
            <div class="bio-row two">
                <span class="bio-no">{{ $t('no_1') }}</span>
                <span class="bio-label">{{ $t('bio_full_name') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.name" value="{{ $v('name') }}">
                <span class="bio-label">{{ $t('bio_latin_name') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.extra_data.nickname" value="{{ $v('extra_data.nickname') }}">
            </div>

            <div class="bio-row three">
                <span class="bio-no"></span>
                <span class="bio-label">{{ $t('bio_gender') }}</span>
                <input class="bio-line" type="text" value="{{ $genderText }}" readonly>
                <span class="bio-label">{{ $t('bio_nationality') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.nationality" value="{{ $v('nationality') }}">
                <span class="bio-label">{{ $t('bio_age') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.age" value="{{ $v('age') }}">
            </div>

            <div class="bio-row">
                <span class="bio-no">{{ $t('no_2') }}</span>
                <span class="bio-label">{{ $t('bio_dob') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.date_of_birth" value="{{ $v('date_of_birth') }}">
            </div>

            <div class="bio-row">
                <span class="bio-no">{{ $t('no_3') }}</span>
                <span class="bio-label">{{ $t('bio_birth_place') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.birth_place" value="{{ $v('birth_place') }}">
            </div>

            <div class="bio-row two">
                <span class="bio-no">{{ $t('no_4') }}</span>
                <span class="bio-label">{{ $t('bio_education_level') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.education_level" value="{{ $v('education_level') }}">
                <span class="bio-label">{{ $t('bio_academic_year') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.academic_year" value="{{ $v('academic_year') }}">
            </div>

            <div class="bio-row two">
                <span class="bio-no">{{ $t('no_5') }}</span>
                <span class="bio-label">{{ $t('bio_current_house_no') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.current_house_no" value="{{ $v('current_house_no') }}">
                <span class="bio-label">{{ $t('bio_group_no') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.current_group" value="{{ $v('current_group') }}">
            </div>

            <div class="bio-row no-number">
                <span></span>
                <span class="bio-label">{{ $t('bio_current_study_work') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.current_address" value="{{ $v('current_address') }}">
                <span class="bio-label">{{ $t('bio_group_no') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.current_group" value="{{ $v('current_group') }}">
            </div>

            <div class="bio-row">
                <span class="bio-no">{{ $t('no_6') }}</span>
                <span class="bio-label">{{ $t('bio_foreign_language') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.extra_data.foreign_language" value="{{ $v('extra_data.foreign_language') }}">
            </div>

            <div class="bio-row">
                <span class="bio-no">{{ $t('no_7') }}</span>
                <span class="bio-label">{{ $t('bio_current_address') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.current_address" value="{{ $v('current_address') }}">
            </div>

            <div class="bio-check-row">
                <span class="bio-no">{{ $t('no_8') }}</span>
                <span>{{ $t('bio_family_status') }}</span>

                <label class="bio-check">
                    <span>{{ $t('bio_single') }}</span>
                    <input
                        type="radio"
                        value="single"
                        wire:model.live="data.marital_status"
                        @checked($maritalStatus === 'single' || $maritalStatus === 'នៅលីវ')
                    >
                </label>

                <label class="bio-check">
                    <span>{{ $t('bio_married') }}</span>
                    <input
                        type="radio"
                        value="married"
                        wire:model.live="data.marital_status"
                        @checked($maritalStatus === 'married' || $maritalStatus === 'មានគ្រួសារ')
                    >
                </label>
            </div>

            <div class="bio-row family">
                <span></span>
                <span class="bio-label">{{ $t('bio_spouse_name') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.spouse_name" value="{{ $v('spouse_name') }}">
                <span class="bio-label">{{ $t('bio_dob') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.spouse_date_of_birth" value="{{ $v('spouse_date_of_birth') }}">
                <span class="bio-label">{{ $t('bio_nationality') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.spouse_nationality" value="{{ $v('spouse_nationality') }}">
            </div>

            <div class="bio-row family-small">
                <span></span>
                <span class="bio-label">{{ $t('bio_occupation') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.spouse_occupation" value="{{ $v('spouse_occupation') }}">
                <span class="bio-label"></span>
                <input class="bio-line" type="text">
            </div>

            <div class="bio-row family">
                <span></span>
                <span class="bio-label">{{ $t('bio_father_name') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.father_name" value="{{ $v('father_name') }}">
                <span class="bio-label">{{ $t('bio_alive_dead_age') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.father_age" value="{{ $v('father_age') }}">
                <span class="bio-label">{{ $t('bio_birth_place') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.father_address" value="{{ $v('father_address') }}">
            </div>

            <div class="bio-row family-small">
                <span></span>
                <span class="bio-label">{{ $t('bio_nationality') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.father_nationality" value="{{ $v('father_nationality') }}">
                <span class="bio-label">{{ $t('bio_occupation_plain') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.father_occupation" value="{{ $v('father_occupation') }}">
            </div>

            <div class="bio-row family">
                <span></span>
                <span class="bio-label">{{ $t('bio_mother_name') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.mother_name" value="{{ $v('mother_name') }}">
                <span class="bio-label">{{ $t('bio_alive_dead_age') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.mother_age" value="{{ $v('mother_age') }}">
                <span class="bio-label">{{ $t('bio_birth_place') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.mother_address" value="{{ $v('mother_address') }}">
            </div>

            <div class="bio-row family-small">
                <span></span>
                <span class="bio-label">{{ $t('bio_nationality') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.mother_nationality" value="{{ $v('mother_nationality') }}">
                <span class="bio-label">{{ $t('bio_occupation_plain') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.mother_occupation" value="{{ $v('mother_occupation') }}">
            </div>

            <div class="bio-row">
                <span class="bio-no">{{ $t('no_9') }}</span>
                <span class="bio-label">{{ $t('bio_contact_address') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.guardian_address" value="{{ $v('guardian_address') }}">
            </div>

            <div class="bio-row">
                <span></span>
                <span class="bio-label">{{ $t('bio_phone') }}</span>
                <input class="bio-line" type="text" wire:model.blur="data.guardian_phone" value="{{ $v('guardian_phone') }}">
            </div>
        </div>

        <div class="bio-confirm">
            {{ $t('bio_confirm') }}
        </div>

        <div class="bio-signature">
            <div class="bio-date">
                <span>{{ $t('day') }}</span>
                <input type="text" wire:model.blur="data.extra_data.bio_day" value="{{ $v('extra_data.bio_day') }}">
                <span>{{ $t('month') }}</span>
                <input type="text" wire:model.blur="data.extra_data.bio_month" value="{{ $v('extra_data.bio_month') }}">
                <span>{{ $t('year') }}</span>
                <input type="text" wire:model.blur="data.extra_data.bio_year" value="{{ $v('extra_data.bio_year') }}">
            </div>

            <div>
                {{ $t('bio_candidate_date') }}
                <input class="bio-sign-name" type="text" wire:model.blur="data.extra_data.sign_day" value="{{ $v('extra_data.sign_day') }}">
                {{ $t('month') }}
                <input class="bio-sign-name" type="text" wire:model.blur="data.extra_data.sign_month" value="{{ $v('extra_data.sign_month') }}">
                {{ $t('year_2026') }}
            </div>

            <div>{{ $t('bio_self_signature') }}</div>
        </div>

        <div class="bio-page-number">2</div>
    </div>
</div>
