{{-- PAGE 1 - Real HTML form design, not image background --}}
@php
    $logoPath = public_path('images/UHS_logo.png');

    $logoUrl = file_exists($logoPath)
        ? asset('images/UHS_logo.png')
        : '';
@endphp

<style>
    .uhs-form-wrapper {
        background: #eef2f7;
        border: 1px solid #d6dce5;
        border-radius: 18px;
        overflow-x: auto;
        padding: 24px 0;
    }

    html.dark .uhs-form-wrapper {
        background: #000;
        border-color: #374151;
    }

    .uhs-form-page {
        width: 900px;
        min-height: 1265px;
        margin: 0 auto 24px;
        background: #fff;
        color: #000;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        padding: 14px 34px 20px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        font-size: 12px;
        line-height: 1.35;
    }

    .uhs-form-page * {
        box-sizing: border-box;
    }

    .uhs-header {
        display: grid;
        grid-template-columns: 250px 1fr 150px;
        align-items: start;
        gap: 14px;
        margin-bottom: 10px;
    }

    .uhs-logo-area {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .uhs-logo {
        width: 82px;
        height: 82px;
        object-fit: contain;
    }

    .uhs-logo-title {
        font-size: 43px;
        font-weight: 900;
        color: #168fc9;
        line-height: 1;
        font-family: Arial, sans-serif;
        letter-spacing: 2px;
        border-bottom: 2px solid #168fc9;
        padding-bottom: 2px;
    }

    .uhs-logo-subtitle {
        font-size: 11px;
        margin-top: 4px;
        color: #111;
        font-family: Arial, sans-serif;
        white-space: nowrap;
    }

    .uhs-title {
        text-align: center;
        padding-top: 32px;
        font-weight: 900;
        color: #000;
    }

    .uhs-title-main {
        font-size: 20px;
        line-height: 1.35;
        text-decoration: underline;
    }

    .uhs-title-sub {
        font-size: 17px;
        margin-top: 4px;
    }

    .uhs-title-line {
        width: 70px;
        border-top: 1px solid #000;
        margin: 8px auto 0;
    }

    .uhs-photo-box {
        width: 145px;
        height: 150px;
        border: 1.5px solid #111;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 14px;
        line-height: 1.8;
        margin-left: auto;
    }

    .uhs-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        color: #000;
    }

    .uhs-table td,
    .uhs-table th {
        border: 1.2px solid #111;
        padding: 4px 6px;
        vertical-align: middle;
        height: 34px;
        font-size: 12px;
        font-weight: 400;
    }

    .uhs-table .center {
        text-align: center;
    }

    .uhs-table .bold {
        font-weight: 900;
    }

    .uhs-cell-flex {
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
    }

    .uhs-line-input {
        border: 0;
        border-bottom: 1px dotted #111;
        outline: none;
        background: transparent;
        height: 19px;
        flex: 1;
        min-width: 30px;
        color: #000;
        font-size: 12px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        padding: 0 2px;
    }

    .uhs-box-input {
        width: 100%;
        height: 100%;
        border: 0;
        outline: none;
        background: transparent;
        text-align: center;
        color: #000;
        font-size: 12px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
    }

    .uhs-checkbox {
        appearance: none;
        -webkit-appearance: none;
        width: 13px;
        height: 13px;
        border: 1.3px solid #111;
        background: #fff;
        margin: 0 4px;
        position: relative;
        vertical-align: middle;
        cursor: pointer;
    }

    .uhs-checkbox:checked::after {
        content: "✓";
        position: absolute;
        left: 0px;
        top: -7px;
        font-size: 18px;
        font-weight: 900;
        color: #000;
        line-height: 1;
    }

    .uhs-check-label {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        margin-right: 12px;
        white-space: nowrap;
    }

    .uhs-id-grid {
        display: grid;
        grid-template-columns: repeat(14, 1fr);
        height: 39px;
        margin: -4px -6px;
    }

    .uhs-id-box {
        border-left: 1.2px solid #111;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .uhs-id-box:first-child {
        border-left: 0;
    }

    .uhs-date-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        height: 43px;
        margin: -4px -6px;
    }

    .uhs-date-box {
        border-left: 1.2px solid #111;
        display: grid;
        grid-template-rows: 18px 1fr;
        text-align: center;
    }

    .uhs-date-box:first-child {
        border-left: 0;
    }

    .uhs-date-label {
        font-size: 11px;
        line-height: 18px;
        border-bottom: 1.2px solid #111;
    }

    .uhs-section-title {
        text-align: center;
        font-weight: 900;
        font-size: 13px;
        line-height: 1.6;
        padding: 8px 6px !important;
    }

    .uhs-small {
        font-size: 10.5px;
    }

    .uhs-bottom-note {
        font-size: 12px;
        line-height: 1.7;
        text-align: justify;
        padding: 8px 10px;
        border-left: 1.2px solid #111;
        border-right: 1.2px solid #111;
        border-bottom: 1.2px solid #111;
    }

    .uhs-signatures {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-top: 28px;
        text-align: center;
        font-size: 13px;
        line-height: 1.9;
        min-height: 140px;
    }

    .uhs-sign-date {
        display: inline-block;
        border: 0;
        border-bottom: 1px dotted #111;
        width: 55px;
        outline: none;
        background: transparent;
        text-align: center;
    }

    .uhs-footer {
        margin-top: 140px;
        border-top: 2px solid #111;
        padding-top: 4px;
        font-size: 9.5px;
        line-height: 1.5;
        color: #000;
    }

    .uhs-resume-page {
        width: 900px;
        min-height: 1265px;
        margin: 0 auto 24px;
        background: #fff;
        color: #000;
        box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        padding: 24px 28px 28px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        font-size: 12px;
        line-height: 1.45;
    }

    .uhs-resume-page * {
        box-sizing: border-box;
    }

    .uhs-resume-header {
        display: grid;
        grid-template-columns: 230px 1fr 145px;
        gap: 16px;
        align-items: start;
        margin-bottom: 10px;
    }

    .uhs-logo-card {
        border: 1.3px solid #111;
        height: 85px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
    }

    .uhs-logo-card img {
        width: 62px;
        height: 62px;
        object-fit: contain;
    }

    .uhs-logo-kh {
        font-size: 34px;
        font-weight: 900;
        line-height: 1;
        color: #111;
    }

    .uhs-logo-en {
        font-size: 10px;
        line-height: 1.3;
    }

    .uhs-resume-title {
        text-align: center;
        font-size: 21px;
        font-weight: 900;
        line-height: 1.55;
    }

    .uhs-resume-subtitle {
        display: inline-block;
        margin-top: 10px;
        border: 1.3px solid #111;
        padding: 4px 34px;
        font-size: 21px;
        font-weight: 900;
    }

    .uhs-resume-note {
        margin-top: 5px;
        font-size: 12px;
        text-align: center;
    }

    .uhs-photo-box {
        width: 130px;
        height: 150px;
        border: 1.4px solid #111;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 13px;
        line-height: 1.8;
        margin-left: auto;
    }

    .uhs-resume-section-title {
        font-size: 15px;
        font-weight: 900;
        margin-top: 8px;
        margin-bottom: 3px;
    }

    .uhs-resume-subsection {
        font-size: 13px;
        font-weight: 900;
        margin-top: 5px;
        margin-bottom: 2px;
    }

    .uhs-resume-row {
        display: flex;
        align-items: flex-end;
        gap: 6px;
        width: 100%;
        min-height: 25px;
        font-size: 12.5px;
        line-height: 1.5;
    }

    .uhs-resume-row.wrap {
        flex-wrap: wrap;
    }

    .uhs-line-input {
        border: 0;
        border-bottom: 1px dotted #111;
        outline: none;
        background: transparent;
        height: 18px;
        flex: 1;
        min-width: 60px;
        color: #000;
        font-size: 12px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        padding: 0 2px;
    }

    .uhs-line-input.sm {
        flex: 0 0 55px;
    }

    .uhs-line-input.md {
        flex: 0 0 115px;
    }

    .uhs-line-input.lg {
        flex: 0 0 190px;
    }

    .uhs-line-input.xl {
        flex: 0 0 300px;
    }

    .uhs-checkbox {
        appearance: none;
        -webkit-appearance: none;
        width: 13px;
        height: 13px;
        border: 1.3px solid #111;
        background: #fff;
        margin: 0 4px;
        position: relative;
        vertical-align: middle;
        cursor: pointer;
    }

    .uhs-checkbox:checked::after {
        content: "✓";
        position: absolute;
        left: 0;
        top: -7px;
        font-size: 18px;
        font-weight: 900;
        color: #000;
        line-height: 1;
    }

    .uhs-check-label {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        white-space: nowrap;
    }

    .uhs-resume-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 6px;
    }

    .uhs-resume-table th,
    .uhs-resume-table td {
        border: 1.2px solid #111;
        height: 31px;
        padding: 3px 5px;
        text-align: center;
        vertical-align: middle;
        font-size: 11px;
        font-weight: 400;
        line-height: 1.35;
    }

    .uhs-resume-table th {
        font-weight: 700;
    }

    .uhs-resume-table input {
        width: 100%;
        height: 100%;
        border: 0;
        outline: none;
        background: transparent;
        text-align: center;
        font-size: 11px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
    }

    .uhs-resume-table input:focus {
        background: rgba(255, 244, 130, 0.45);
    }

    .uhs-resume-declare {
        margin-top: 12px;
        font-size: 12.5px;
        line-height: 1.75;
        text-align: justify;
    }

    .uhs-resume-sign {
        margin-top: 14px;
        display: flex;
        justify-content: flex-end;
        text-align: center;
        font-size: 13px;
        line-height: 1.8;
    }

    .uhs-sign-box {
        width: 330px;
    }

    .uhs-sign-date {
        display: inline-block;
        width: 55px;
        border: 0;
        border-bottom: 1px dotted #111;
        outline: none;
        background: transparent;
        text-align: center;
    }

    @media print {
        .uhs-form-wrapper {
            background: #fff;
            border: none;
            border-radius: 0;
            padding: 0;
        }

        .uhs-form-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }
