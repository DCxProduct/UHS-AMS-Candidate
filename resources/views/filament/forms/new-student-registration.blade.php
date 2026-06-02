@php
    $logoPath = public_path('images/UHS_logo.png');

    $logoUrl = file_exists($logoPath)
        ? asset('images/UHS_logo.png')
        : '';
@endphp

<style>
    :root {
        --uhs-shell-bg: #eef2f7;
        --uhs-shell-border: #d6dce5;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #111111;
        --uhs-muted: #4b5563;
        --uhs-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
    }

    html.dark {
        --uhs-shell-bg: #000000;
        --uhs-shell-border: #374151;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #111111;
        --uhs-muted: #4b5563;
        --uhs-shadow: 0 18px 45px rgba(0, 0, 0, 0.55);
    }

    .uhsrs-wrapper {
        background: var(--uhs-shell-bg);
        border: 1px solid var(--uhs-shell-border);
        border-radius: 18px;
        overflow-x: auto;
        padding: 24px 0;
    }

    .uhsrs-page {
        width: 900px;
        min-height: 1240px;
        margin: 0 auto 24px;
        background: var(--uhs-page-bg);
        color: var(--uhs-page-text);
        box-shadow: var(--uhs-shadow);
        padding: 30px 42px 34px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        font-size: 12px;
        line-height: 1.25;
    }

    .uhsrs-page * {
        box-sizing: border-box;
    }

    .uhsrs-page input[type="text"],
    .uhsrs-page input[type="email"],
    .uhsrs-page input[type="number"] {
        color: #111111 !important;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
    }

    .uhsrs-page input[type="checkbox"],
    .uhsrs-page input[type="radio"] {
        appearance: none;
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        border: 1.5px solid var(--uhs-line);
        background: #ffffff;
        border-radius: 0;
        margin: 0 4px;
        position: relative;
        vertical-align: middle;
        cursor: pointer;
    }

    .uhsrs-page input[type="checkbox"]:checked::after,
    .uhsrs-page input[type="radio"]:checked::after {
        content: "✓";
        position: absolute;
        left: 1px;
        top: -6px;
        font-size: 18px;
        font-weight: 900;
        color: #000000;
    }

    .uhsrs-text-input {
        border: 0;
        border-bottom: 1px dotted var(--uhs-line);
        background: transparent;
        outline: none;
        height: 18px;
        padding: 0 2px;
        font-size: 12px;
    }

    .uhsrs-text-input.full {
        width: 100%;
    }

    .uhsrs-text-input.sm {
        width: 60px;
    }

    .uhsrs-text-input.md {
        width: 130px;
    }

    .uhsrs-text-input.lg {
        width: 220px;
    }

    .uhsrs-top-line {
        border-top: 2px solid var(--uhs-line);
        padding-top: 10px;
        position: relative;
    }

    .uhsrs-reg {
        position: absolute;
        right: 0;
        top: -22px;
        font-size: 11px;
        font-style: italic;
    }

    .uhsrs-header {
        display: grid;
        grid-template-columns: 250px 1fr 125px;
        align-items: start;
        gap: 12px;
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
        font-size: 42px;
        font-weight: 900;
        letter-spacing: 1px;
        line-height: 1;
        color: #1d9bd7;
        font-family: Arial, sans-serif;
    }

    .uhs-logo-subtitle {
        font-size: 11px;
        margin-top: 4px;
        line-height: 1.35;
        font-family: Arial, sans-serif;
    }

    .uhsrs-title-block {
        text-align: center;
        padding-top: 34px;
    }

    .uhsrs-title-kh {
        font-size: 21px;
        font-weight: 900;
        line-height: 1.3;
    }

    .uhsrs-title-year {
        font-size: 15px;
        font-weight: 700;
        margin-top: 6px;
    }

    .uhsrs-photo-box {
        width: 120px;
        height: 125px;
        border: 1.5px solid var(--uhs-line);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 17px;
        font-weight: 700;
        line-height: 1.7;
        margin-left: auto;
    }

    .uhsrs-id-row {
        margin-top: 14px;
        display: grid;
        grid-template-columns: 210px 1fr;
        align-items: center;
    }

    .uhsrs-id-label {
        border: 1.3px solid var(--uhs-line);
        border-right: 0;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        text-align: right;
        line-height: 1.2;
    }

    .uhsrs-box-input {
        width: 100%;
        height: 36px;
        border: 1.3px solid var(--uhs-line);
        background-image: linear-gradient(to right, transparent 0, transparent 31px, var(--uhs-line) 32px);
        background-size: 32px 100%;
        background-color: #ffffff;
        outline: none;
        letter-spacing: 18px;
        padding-left: 8px;
        font-size: 18px;
        font-family: "Courier New", monospace !important;
        text-transform: uppercase;
    }

    .uhsrs-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .uhsrs-table td,
    .uhsrs-table th {
        border: 1.3px solid var(--uhs-line);
        padding: 4px 6px;
        vertical-align: middle;
        height: 31px;
    }

    .uhsrs-en {
        font-family: Arial, sans-serif;
        font-size: 10px;
        color: var(--uhs-muted);
    }

    .uhsrs-date-boxes {
        width: 100%;
        height: 28px;
        border: 0;
        background-image: linear-gradient(to right, transparent 0, transparent 27px, var(--uhs-line) 28px);
        background-size: 28px 100%;
        outline: none;
        letter-spacing: 13px;
        padding-left: 6px;
        font-size: 14px;
        font-family: "Courier New", monospace !important;
    }

    .uhsrs-section-title {
        text-align: center;
        font-size: 14px;
        font-weight: 900;
        padding: 8px 0 5px;
        line-height: 1.5;
    }

    .uhsrs-small {
        font-size: 10.5px;
        line-height: 1.45;
    }

    .uhsrs-sign-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        margin-top: 26px;
        text-align: center;
        font-size: 12px;
        line-height: 1.8;
    }

    .uhsrs-sign-space {
        height: 58px;
    }

    .uhsrs-footer-note {
        border-top: 1.6px solid var(--uhs-line);
        margin-top: 28px;
        padding-top: 8px;
        font-size: 9.5px;
        line-height: 1.5;
    }

    .uhsrs-page2-header {
        display: grid;
        grid-template-columns: 220px 1fr 125px;
        gap: 16px;
        align-items: start;
    }

    .uhsrs-page2-logo-box {
        border: 1.3px solid var(--uhs-line);
        height: 78px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px;
    }

    .uhsrs-page2-title {
        text-align: center;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.4;
    }

    .uhsrs-page2-subtitle {
        text-align: center;
        font-size: 18px;
        font-weight: 800;
        margin-top: 8px;
        border: 1.3px solid var(--uhs-line);
        display: inline-block;
        padding: 6px 28px;
    }

    .uhsrs-page2-section {
        margin-top: 12px;
        font-size: 13px;
        line-height: 1.7;
    }

    .uhsrs-page2-section-title {
        font-weight: 900;
        font-size: 15px;
        margin: 8px 0 4px;
    }

    .uhsrs-line-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: flex-end;
        margin: 3px 0;
    }

    .uhsrs-line-row .uhsrs-text-input {
        flex: 1;
        min-width: 100px;
    }

    .uhsrs-family-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 10px;
    }

    .uhsrs-family-table th,
    .uhsrs-family-table td {
        border: 1.3px solid var(--uhs-line);
        height: 32px;
        padding: 4px 6px;
        text-align: center;
        vertical-align: middle;
        font-size: 12px;
    }

    .uhsrs-family-table input {
        width: 100%;
        height: 100%;
        border: 0;
        outline: none;
        background: transparent;
        text-align: center;
    }

    .uhsrs-page2-bottom {
        margin-top: 18px;
        text-align: center;
        font-size: 13px;
        line-height: 1.8;
    }

    .uhsrs-page2-date {
        margin-top: 10px;
        text-align: right;
        font-size: 13px;
        line-height: 1.8;
    }

    @media print {
        .uhsrs-wrapper {
            background: #ffffff;
            border: none;
            border-radius: 0;
            padding: 0;
        }

        .uhsrs-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }
