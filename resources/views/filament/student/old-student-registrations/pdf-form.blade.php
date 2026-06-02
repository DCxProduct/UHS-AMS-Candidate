@php
    /*
    |--------------------------------------------------------------------------
    | Old Student Registration Form Preview
    |--------------------------------------------------------------------------
    | Path:
    | resources/views/filament/student/old-student-registrations/pdf-form.blade.php
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

    $dateDigits = function (?string $value): string {
        $value = (string) $value;
        $digits = preg_replace('/\D+/', '', $value) ?: '';

        if (strlen($digits) === 8) {
            return $digits;
        }

        try {
            if (filled($value)) {
                return \Carbon\Carbon::parse($value)->format('dmY');
            }
        } catch (\Throwable $e) {
            return $digits;
        }

        return $digits;
    };

    $logoPath = public_path('images/UHS_logo.png');
    $logoUrl = $isPdfExport
        ? (file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '')
        : asset('images/UHS_logo.png');

    $photoValue = data_get($data, 'photo_path');

    if (is_array($photoValue)) {
        $photoValue = reset($photoValue);
    }

    $photoUrl = null;

    if ($photoValue instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
        $photoUrl = $photoValue->temporaryUrl();
    } elseif (is_string($photoValue) && filled($photoValue)) {
        if ($isPdfExport) {
            $photoPath = storage_path('app/public/' . $photoValue);

            if (file_exists($photoPath)) {
                $photoUrl = 'data:image/png;base64,' . base64_encode(file_get_contents($photoPath));
            }
        } else {
            $photoUrl = \Illuminate\Support\Facades\Storage::url($photoValue);
        }
    }

    $sex = $v('sex');
    $isMale = in_array($sex, ['male', 'ប្រុស'], true);
    $isFemale = in_array($sex, ['female', 'ស្រី'], true);

    $maritalStatus = $v('marital_status');
    $isSingle = in_array($maritalStatus, ['single', 'នៅលីវ'], true);
    $isMarried = in_array($maritalStatus, ['married', 'រៀបការ'], true);

    $studentType = $v('student_type');
    $isRegular = in_array($studentType, ['regular', 'បង់ថ្លៃ', 'fee'], true);
    $isScholarship = in_array($studentType, ['scholarship', 'អាហារូបករណ៍'], true);


    $t = function (string $key, ?string $default = null): string {
        $translationKey = 'old_student_registrations.pdf.' . $key;
        $translated = __($translationKey);

        return $translated === $translationKey
            ? (string) ($default ?? $key)
            : (string) $translated;
    };
@endphp

<style>
    @font-face {
        font-family: "KhmerOSBattambang";
        src:
            url("{{ asset('KhmerOS_battambang.ttf') }}") format("truetype"),
            url("{{ asset('fonts/KhmerOS_battambang.ttf') }}") format("truetype");
        font-weight: 400;
        font-style: normal;
    }

    @font-face {
        font-family: "KhmerOSMuolLight";
        src:
            url("{{ asset('KhmerOS_muollight.ttf') }}") format("truetype"),
            url("{{ asset('fonts/KhmerOS_muollight.ttf') }}") format("truetype");
        font-weight: 400;
        font-style: normal;
    }

    .osr-wrap {
        width: 100%;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        padding: 18px;
        border-radius: 18px;
        overflow-x: auto;
    }

    .osr-page {
        width: 794px;
        min-height: 1123px;
        margin: 0 auto;
        background: #ffffff !important;
        color: #111111 !important;
        color-scheme: light !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .18);
        padding: 24px 28px;
        position: relative;
        font-family: "KhmerOSBattambang", "Noto Sans Khmer", Arial, sans-serif !important;
        font-size: 10.2px;
        line-height: 1.18;
    }

    .osr-page *,
    .osr-page *::before,
    .osr-page *::after {
        box-sizing: border-box;
        font-family: "KhmerOSBattambang", "Noto Sans Khmer", Arial, sans-serif !important;
    }

    .osr-muol,
    .osr-title-kh {
        font-family: "KhmerOSMuolLight", "KhmerOSBattambang", serif !important;
        font-weight: 400 !important;
    }

    .osr-top-code {
        position: absolute;
        top: 14px;
        right: 34px;
        font-family: "Times New Roman", serif !important;
        font-size: 9px;
        font-style: italic;
        text-decoration: underline;
    }

    .osr-top-line {
        border-top: 1px solid #111;
        margin-top: 9px;
        margin-bottom: 10px;
    }

    .osr-header {
        display: grid;
        grid-template-columns: 230px 1fr 116px;
        align-items: start;
        gap: 12px;
        min-height: 158px;
    }

    .osr-logo {
        width: 220px;
        height: auto;
        object-fit: contain;
        display: block;
        margin-top: 10px;
    }

    .osr-title-box {
        text-align: center;
        padding-top: 58px;
    }

    .osr-title-kh {
        font-size: 21px;
        line-height: 1.35;
    }

    .osr-title-en {
        font-family: Georgia, "Times New Roman", serif !important;
        font-size: 15px;
        font-weight: 700;
        margin-top: 3px;
    }

    .osr-divider {
        font-size: 8px;
        margin-top: 5px;
        letter-spacing: 1px;
    }

    .osr-photo {
        width: 106px;
        height: 145px;
        border: 1px solid #9aa2a6;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        margin-left: auto;
        margin-top: 10px;
        overflow: hidden;
        background: #fff;
        font-size: 10px;
        line-height: 1.5;
    }

    .osr-photo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .osr-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        border: 1.15px solid #111;
    }

    .osr-table td,
    .osr-table th {
        border: 1.15px solid #111;
        padding: 2px 4px;
        height: 30px;
        vertical-align: middle;
        font-size: 9.6px;
        line-height: 1.1;
    }

    .osr-main-table {
        margin-top: 8px;
    }

    .osr-center {
        text-align: center;
    }

    .osr-bold {
        font-weight: 700;
    }

    .osr-en {
        display: block;
        font-family: Arial, sans-serif !important;
        font-size: 7px;
        line-height: 1.05;
        color: #111;
    }

    .osr-input {
        width: 100%;
        height: 17px;
        border: 0;
        border-bottom: 1px dotted #111;
        outline: none;
        background: transparent !important;
        color: #111 !important;
        -webkit-text-fill-color: #111 !important;
        padding: 0 3px;
        font-size: 10px;
    }

    .osr-input-no-line {
        width: 100%;
        height: 17px;
        border: 0;
        outline: none;
        background: transparent !important;
        color: #111 !important;
        -webkit-text-fill-color: #111 !important;
        padding: 0 3px;
        font-size: 10px;
    }

    .osr-box-input {
        width: 100%;
        height: 29px;
        border: 0;
        outline: none;
        background-image: linear-gradient(to right, transparent 0, transparent 27px, #111 28px);
        background-size: 28px 100%;
        background-color: transparent;
        letter-spacing: 11px;
        padding-left: 8px;
        font-family: "Courier New", monospace !important;
        font-size: 14px;
        text-transform: uppercase;
    }

    .osr-date-input {
        width: 100%;
        height: 26px;
        border: 0;
        outline: none;
        background-image: linear-gradient(to right, transparent 0, transparent 26px, #111 27px);
        background-size: 27px 100%;
        background-color: transparent;
        letter-spacing: 10px;
        padding-left: 8px;
        font-family: "Courier New", monospace !important;
        font-size: 13px;
    }

    .osr-check {
        appearance: none;
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        border: 1px solid #111;
        background: #fff;
        display: inline-block;
        vertical-align: middle;
        position: relative;
        margin: 0 3px;
    }

    .osr-check:checked::after {
        content: "✓";
        position: absolute;
        left: 1px;
        top: -8px;
        font-size: 16px;
        font-weight: 900;
    }

    .osr-blue-row td {
        background: #def4f4;
    }

    .osr-note {
        margin-top: 8px;
        border: 1.15px solid #111;
        min-height: 52px;
        padding: 8px 10px;
        text-align: justify;
        font-size: 10.6px;
        line-height: 1.55;
    }

    .osr-note-small {
        margin-top: 3px;
        font-size: 8.6px;
        line-height: 1.45;
        text-align: justify;
        font-style: italic;
    }

    .osr-sign-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 70px;
        margin-top: 25px;
        text-align: center;
        line-height: 1.7;
        font-size: 10.8px;
    }

    .osr-line-fixed {
        display: inline-block;
        min-height: 16px;
        border-bottom: 1px dotted #111;
        padding: 0 3px;
        vertical-align: bottom;
    }

    .osr-footer {
        position: absolute;
        left: 42px;
        right: 42px;
        bottom: 28px;
        border-top: 1px solid #111;
        padding-top: 4px;
        color: #1683c7;
        font-size: 8px;
        line-height: 1.3;
    }



    /*
    |--------------------------------------------------------------------------
    | Dark Mode / Light Mode Preview - Same Style as document-requests
    |--------------------------------------------------------------------------
    | Light mode  = wrapper ពណ៌ប្រផេះស្រាល
    | Dark mode   = wrapper ខ្មៅ
    | Paper form  = នៅតែស
    | Text/line   = នៅតែខ្មៅ
    | Input       = មិនប្តូរទៅពណ៌ dark
    | Checkbox    = នៅស្អាតក្នុង dark mode
    */

    html.dark .osr-wrap,
    body.dark .osr-wrap,
    .dark .osr-wrap {
        background: #05070d !important;
        border-color: #27272a !important;
    }

    html.dark .osr-page,
    body.dark .osr-page,
    .dark .osr-page {
        background: #ffffff !important;
        color: #111111 !important;
        color-scheme: light !important;
        box-shadow: 0 12px 30px rgba(0, 0, 0, .65) !important;
    }

    html.dark .osr-page *,
    body.dark .osr-page *,
    .dark .osr-page * {
        color: #111111 !important;
        border-color: #111111 !important;
    }

    html.dark .osr-page input,
    html.dark .osr-page textarea,
    html.dark .osr-page select,
    body.dark .osr-page input,
    body.dark .osr-page textarea,
    body.dark .osr-page select,
    .dark .osr-page input,
    .dark .osr-page textarea,
    .dark .osr-page select {
        background-color: transparent !important;
        color: #111111 !important;
        -webkit-text-fill-color: #111111 !important;
        border-color: #111111 !important;
        box-shadow: none !important;
    }

    html.dark .osr-page .osr-box-input,
    html.dark .osr-page .osr-date-input,
    body.dark .osr-page .osr-box-input,
    body.dark .osr-page .osr-date-input,
    .dark .osr-page .osr-box-input,
    .dark .osr-page .osr-date-input {
        background-color: transparent !important;
        color: #111111 !important;
        -webkit-text-fill-color: #111111 !important;
    }

    html.dark .osr-page input[type="checkbox"],
    html.dark .osr-page input[type="radio"],
    body.dark .osr-page input[type="checkbox"],
    body.dark .osr-page input[type="radio"],
    .dark .osr-page input[type="checkbox"],
    .dark .osr-page input[type="radio"] {
        background-color: #ffffff !important;
        border: 1px solid #111111 !important;
        accent-color: #111111 !important;
        -webkit-text-fill-color: initial !important;
    }

    html.dark .osr-page input[type="checkbox"]:checked::after,
    html.dark .osr-page input[type="radio"]:checked::after,
    body.dark .osr-page input[type="checkbox"]:checked::after,
    body.dark .osr-page input[type="radio"]:checked::after,
    .dark .osr-page input[type="checkbox"]:checked::after,
    .dark .osr-page input[type="radio"]:checked::after {
        color: #111111 !important;
        -webkit-text-fill-color: #111111 !important;
    }

    html.dark .osr-page .osr-blue-row td,
    body.dark .osr-page .osr-blue-row td,
    .dark .osr-page .osr-blue-row td {
        background: #def4f4 !important;
        color: #111111 !important;
    }

    html.dark .osr-page .osr-photo,
    body.dark .osr-page .osr-photo,
    .dark .osr-page .osr-photo {
        background: #ffffff !important;
        color: #111111 !important;
        border-color: #111111 !important;
    }

    @media print {
        .osr-wrap {
            padding: 0;
            background: #fff;
            border-radius: 0;
        }

        .osr-page {
            box-shadow: none;
            margin: 0;
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
            background: #fff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .osr-wrap {
            width: 210mm !important;
            padding: 0 !important;
            background: #fff !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .osr-page {
            width: 794px !important;
            height: 1123px !important;
            min-height: 1123px !important;
            margin: 0 !important;
            box-shadow: none !important;
            overflow: hidden !important;
        }

        .osr-page input {
            pointer-events: none !important;
        }
    @endif
</style>

<div class="osr-wrap">
    <div class="osr-page">
        <div class="osr-top-code">F-Reg002 (V-0.1)</div>
        <div class="osr-top-line"></div>

        <div class="osr-header">
            <div>
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" class="osr-logo" alt="UHS Logo">
                @endif
            </div>

            <div class="osr-title-box">
                <div class="osr-title-kh">{{ $t('title_kh') }}</div>
                <div class="osr-title-en">({{ $t('title_en') }})</div>
                <div class="osr-divider">───────</div>
            </div>

            <div class="osr-photo">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="Student Photo">
                @else
                    {{ $t('photo') }}
                @endif
            </div>
        </div>

        <table class="osr-table osr-main-table">
            <colgroup>
                <col style="width: 20%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 5%;">
                <col style="width: 20%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
            </colgroup>

            <tr>
                <td class="osr-center">
                    {{ $t('student_id') }}
                    <span class="osr-en">{{ $t('student_id_en') }}</span>
                </td>
                <td colspan="8">
                    <input class="osr-box-input" type="text" maxlength="10" wire:model.blur="data.student_id">
                </td>
                <td colspan="3">
                    {{ $t('sex') }}៖
                    <label><input class="osr-check" type="radio" value="male" wire:model.live="data.sex" @checked($isMale)> {{ $t('male') }}</label>
                    <label><input class="osr-check" type="radio" value="female" wire:model.live="data.sex" @checked($isFemale)> {{ $t('female') }}</label>
                    <span class="osr-en">{{ $t('sex_en') }} &nbsp;&nbsp; {{ $t('male_en') }} &nbsp;&nbsp; {{ $t('female_en') }}</span>
                </td>
            </tr>

            <tr>
                <td colspan="5">
                    {{ $t('khmer_name') }} {{ $t('family_name_kh') }}
                    <input class="osr-input" type="text" wire:model.blur="data.khmer_name">
                </td>
                <td colspan="4">
                    {{ $t('first_name_kh') }}
                    <input class="osr-input" type="text" wire:model.blur="data.first_name">
                </td>
                <td colspan="3">
                    {{ $t('full_name_kh') }}
                    <input class="osr-input" type="text" wire:model.blur="data.khmer_name">
                </td>
            </tr>

            <tr>
                <td colspan="5">
                    {{ $t('english_name') }}
                    <span class="osr-en">({{ $t('block_letter') }})</span>
                    <input class="osr-input" type="text" wire:model.blur="data.family_name">
                    <span class="osr-en">{{ $t('family_name_en') }}</span>
                </td>
                <td colspan="7">
                    <input class="osr-input" type="text" wire:model.blur="data.first_name">
                    <span class="osr-en">{{ $t('first_name_en') }}</span>
                </td>
            </tr>

            <tr>
                <td class="osr-center">
                    {{ $t('date_of_birth') }}
                    <span class="osr-en">{{ $t('date_of_birth_en') }}</span>
                </td>
                <td colspan="5">
                    <input class="osr-date-input" type="text" value="{{ $dateDigits($v('date_of_birth')) }}" wire:model.blur="data.date_of_birth">
                </td>
                <td colspan="3">
                    {{ $t('nationality') }}៖
                    <input class="osr-input" type="text" wire:model.blur="data.nationality">
                    <span class="osr-en">{{ $t('nationality_en') }}</span>
                </td>
                <td colspan="3">
                    {{ $t('religion') }}៖
                    <input class="osr-input" type="text" wire:model.blur="data.religion">
                    <span class="osr-en">{{ $t('religion_en') }}</span>
                </td>
            </tr>

            <tr>
                <td class="osr-center">
                    {{ $t('place_of_birth') }}
                    <span class="osr-en">{{ $t('place_of_birth_en') }}</span>
                </td>
                <td colspan="6">
                    <input class="osr-input-no-line" type="text" wire:model.blur="data.place_of_birth">
                </td>
                <td colspan="5">
                    {{ $t('marital_status') }}៖
                    <label><input class="osr-check" type="radio" value="single" wire:model.live="data.marital_status" @checked($isSingle)> {{ $t('single') }}</label>
                    <label><input class="osr-check" type="radio" value="married" wire:model.live="data.marital_status" @checked($isMarried)> {{ $t('married') }}</label>
                    <span class="osr-en">{{ $t('marital_status_en') }} &nbsp; {{ $t('single_en') }} &nbsp; {{ $t('married_en') }}</span>
                </td>
            </tr>

            <tr>
                <td class="osr-center">
                    {{ $t('current_job') }}
                    <span class="osr-en">{{ $t('current_job_en') }}</span>
                </td>
                <td colspan="5">
                    <input class="osr-input-no-line" type="text" wire:model.blur="data.current_job">
                </td>
                <td colspan="6">
                    {{ $t('institution') }}
                    <input class="osr-input" type="text" wire:model.blur="data.institution">
                    <span class="osr-en">{{ $t('institution_en') }}</span>
                </td>
            </tr>

            <tr class="osr-blue-row">
                <td colspan="7">
                    {{ $t('register_for_course') }}
                    <span class="osr-en">{{ $t('register_for_course_en') }}</span>
                    <input class="osr-input-no-line" type="text" wire:model.blur="data.workshop_course">
                </td>
                <td colspan="2" class="osr-center">
                    {{ $t('student_type') }}៖
                    <span class="osr-en">{{ $t('student_type_en') }}</span>
                </td>
                <td class="osr-center">
                    <label><input class="osr-check" type="radio" value="regular" wire:model.live="data.student_type" @checked($isRegular)> {{ $t('regular') }}</label>
                    <span class="osr-en">{{ $t('regular_en') }}</span>
                </td>
                <td colspan="2" class="osr-center">
                    <label><input class="osr-check" type="radio" value="scholarship" wire:model.live="data.student_type" @checked($isScholarship)> {{ $t('scholarship') }}</label>
                    <span class="osr-en">{{ $t('scholarship_en') }}</span>
                </td>
            </tr>
        </table>

        <table class="osr-table" style="margin-top: 10px;">
            <colgroup>
                <col style="width: 18%;">
                <col style="width: 10%;">
                <col style="width: 14%;">
                <col style="width: 16%;">
                <col style="width: 20%;">
                <col style="width: 22%;">
            </colgroup>

            <tr>
                <td>{{ $t('permanent_address') }}<span class="osr-en">{{ $t('permanent_address_en') }}</span></td>
                <td>{{ $t('house_no') }}<input class="osr-input" type="text" wire:model.blur="data.permanent_no"><span class="osr-en">{{ $t('house_no_en') }}</span></td>
                <td>{{ $t('street') }}<input class="osr-input" type="text" wire:model.blur="data.permanent_street"><span class="osr-en">{{ $t('street_en') }}</span></td>
                <td>{{ $t('sangkat') }}<input class="osr-input" type="text" wire:model.blur="data.permanent_sangkat"><span class="osr-en">{{ $t('sangkat_en') }}</span></td>
                <td>{{ $t('khan_district') }}<input class="osr-input" type="text" wire:model.blur="data.permanent_khan_district"><span class="osr-en">{{ $t('khan_district_en') }}</span></td>
                <td>{{ $t('city_state_country') }}<input class="osr-input" type="text" wire:model.blur="data.permanent_city_state_country"><span class="osr-en">{{ $t('city_state_country_en') }}</span></td>
            </tr>

            <tr>
                <td>{{ $t('current_address') }}<span class="osr-en">{{ $t('current_address_en') }}</span></td>
                <td>{{ $t('house_no') }}<input class="osr-input" type="text" wire:model.blur="data.current_no"><span class="osr-en">{{ $t('house_no_en') }}</span></td>
                <td>{{ $t('street') }}<input class="osr-input" type="text" wire:model.blur="data.current_street"><span class="osr-en">{{ $t('street_en') }}</span></td>
                <td>{{ $t('sangkat') }}<input class="osr-input" type="text" wire:model.blur="data.current_sangkat"><span class="osr-en">{{ $t('sangkat_en') }}</span></td>
                <td>{{ $t('khan_district') }}<input class="osr-input" type="text" wire:model.blur="data.current_khan_district"><span class="osr-en">{{ $t('khan_district_en') }}</span></td>
                <td>{{ $t('city_country') }}<input class="osr-input" type="text" wire:model.blur="data.current_city_country"><span class="osr-en">{{ $t('city_country_en') }}</span></td>
            </tr>

            <tr>
                <td colspan="3">{{ $t('phone_no') }}<input class="osr-input" type="text" wire:model.blur="data.phone_no"><span class="osr-en">{{ $t('phone_no_en') }}</span></td>
                <td colspan="3">{{ $t('email') }}<input class="osr-input" type="email" wire:model.blur="data.email"><span class="osr-en">{{ $t('email_en') }}</span></td>
            </tr>

            <tr>
                <td colspan="2">{{ $t('father_name') }}៖<input class="osr-input" type="text" wire:model.blur="data.father_name"><span class="osr-en">{{ $t('father_name_en') }}</span></td>
                <td>{{ $t('year_of_birth') }}៖<input class="osr-input" type="text" wire:model.blur="data.father_year_of_birth"><span class="osr-en">{{ $t('year_of_birth_en') }}</span></td>
                <td>{{ $t('alive_dead') }}៖<input class="osr-input" type="text" wire:model.blur="data.extra_data.father_status"><span class="osr-en">{{ $t('alive_dead_en') }}</span></td>
                <td colspan="2">{{ $t('occupation') }}៖<input class="osr-input" type="text" wire:model.blur="data.father_occupation"><span class="osr-en">{{ $t('occupation_en') }}</span></td>
            </tr>

            <tr>
                <td colspan="2">{{ $t('mother_name') }}៖<input class="osr-input" type="text" wire:model.blur="data.mother_name"><span class="osr-en">{{ $t('mother_name_en') }}</span></td>
                <td>{{ $t('year_of_birth') }}៖<input class="osr-input" type="text" wire:model.blur="data.mother_year_of_birth"><span class="osr-en">{{ $t('year_of_birth_en') }}</span></td>
                <td>{{ $t('alive_dead') }}៖<input class="osr-input" type="text" wire:model.blur="data.extra_data.mother_status"><span class="osr-en">{{ $t('alive_dead_en') }}</span></td>
                <td colspan="2">{{ $t('occupation') }}៖<input class="osr-input" type="text" wire:model.blur="data.mother_occupation"><span class="osr-en">{{ $t('occupation_en') }}</span></td>
            </tr>

            <tr>
                <td colspan="6" style="height: 48px;">
                    {{ $t('contact_person_contact_no') }}
                    <input class="osr-input" type="text" wire:model.blur="data.contact_person">
                    <span class="osr-en">{{ $t('contact_person_contact_no_en') }}</span>
                    <input class="osr-input" type="text" wire:model.blur="data.contact_no">
                </td>
            </tr>
        </table>

        <div class="osr-note">
            {{ $t('guarantee_note') }}
        </div>

        <div class="osr-note-small">
            {{ $t('consent_note') }}
        </div>

        <div class="osr-sign-row">
            <div>
                {{ $t('checked_correctly') }}<br>
                {{ $t('receiver') }}
            </div>

            <div>
                {{ $t('phnom_penh_date') }}
                <span class="osr-line-fixed" style="width: 32px;"></span>
                {{ $t('month') }}
                <span class="osr-line-fixed" style="width: 42px;"></span>
                {{ $t('year_20') }}
                <span class="osr-line-fixed" style="width: 32px;"></span>
                <br>
                {{ $t('signature_name') }}<br>
                <span class="osr-en">({{ $t('signature_name_en') }})</span>
            </div>
        </div>

        <div class="osr-footer">
            {{ $t('footer_address') }} |
            {{ $t('footer_phone') }} |
            {{ $t('footer_email') }} |
            {{ $t('footer_website') }}
        </div>
    </div>
</div>