</style>

<div class="uhs-form-wrapper">
    <div class="uhs-form-page">

        {{-- Header --}}
        <div class="uhs-header">
            <div class="uhs-logo-area">
                <img src="{{ $logoUrl }}" class="uhs-logo" alt="UHS Logo">
                <div>
                    <div class="uhs-logo-title">UHS</div>
                    <div class="uhs-logo-subtitle">University of Health Sciences</div>
                </div>
            </div>

            <div class="uhs-title">
                <div class="uhs-title-main">
                    ពាក្យសុំចុះឈ្មោះចូលរៀន
                </div>
                <div class="uhs-title-sub">
                    (ឆ្នាំសិក្សា ២០ &nbsp;&nbsp; - &nbsp;&nbsp; ២០)
                </div>
                <div class="uhs-title-line"></div>
            </div>

            <div class="uhs-photo-box">
                បិទរូបថត<br>
                ៤ x ៦
            </div>
        </div>

        {{-- Main form table --}}
        <table class="uhs-table">
            <colgroup>
                <col style="width: 7%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
            </colgroup>

            {{-- 1 Student ID --}}
            <tr>
                <td colspan="2" class="center">
                    1. អត្តលេខ:<br>
                    <span class="uhs-small">Student ID</span>
                </td>
                <td colspan="9">
                    <div class="uhs-id-grid">
                        @for ($i = 0; $i < 14; $i++)
                            <div class="uhs-id-box">
                                <input type="text" name="student_id[]" maxlength="1" class="uhs-box-input">
                            </div>
                        @endfor
                    </div>
                </td>
            </tr>

            {{-- 2-3 --}}
            <tr>
                <td colspan="8">
                    <div class="uhs-cell-flex">
                        <span>2. គោត្តនាម និងនាម:</span>
                        <input type="text" name="name_kh" class="uhs-line-input">
                        <span>នាមខ្លួន:</span>
                        <input type="text" name="first_name_kh" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <span>3. ភេទ:</span>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="gender" value="male" class="uhs-checkbox">
                        ប្រុស
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="gender" value="female" class="uhs-checkbox">
                        ស្រី
                    </label>
                </td>
            </tr>

            {{-- 4-5 --}}
            <tr>
                <td colspan="9">
                    <div class="uhs-cell-flex">
                        <span>4. ឈ្មោះជាអក្សរឡាតាំង:</span>
                        <span class="uhs-small">(អក្សរធំ BLOCK LETTER)</span>
                        <span>Family Name:</span>
                        <input type="text" name="family_name_en" class="uhs-line-input">
                        <span>First Name:</span>
                        <input type="text" name="first_name_en" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>5. អាយុ:</span>
                        <input type="text" name="age" class="uhs-line-input">
                        <span>ឆ្នាំ</span>
                    </div>
                </td>
            </tr>

            {{-- 6-7 --}}
            <tr>
                <td colspan="2">
                    6. ថ្ងៃ-ខែ-ឆ្នាំកំណើត
                </td>
                <td colspan="4">
                    <div class="uhs-date-grid">
                        @foreach (['d', 'd', 'm', 'm', 'y', 'y', 'y', 'y'] as $label)
                            <div class="uhs-date-box">
                                <div class="uhs-date-label">{{ $label }}</div>
                                <input type="text" name="birth_date[]" maxlength="1" class="uhs-box-input">
                            </div>
                        @endforeach
                    </div>
                </td>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>7. ទីកន្លែងកំណើត</span>
                        <input type="text" name="birth_place" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 8-11 --}}
            <tr>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>8. សញ្ជាតិ</span>
                        <input type="text" name="nationality" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>9. ជនជាតិ</span>
                        <input type="text" name="ethnicity" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>10. សាសនា</span>
                        <input type="text" name="religion" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="5">
                    <span>11. ស្ថានភាពគ្រួសារ:</span>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="marital_status[]" value="single" class="uhs-checkbox">
                        នៅលីវ
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="marital_status[]" value="married" class="uhs-checkbox">
                        រៀបការ
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="marital_status[]" value="other" class="uhs-checkbox">
                        លែងលះ
                    </label>
                </td>
            </tr>

            {{-- 12 --}}
            <tr>
                <td colspan="11">
                    <div class="uhs-cell-flex">
                        <span>12. អាសយដ្ឋានបច្ចុប្បន្ន ឬ យស្ថានទីលំនៅ:</span>
                        <input type="text" name="current_address" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- Section --}}
            <tr>
                <td colspan="11" class="uhs-section-title">
                    សូមបំពេញព័ត៌មានសិក្សា និងព័ត៌មានបន្ថែមខាងក្រោម
                </td>
            </tr>

            {{-- 13-15 --}}
            <tr>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>13. ផ្នែក/ជំនាញចូលរៀន</span>
                        <input type="text" name="major" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>14. សម្រាប់ឆ្នាំសិក្សា</span>
                        <input type="text" name="academic_year_start" class="uhs-line-input" style="max-width: 45px;">
                        <span>-</span>
                        <input type="text" name="academic_year_end" class="uhs-line-input" style="max-width: 45px;">
                    </div>
                </td>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>15. វេនសិក្សា</span>
                        <input type="text" name="study_shift" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 16-18 --}}
            <tr>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>16. ថ្នាក់</span>
                        <input type="text" name="class_level" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>17. ក្រុម</span>
                        <input type="text" name="group" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="4">
                    <span>18. ប្រភេទ:</span>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="study_type[]" value="scholarship" class="uhs-checkbox">
                        អាហារូបករណ៍
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="study_type[]" value="private" class="uhs-checkbox">
                        បង់ថ្លៃ
                    </label>
                </td>
            </tr>

            {{-- 19 --}}
            <tr>
                <td colspan="11">
                    <span>19. ជំនាញ/កម្រិតសិក្សា:</span>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="faculty[]" value="medicine" class="uhs-checkbox">
                        វេជ្ជសាស្ត្រ
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="faculty[]" value="dentistry" class="uhs-checkbox">
                        ទន្តសាស្ត្រ
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="faculty[]" value="pharmacy" class="uhs-checkbox">
                        ឱសថសាស្ត្រ
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="faculty[]" value="nursing" class="uhs-checkbox">
                        គិលានុបដ្ឋាក
                    </label>
                    <label class="uhs-check-label">
                        <input type="checkbox" name="faculty[]" value="other" class="uhs-checkbox">
                        ផ្សេងៗ
                    </label>
                </td>
            </tr>

            {{-- 20-23 --}}
            <tr>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>20. ឆ្នាំប្រឡងជាប់បាក់ឌុប</span>
                        <input type="text" name="bac_year" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>21. លេខនិទ្ទេស</span>
                        <input type="text" name="bac_grade" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="2">
                    <div class="uhs-cell-flex">
                        <span>22. ខេត្ត/រាជធានី</span>
                        <input type="text" name="bac_province" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>23. វិទ្យាល័យ</span>
                        <input type="text" name="high_school" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 24 --}}
            <tr>
                <td colspan="11">
                    <div class="uhs-cell-flex">
                        <span>24. ឈ្មោះឪពុក:</span>
                        <input type="text" name="father_name" class="uhs-line-input">
                        <span>មុខរបរ:</span>
                        <input type="text" name="father_job" class="uhs-line-input">
                        <span>អាសយដ្ឋាន:</span>
                        <input type="text" name="father_address" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 25 --}}
            <tr>
                <td colspan="11">
                    <div class="uhs-cell-flex">
                        <span>25. ឈ្មោះម្តាយ:</span>
                        <input type="text" name="mother_name" class="uhs-line-input">
                        <span>មុខរបរ:</span>
                        <input type="text" name="mother_job" class="uhs-line-input">
                        <span>អាសយដ្ឋាន:</span>
                        <input type="text" name="mother_address" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 26-27 --}}
            <tr>
                <td colspan="6">
                    <div class="uhs-cell-flex">
                        <span>26. លេខទូរស័ព្ទឪពុក/ម្តាយ:</span>
                        <input type="text" name="parent_phone" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>27. អ៊ីមែល:</span>
                        <input type="email" name="email" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 28-30 --}}
            <tr>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>28. ឈ្មោះអាណាព្យាបាល:</span>
                        <input type="text" name="guardian_name" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>29. ត្រូវជា:</span>
                        <input type="text" name="guardian_relationship" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>30. ទូរស័ព្ទ:</span>
                        <input type="text" name="guardian_phone" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 31-33 --}}
            <tr>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>31. អាសយដ្ឋានអាណាព្យាបាល:</span>
                        <input type="text" name="guardian_address" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>32. ទីកន្លែងធ្វើការ:</span>
                        <input type="text" name="guardian_workplace" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="3">
                    <div class="uhs-cell-flex">
                        <span>33. មុខរបរ:</span>
                        <input type="text" name="guardian_job" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 34-35 --}}
            <tr>
                <td colspan="6">
                    <div class="uhs-cell-flex">
                        <span>34. លេខអត្តសញ្ញាណប័ណ្ណ:</span>
                        <input type="text" name="national_id" class="uhs-line-input">
                    </div>
                </td>
                <td colspan="5">
                    <div class="uhs-cell-flex">
                        <span>35. ចេញនៅ:</span>
                        <input type="text" name="national_id_place" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 36 --}}
            <tr>
                <td colspan="11">
                    <div class="uhs-cell-flex">
                        <span>36. លេខទូរស័ព្ទទំនាក់ទំនងបន្ទាន់ ឬ ព័ត៌មានបន្ថែម:</span>
                        <input type="text" name="emergency_contact" class="uhs-line-input">
                    </div>
                </td>
            </tr>

            {{-- 37 --}}
            <tr>
                <td colspan="11">
                    <span>37. ប្រភេទឯកសារភ្ជាប់មកជាមួយ:</span>

                    <label class="uhs-check-label">
                        37a
                        <input type="checkbox" name="attachments[]" value="id_card" class="uhs-checkbox">
                        អ.ស.ប.ក
                    </label>

                    <label class="uhs-check-label">
                        37b
                        <input type="checkbox" name="attachments[]" value="certificate" class="uhs-checkbox">
                        សញ្ញាបត្រ
                    </label>

                    <label class="uhs-check-label">
                        37c
                        <input type="checkbox" name="attachments[]" value="photo" class="uhs-checkbox">
                        រូបថត
                    </label>

                    <label class="uhs-check-label">
                        37d
                        <input type="checkbox" name="attachments[]" value="other" class="uhs-checkbox">
                        ផ្សេងៗ
                    </label>
                </td>
            </tr>
        </table>

        {{-- Declaration text --}}
        <div class="uhs-bottom-note">
            ខ្ញុំបាទ/នាងខ្ញុំសូមធានាអះអាងថា ព័ត៌មានដែលបានបំពេញខាងលើពិតជាត្រឹមត្រូវ
            និងសូមទទួលខុសត្រូវចំពោះព័ត៌មានទាំងអស់។ ប្រសិនបើមានព័ត៌មានណាមួយមិនពិត
            ខ្ញុំបាទ/នាងខ្ញុំសូមទទួលខុសត្រូវតាមច្បាប់ និងបទបញ្ជារបស់សាកលវិទ្យាល័យ។
        </div>

        {{-- Signature area --}}
        <div class="uhs-signatures">
            <div>
                ថ្ងៃទី
                <input type="text" name="student_sign_day" class="uhs-sign-date">
                ខែ
                <input type="text" name="student_sign_month" class="uhs-sign-date">
                ឆ្នាំ២០
                <input type="text" name="student_sign_year" class="uhs-sign-date">
                <br>
                ហត្ថលេខា និង ឈ្មោះសាមីខ្លួន
            </div>

            <div>
                បានពិនិត្យ និង ឯកភាព<br>
                ការិយាល័យសិក្សា<br>
                ថ្ងៃទី
                <input type="text" name="office_sign_day" class="uhs-sign-date">
                ខែ
                <input type="text" name="office_sign_month" class="uhs-sign-date">
                ឆ្នាំ២០
                <input type="text" name="office_sign_year" class="uhs-sign-date">
                <br>
                ហត្ថលេខា និង ត្រា
            </div>

            <div>
                រាជធានីភ្នំពេញ<br>
                ថ្ងៃទី
                <input type="text" name="admin_sign_day" class="uhs-sign-date">
                ខែ
                <input type="text" name="admin_sign_month" class="uhs-sign-date">
                ឆ្នាំ២០
                <input type="text" name="admin_sign_year" class="uhs-sign-date">
                <br>
                ហត្ថលេខា និង ឈ្មោះមន្ត្រីទទួល
            </div>
        </div>

        {{-- Footer --}}
        <div class="uhs-footer">
            សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល | University of Health Sciences |
            Email: info@uhs.edu.kh | Website: www.uhs.edu.kh | fb.com/uhs.edu.kh
        </div>
    </div>
    <div class="uhs-resume-page">
        {{-- Header --}}
        <div class="uhs-resume-header">
            <div class="uhs-logo-card">
                <img src="{{ $logoUrl }}" alt="UHS Logo">
                <div>
                    <div class="uhs-logo-kh">ស.វ.ស</div>
                    <div class="uhs-logo-en">
                        សាកលវិទ្យាល័យ<br>
                        វិទ្យាសាស្ត្រសុខាភិបាល
                    </div>
                </div>
            </div>

            <div style="text-align: center;">
                <div class="uhs-resume-title">
                    ព្រះរាជាណាចក្រកម្ពុជា<br>
                    ជាតិ សាសនា ព្រះមហាក្សត្រ
                </div>

                <div class="uhs-resume-subtitle">
                    ប្រវត្តិរូបសង្ខេប
                </div>

                <div class="uhs-resume-note">
                    (ត្រូវសរសេរដោយខ្លួនឯងផ្ទាល់ ហាមគូសលុប)
                </div>
            </div>

            <div class="uhs-photo-box">
                បិទរូបថតថ្មី<br>
                ៤ x ៦
            </div>
        </div>

        {{-- I --}}
        <div class="uhs-resume-section-title">I- ព័ត៌មានផ្ទាល់ខ្លួន</div>

        <div class="uhs-resume-row">
            <span>- គោត្តនាម និង នាម (ជាអក្សរខ្មែរ)៖</span>
            <input type="text" name="resume_name_kh" class="uhs-line-input">
            <span>អក្សរឡាតាំង</span>
            <input type="text" name="resume_name_en" class="uhs-line-input">
        </div>

        <div class="uhs-resume-row">
            <span>- ភេទ</span>
            <input type="text" name="resume_gender" class="uhs-line-input sm">
            <span>សញ្ជាតិ</span>
            <input type="text" name="resume_nationality" class="uhs-line-input sm">
            <span>ជនជាតិ</span>
            <input type="text" name="resume_ethnicity" class="uhs-line-input sm">
            <span>សាសនា</span>
            <input type="text" name="resume_religion" class="uhs-line-input sm">
            <label class="uhs-check-label">
                រៀបការ <input type="checkbox" name="resume_marital_status" value="married" class="uhs-checkbox">
            </label>
            <label class="uhs-check-label">
                នៅលីវ <input type="checkbox" name="resume_marital_status" value="single" class="uhs-checkbox">
            </label>
        </div>

        <div class="uhs-resume-row">
            <span>- ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span>
            <input type="text" name="resume_birth_date" class="uhs-line-input md">
            <span>ទីកន្លែងកំណើត៖ ភូមិ</span>
            <input type="text" name="resume_birth_village" class="uhs-line-input md">
            <span>ឃុំ/សង្កាត់</span>
            <input type="text" name="resume_birth_commune" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>ស្រុក/ខណ្ឌ</span>
            <input type="text" name="resume_birth_district" class="uhs-line-input lg">
            <span>រាជធានី/ខេត្ត</span>
            <input type="text" name="resume_birth_province" class="uhs-line-input lg">
        </div>

        <div class="uhs-resume-row">
            <span>- អាសយដ្ឋានបច្ចុប្បន្ន៖ ផ្ទះលេខ</span>
            <input type="text" name="resume_house_no" class="uhs-line-input sm">
            <span>ផ្លូវលេខ</span>
            <input type="text" name="resume_street_no" class="uhs-line-input sm">
            <span>ឃុំ/សង្កាត់</span>
            <input type="text" name="resume_commune" class="uhs-line-input md">
            <span>ស្រុក/ខណ្ឌ</span>
            <input type="text" name="resume_district" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>រាជធានី/ខេត្ត</span>
            <input type="text" name="resume_province" class="uhs-line-input lg">
            <span>លេខទូរស័ព្ទ</span>
            <input type="text" name="resume_phone" class="uhs-line-input lg">
        </div>

        <div class="uhs-resume-row">
            <span>- កម្រិតវប្បធម៌ជាតិ</span>
            <input type="text" name="resume_general_education" class="uhs-line-input">
            <span>សម័យប្រឡង</span>
            <input type="text" name="resume_general_exam_date" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>- កម្រិតសញ្ញាបត្រជំនាញ</span>
            <input type="text" name="resume_degree" class="uhs-line-input">
            <span>សម័យប្រឡង</span>
            <input type="text" name="resume_degree_exam_date" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>មកពីសាកលវិទ្យាល័យ</span>
            <input type="text" name="resume_university" class="uhs-line-input">
        </div>

        <div class="uhs-resume-row">
            <span>- មុខរបរបច្ចុប្បន្ន</span>
            <input type="text" name="resume_current_job" class="uhs-line-input">
            <span>ទីកន្លែងធ្វើការ/អង្គភាព</span>
            <input type="text" name="resume_workplace" class="uhs-line-input">
        </div>

        {{-- II --}}
        <div class="uhs-resume-section-title">II- ព័ត៌មានគ្រួសារ</div>

        <div class="uhs-resume-subsection">ក- អំពីឪពុក-ម្តាយបង្កើត</div>

        <div class="uhs-resume-row">
            <span>- ឪពុកឈ្មោះ</span>
            <input type="text" name="resume_father_name" class="uhs-line-input">
            <span>ឆ្នាំកំណើត</span>
            <input type="text" name="resume_father_birth_year" class="uhs-line-input sm">
            <span>ជនជាតិ</span>
            <input type="text" name="resume_father_ethnicity" class="uhs-line-input sm">
            <span>សញ្ជាតិ</span>
            <input type="text" name="resume_father_nationality" class="uhs-line-input sm">
            <label class="uhs-check-label">
                នៅរស់ <input type="checkbox" name="resume_father_status" value="alive" class="uhs-checkbox">
            </label>
            <label class="uhs-check-label">
                ស្លាប់ <input type="checkbox" name="resume_father_status" value="dead" class="uhs-checkbox">
            </label>
        </div>

        <div class="uhs-resume-row">
            <span>មុខរបរ</span>
            <input type="text" name="resume_father_job" class="uhs-line-input">
            <span>ទីកន្លែងធ្វើការ</span>
            <input type="text" name="resume_father_workplace" class="uhs-line-input">
            <span>លេខទូរស័ព្ទ</span>
            <input type="text" name="resume_father_phone" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>- ម្តាយឈ្មោះ</span>
            <input type="text" name="resume_mother_name" class="uhs-line-input">
            <span>ឆ្នាំកំណើត</span>
            <input type="text" name="resume_mother_birth_year" class="uhs-line-input sm">
            <span>ជនជាតិ</span>
            <input type="text" name="resume_mother_ethnicity" class="uhs-line-input sm">
            <span>សញ្ជាតិ</span>
            <input type="text" name="resume_mother_nationality" class="uhs-line-input sm">
            <label class="uhs-check-label">
                នៅរស់ <input type="checkbox" name="resume_mother_status" value="alive" class="uhs-checkbox">
            </label>
            <label class="uhs-check-label">
                ស្លាប់ <input type="checkbox" name="resume_mother_status" value="dead" class="uhs-checkbox">
            </label>
        </div>

        <div class="uhs-resume-row">
            <span>មុខរបរ</span>
            <input type="text" name="resume_mother_job" class="uhs-line-input">
            <span>ទីកន្លែងធ្វើការ</span>
            <input type="text" name="resume_mother_workplace" class="uhs-line-input">
            <span>លេខទូរស័ព្ទ</span>
            <input type="text" name="resume_mother_phone" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>- អាសយដ្ឋានបច្ចុប្បន្ន៖ ផ្ទះលេខ</span>
            <input type="text" name="resume_parent_house_no" class="uhs-line-input sm">
            <span>ផ្លូវលេខ</span>
            <input type="text" name="resume_parent_street_no" class="uhs-line-input sm">
            <span>ឃុំ/សង្កាត់</span>
            <input type="text" name="resume_parent_commune" class="uhs-line-input md">
            <span>ស្រុក/ខណ្ឌ</span>
            <input type="text" name="resume_parent_district" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-row">
            <span>- អាណាព្យាបាលឈ្មោះ</span>
            <input type="text" name="resume_guardian_name" class="uhs-line-input">
            <span>ត្រូវជា</span>
            <input type="text" name="resume_guardian_relation" class="uhs-line-input md">
            <span>លេខទូរស័ព្ទ</span>
            <input type="text" name="resume_guardian_phone" class="uhs-line-input md">
        </div>

        <div class="uhs-resume-subsection">ខ- អំពីបងប្អូនបង្កើត</div>

        @for ($i = 1; $i <= 3; $i++)
            <div class="uhs-resume-row">
                <span>{{ $i }}- ឈ្មោះ</span>
                <input type="text" name="resume_sibling_{{ $i }}_name" class="uhs-line-input">
                <span>ភេទ</span>
                <input type="text" name="resume_sibling_{{ $i }}_gender" class="uhs-line-input sm">
                <span>ឆ្នាំកំណើត</span>
                <input type="text" name="resume_sibling_{{ $i }}_birth_year" class="uhs-line-input md">
                <span>មុខរបរ</span>
                <input type="text" name="resume_sibling_{{ $i }}_job" class="uhs-line-input">
            </div>
        @endfor

        <div class="uhs-resume-subsection">គ- អំពីប្តី ឬ ប្រពន្ធ និង កូន</div>

        <div class="uhs-resume-row">
            <span>- ប្តី ឬ ប្រពន្ធឈ្មោះ</span>
            <input type="text" name="resume_spouse_name" class="uhs-line-input">
            <span>ថ្ងៃខែឆ្នាំកំណើត</span>
            <input type="text" name="resume_spouse_birth_date" class="uhs-line-input md">
            <span>មុខរបរ</span>
            <input type="text" name="resume_spouse_job" class="uhs-line-input">
        </div>

        <div class="uhs-resume-row">
            <span>- មានកូនចំនួន</span>
            <input type="text" name="resume_children_total" class="uhs-line-input sm">
            <span>នាក់៖ ប្រុស</span>
            <input type="text" name="resume_children_male" class="uhs-line-input sm">
            <span>នាក់ ស្រី</span>
            <input type="text" name="resume_children_female" class="uhs-line-input sm">
            <span>នាក់</span>
        </div>

        {{-- III --}}
        <div class="uhs-resume-section-title">III- ព័ត៌មានសិក្សា</div>

        <table class="uhs-resume-table">
            <thead>
            <tr>
                <th style="width: 28%;">គ្រឹះស្ថានបណ្តុះបណ្តាល</th>
                <th style="width: 26%;">កម្រិតសញ្ញាបត្រ និង ជំនាញ</th>
                <th style="width: 18%;">ពីឆ្នាំណា ដល់ឆ្នាំណា</th>
                <th style="width: 14%;">ប្រទេស</th>
                <th style="width: 14%;">ឆ្នាំបញ្ចប់ការសិក្សា ឬ ទទួលបានសញ្ញាបត្រ</th>
            </tr>
            </thead>
            <tbody>
            @for ($i = 1; $i <= 4; $i++)
                <tr>
                    <td><input type="text" name="resume_education_{{ $i }}_school"></td>
                    <td><input type="text" name="resume_education_{{ $i }}_degree"></td>
                    <td><input type="text" name="resume_education_{{ $i }}_year"></td>
                    <td><input type="text" name="resume_education_{{ $i }}_country"></td>
                    <td><input type="text" name="resume_education_{{ $i }}_graduate_year"></td>
                </tr>
            @endfor
            </tbody>
        </table>

        <div class="uhs-resume-declare">
            ខ្ញុំបាទ/នាងខ្ញុំ សូមធានាអះអាងថា រាល់ចម្លើយ និងព័ត៌មានទាំងអស់ខាងលើនេះ
            ពិតជាត្រឹមត្រូវឥតក្លែងបន្លំឡើយ។ ប្រសិនបើមានចំណុចណាមួយប្រាសចាកពីការពិត
            ខ្ញុំបាទ/នាងខ្ញុំ សូមទទួលខុសត្រូវទាំងស្រុងចំពោះមុខច្បាប់ជាធរមាន។
        </div>

        <div class="uhs-resume-sign">
            <div class="uhs-sign-box">
                រាជធានីភ្នំពេញ ថ្ងៃទី
                <input type="text" name="resume_sign_day" class="uhs-sign-date">
                ខែ
                <input type="text" name="resume_sign_month" class="uhs-sign-date">
                ឆ្នាំ២០
                <input type="text" name="resume_sign_year" class="uhs-sign-date">
                <br>
                ស្នាមមេដៃស្តាំ និង ឈ្មោះសាមីខ្លួន
            </div>
        </div>
    </div>
</div>