</style>

<div class="uhsrs-wrapper">
    {{-- PAGE 1 --}}
    <div class="uhsrs-page">
        <div class="uhsrs-top-line">
            <div class="uhsrs-reg">Reg-S/ 01</div>

            <div class="uhsrs-header">
                {{-- Logo left --}}
                <div class="uhs-logo-area">
                    <img src="{{ $logoUrl }}" class="uhs-logo" alt="UHS Logo">
                    <div>
                        <div class="uhs-logo-title">UHS</div>
                        <div class="uhs-logo-subtitle">University of Health Sciences</div>
                    </div>
                </div>

                <div class="uhsrs-title-block">
                    <div class="uhsrs-title-kh">
                        ពាក្យសុំចុះឈ្មោះចូលរៀន និស្សិតថ្មី
                    </div>

                    <div class="uhsrs-title-year">
                        (ថ្នាក់ឆ្នាំ ២០..... - ២០.....)
                    </div>
                </div>

                <div class="uhsrs-photo-box">
                    រូបថត<br>៤ x ៦
                </div>
            </div>

            <div class="uhsrs-id-row">
                <div class="uhsrs-id-label">
                    1. អត្តលេខ<br>
                    <span class="uhsrs-en">Student ID</span>
                </div>

                <input class="uhsrs-box-input" type="text" maxlength="18">
            </div>

            <table class="uhsrs-table">
                <tr>
                    <td colspan="4">
                        2. ឈ្មោះ : ជាភាសាខ្មែរ នាមត្រកូល
                        <input class="uhsrs-text-input md" type="text">
                        នាមខ្លួន
                        <input class="uhsrs-text-input md" type="text">
                    </td>
                    <td colspan="2">
                        3. ភេទ :
                        <label><input type="checkbox"> ប្រុស</label>
                        <label><input type="checkbox"> ស្រី</label>
                    </td>
                </tr>

                <tr>
                    <td colspan="4">
                        4. ឈ្មោះ : ជាអក្សរឡាតាំង
                        <span class="uhsrs-en">(NAME IN LATIN BLOCK LETTER)</span><br>
                        Family Name:
                        <input class="uhsrs-text-input md" type="text">
                        First Name:
                        <input class="uhsrs-text-input md" type="text">
                    </td>
                    <td colspan="2">
                        5. ជនជាតិ:
                        <input class="uhsrs-text-input md" type="text">
                    </td>
                </tr>

                <tr>
                    <td>6. ថ្ងៃ-ខែ-ឆ្នាំកំណើត</td>
                    <td colspan="2">
                        <input class="uhsrs-date-boxes" type="text" placeholder="ddmmyyyy" maxlength="8">
                    </td>
                    <td colspan="3">
                        7. ទីកន្លែងកំណើត:
                        <input class="uhsrs-text-input full" type="text">
                    </td>
                </tr>

                <tr>
                    <td>8. សញ្ជាតិ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>9. សាសនា <input class="uhsrs-text-input sm" type="text"></td>
                    <td>10. លេខទូរស័ព្ទ <input class="uhsrs-text-input sm" type="text"></td>
                    <td colspan="3">
                        11. ស្ថានភាពគ្រួសារ:
                        <label><input type="checkbox"> នៅលីវ</label>
                        <label><input type="checkbox"> រៀបការ</label>
                        <label><input type="checkbox"> ផ្សេងៗ</label>
                    </td>
                </tr>

                <tr>
                    <td colspan="6">
                        12. អាសយដ្ឋានបច្ចុប្បន្ន:
                        <input class="uhsrs-text-input full" type="text">
                    </td>
                </tr>

                <tr>
                    <td colspan="6" class="uhsrs-section-title">
                        ស្ថានភាពការសិក្សា និង ព័ត៌មានចូលរៀន
                    </td>
                </tr>

                <tr>
                    <td colspan="2">13. សញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>14. ឆ្នាំប្រឡងជាប់ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>២០ - ២០</td>
                    <td colspan="2">15. និទ្ទេស <input class="uhsrs-text-input md" type="text"></td>
                </tr>

                <tr>
                    <td>16. លេខតុ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>17. បន្ទប់ <input class="uhsrs-text-input sm" type="text"></td>
                    <td colspan="2">18. ប្រភេទនិស្សិត</td>
                    <td colspan="2">
                        <label><input type="checkbox"> បង់ថ្លៃ</label>
                        <label><input type="checkbox"> អាហារូបករណ៍</label>
                    </td>
                </tr>

                <tr>
                    <td colspan="6">
                        19. មហាវិទ្យាល័យ / សាលា / ដេប៉ាតឺម៉ង់ :
                        <label><input type="checkbox"> វេជ្ជសាស្ត្រ</label>
                        <label><input type="checkbox"> ឱសថសាស្ត្រ</label>
                        <label><input type="checkbox"> ទន្តវទន្តសាស្ត្រ</label>
                        <label><input type="checkbox"> សុខភាពសាធារណៈ</label>
                        <label><input type="checkbox"> ស.ប.ន.ថ</label>
                        <label><input type="checkbox"> ថ្នាក់ឆ្នាំសិក្សាមូលដ្ឋាន</label>
                    </td>
                </tr>

                <tr>
                    <td colspan="2">20. ជំនាញ <input class="uhsrs-text-input md" type="text"></td>
                    <td colspan="2">21. ថ្នាក់ឆ្នាំទី <input class="uhsrs-text-input md" type="text"></td>
                    <td>22. ក្រុម <input class="uhsrs-text-input sm" type="text"></td>
                    <td>23. វេនសិក្សា <input class="uhsrs-text-input sm" type="text"></td>
                </tr>

                <tr>
                    <td colspan="6" class="uhsrs-section-title">
                        ព័ត៌មានគ្រួសារ និង អាណាព្យាបាល
                    </td>
                </tr>

                <tr>
                    <td colspan="2">24. ឈ្មោះឪពុក <input class="uhsrs-text-input md" type="text"></td>
                    <td>អាយុ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>មុខរបរ <input class="uhsrs-text-input sm" type="text"></td>
                    <td colspan="2">លេខទូរស័ព្ទ <input class="uhsrs-text-input md" type="text"></td>
                </tr>

                <tr>
                    <td colspan="2">25. ឈ្មោះម្តាយ <input class="uhsrs-text-input md" type="text"></td>
                    <td>អាយុ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>មុខរបរ <input class="uhsrs-text-input sm" type="text"></td>
                    <td colspan="2">លេខទូរស័ព្ទ <input class="uhsrs-text-input md" type="text"></td>
                </tr>

                <tr>
                    <td colspan="3">26. អាសយដ្ឋានអាណាព្យាបាល <input class="uhsrs-text-input full" type="text"></td>
                    <td colspan="3">27. អ៊ីមែល <input class="uhsrs-text-input full" type="email"></td>
                </tr>

                <tr>
                    <td colspan="2">28. កម្ពស់ <input class="uhsrs-text-input sm" type="text"></td>
                    <td colspan="2">29. ទម្ងន់ <input class="uhsrs-text-input sm" type="text"></td>
                    <td>30. ក្រុមឈាម <input class="uhsrs-text-input sm" type="text"></td>
                    <td>31. ជំងឺ <input class="uhsrs-text-input sm" type="text"></td>
                </tr>

                <tr>
                    <td colspan="3">32. បញ្ជាក់ផ្សេងៗ <input class="uhsrs-text-input full" type="text"></td>
                    <td colspan="3">33. ឯកសារភ្ជាប់ <input class="uhsrs-text-input full" type="text"></td>
                </tr>

                <tr>
                    <td colspan="6">
                        37. ស្ថានភាពពាក្យ:
                        <label><input type="checkbox"> គ្រប់គ្រាន់</label>
                        <label><input type="checkbox"> ខ្វះឯកសារ</label>
                        <label><input type="checkbox"> ត្រូវកែតម្រូវ</label>
                        <label><input type="checkbox"> ផ្សេងៗ</label>
                    </td>
                </tr>
            </table>

            <div class="uhsrs-small" style="margin-top:8px; text-align:justify;">
                ខ្ញុំបាទ/នាងខ្ញុំ សូមអះអាងថា ព័ត៌មានដែលបានបំពេញខាងលើពិតជាត្រឹមត្រូវ។
                ប្រសិនបើមានការក្លែងបន្លំ ខ្ញុំបាទ/នាងខ្ញុំសូមទទួលខុសត្រូវចំពោះមុខច្បាប់។
            </div>

            <div class="uhsrs-sign-row">
                <div>
                    បានពិនិត្យ និង ឯកភាព<br>
                    ថ្ងៃទី <input class="uhsrs-text-input sm" type="text">
                    ខែ <input class="uhsrs-text-input sm" type="text">
                    ឆ្នាំ២០ <input class="uhsrs-text-input sm" type="text">
                    <div class="uhsrs-sign-space"></div>
                    ហត្ថលេខា និងឈ្មោះមន្រ្តីទទួលពាក្យ
                </div>

                <div>
                    បានពិនិត្យ និង អនុម័ត<br>
                    ថ្ងៃទី <input class="uhsrs-text-input sm" type="text">
                    ខែ <input class="uhsrs-text-input sm" type="text">
                    ឆ្នាំ២០ <input class="uhsrs-text-input sm" type="text">
                    <div class="uhsrs-sign-space"></div>
                    ហត្ថលេខា និងឈ្មោះប្រធានការិយាល័យ
                </div>

                <div>
                    រាជធានីភ្នំពេញ ថ្ងៃទី <input class="uhsrs-text-input sm" type="text">
                    ខែ <input class="uhsrs-text-input sm" type="text">
                    ឆ្នាំ២០ <input class="uhsrs-text-input sm" type="text">
                    <div class="uhsrs-sign-space"></div>
                    ហត្ថលេខា និងឈ្មោះសាមីខ្លួន
                </div>
            </div>

            <div class="uhsrs-footer-note">
                កំណត់ចំណាំ៖ សូមភ្ជាប់ឯកសារដែលត្រូវការ និងបំពេញព័ត៌មានឲ្យបានត្រឹមត្រូវមុនដាក់ពាក្យ។
            </div>
        </div>
    </div>

    {{-- PAGE 2 --}}
    <div class="uhsrs-page">
        <div class="uhsrs-page2-header">
            <div class="uhsrs-page2-logo-box">
                <img src="{{ $logoUrl }}" class="uhs-logo" alt="UHS Logo">
                <div>
                    <div style="font-size:30px;font-weight:900;color:#111;">ស.វ.ស</div>
                    <div style="font-size:10px;">University of Health Sciences</div>
                </div>
            </div>

            <div style="text-align:center;">
                <div class="uhsrs-page2-title">
                    ប្រវត្តិរូបសង្ខេប<br>
                    និស្សិត សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល
                </div>

                <div class="uhsrs-page2-subtitle">
                    ប្រវត្តិរូបសង្ខេប
                </div>

                <div style="font-size:12px;margin-top:5px;">
                    (សម្រាប់ការចុះឈ្មោះនិស្សិតថ្មី)
                </div>
            </div>

            <div class="uhsrs-photo-box" style="height:118px;">
                បិទរូបថត<br>៤ x ៦
            </div>
        </div>

        <div class="uhsrs-page2-section">
            <div class="uhsrs-page2-section-title">I- ព័ត៌មានផ្ទាល់ខ្លួន</div>

            <div class="uhsrs-line-row">
                <span>- គោត្តនាម និង នាម</span>
                <input class="uhsrs-text-input" type="text">
                <span>អក្សរឡាតាំង</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-line-row">
                <span>- ភេទ</span>
                <label><input type="checkbox"> ប្រុស</label>
                <label><input type="checkbox"> ស្រី</label>
                <span>ថ្ងៃខែឆ្នាំកំណើត</span>
                <input class="uhsrs-text-input" type="text">
                <span>សញ្ជាតិ</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-line-row">
                <span>- ទីកន្លែងកំណើត</span>
                <input class="uhsrs-text-input" type="text">
                <span>ឃុំ/សង្កាត់</span>
                <input class="uhsrs-text-input" type="text">
                <span>ស្រុក/ខណ្ឌ</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-line-row">
                <span>- អាសយដ្ឋានបច្ចុប្បន្ន</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-line-row">
                <span>- លេខទូរស័ព្ទ</span>
                <input class="uhsrs-text-input" type="text">
                <span>អ៊ីមែល</span>
                <input class="uhsrs-text-input" type="email">
            </div>

            <div class="uhsrs-page2-section-title">II- ព័ត៌មានឪពុកម្តាយ</div>

            <div class="uhsrs-line-row">
                <span>ក- ឪពុកឈ្មោះ</span>
                <input class="uhsrs-text-input" type="text">
                <span>អាយុ</span>
                <input class="uhsrs-text-input sm" type="text">
                <span>មុខរបរ</span>
                <input class="uhsrs-text-input" type="text">
                <label>រស់នៅ <input type="checkbox"></label>
                <label>ស្លាប់ <input type="checkbox"></label>
            </div>

            <div class="uhsrs-line-row">
                <span>លេខទូរស័ព្ទ</span>
                <input class="uhsrs-text-input" type="text">
                <span>អាសយដ្ឋាន</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-line-row">
                <span>ខ- ម្តាយឈ្មោះ</span>
                <input class="uhsrs-text-input" type="text">
                <span>អាយុ</span>
                <input class="uhsrs-text-input sm" type="text">
                <span>មុខរបរ</span>
                <input class="uhsrs-text-input" type="text">
                <label>រស់នៅ <input type="checkbox"></label>
                <label>ស្លាប់ <input type="checkbox"></label>
            </div>

            <div class="uhsrs-line-row">
                <span>លេខទូរស័ព្ទ</span>
                <input class="uhsrs-text-input" type="text">
                <span>អាសយដ្ឋាន</span>
                <input class="uhsrs-text-input" type="text">
            </div>

            <div class="uhsrs-page2-section-title">III- ព័ត៌មានបងប្អូន</div>

            <table class="uhsrs-family-table">
                <thead>
                <tr>
                    <th style="width:28%;">គោត្តនាម និង នាម</th>
                    <th style="width:27%;">កម្រិតសិក្សា និង ជំនាញ</th>
                    <th style="width:20%;">ទីកន្លែងធ្វើការ</th>
                    <th style="width:15%;">ប្រភេទ</th>
                    <th style="width:10%;">ផ្សេងៗ</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                </tr>
                <tr>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                </tr>
                <tr>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                </tr>
                <tr>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                    <td><input type="text"></td>
                </tr>
                </tbody>
            </table>

            <div class="uhsrs-page2-bottom">
                កិច្ចសន្យាអះអាងព័ត៌មានខាងលើពិតជាត្រឹមត្រូវ<br>
                ខ្ញុំបាទ/នាងខ្ញុំ សូមទទួលខុសត្រូវចំពោះព័ត៌មានទាំងអស់ដែលបានបំពេញ។
            </div>

            <div class="uhsrs-page2-date">
                រាជធានីភ្នំពេញ ថ្ងៃទី
                <input class="uhsrs-text-input sm" type="text">
                ខែ
                <input class="uhsrs-text-input sm" type="text">
                ឆ្នាំ២០
                <input class="uhsrs-text-input sm" type="text"><br>
                ហត្ថលេខា និង ឈ្មោះសាមីខ្លួន
            </div>
        </div>
    </div>
</div>
