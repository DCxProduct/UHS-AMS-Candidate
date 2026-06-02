<style>
    :root {
        --uhs-shell-bg: #eef2f7;
        --uhs-shell-border: #d6dce5;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #111111;
        --uhs-shadow: 0 16px 45px rgba(15, 23, 42, 0.18);
        --uhs-table-label-bg: #eef4d8;
    }

    html.dark {
        --uhs-shell-bg: #000000;
        --uhs-shell-border: #374151;
        --uhs-page-bg: #ffffff;
        --uhs-page-text: #111827;
        --uhs-line: #111111;
        --uhs-shadow: 0 16px 45px rgba(0, 0, 0, 0.55);
        --uhs-table-label-bg: #eef4d8;
    }

    .uhsdc-wrapper {
        background: var(--uhs-shell-bg);
        border: 1px solid var(--uhs-shell-border);
        border-radius: 16px;
        overflow-x: auto;
        padding: 24px 0;
    }

    .uhsdc-page {
        width: 876px;
        min-height: 1230px;
        margin: 0 auto;
        background: var(--uhs-page-bg);
        color: var(--uhs-page-text);
        box-shadow: var(--uhs-shadow);
        padding: 38px 66px 40px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        line-height: 1.45;
    }

    .uhsdc-page * {
        box-sizing: border-box;
    }

    .uhsdc-title {
        text-align: center;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.2;
        margin-bottom: 24px;
    }

    .uhsdc-paragraph {
        font-size: 16px;
        line-height: 1.75;
        text-align: justify;
        margin: 0 0 28px;
    }

    .uhsdc-table {
        width: 598px;
        margin: 30px auto 32px;
        border-collapse: collapse;
        table-layout: fixed;
        color: #111827;
    }

    .uhsdc-table td {
        border: 1.4px solid var(--uhs-line);
        padding: 3px 8px;
        vertical-align: middle;
        font-size: 16px;
        line-height: 1.25;
        height: 35px;
    }

    .uhsdc-table td:first-child {
        width: 308px;
        background: var(--uhs-table-label-bg);
        font-weight: 600;
    }

    .uhsdc-table td:last-child {
        width: 290px;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
    }

    .uhsdc-footer {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 20px;
        margin-top: 42px;
        align-items: start;
    }

    .uhsdc-qr {
        width: 62px;
        height: 62px;
        margin-left: 58px;
        margin-top: 8px;
        background: #ffffff;
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        grid-template-rows: repeat(7, 1fr);
        gap: 2px;
        padding: 2px;
    }

    .uhsdc-qr span {
        background: #000000;
    }

    .uhsdc-qr span.empty {
        background: transparent;
    }

    .uhsdc-date {
        text-align: center;
        font-size: 16px;
        line-height: 1.75;
        padding-top: 0;
    }

    .uhsdc-signature {
        margin-top: 105px;
        text-align: center;
        font-size: 16px;
        font-weight: 600;
    }

    @media print {
        .uhsdc-wrapper {
            background: #ffffff;
            border: none;
            border-radius: 0;
            padding: 0;
        }

        .uhsdc-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }
</style>

<div class="uhsdc-wrapper">
    <div class="uhsdc-page">
        <div class="uhsdc-title">
            លិខិត​យល់​ព្រម
        </div>

        <div class="uhsdc-paragraph">
            ក្រោយពីមានការពន្យល់ពីមន្រ្តីរបស់ ស.វ.ស ខ្ញុំបាទ-នាងខ្ញុំបានយល់ច្បាស់អំពី
            សារៈប្រយោជន៍នៃការផ្តល់ព័ត៌មានផ្ទាល់ខ្លួន របស់ខ្ញុំបាទ-នាងខ្ញុំ សម្រាប់ប្រើប្រាស់
            ក្នុងការងាររដ្ឋបាល និងបច្ចេកទេសរបស់ ស.វ.ស នៅពេលកំពុងសិក្សា និងក្រោយពេលបញ្ចប់
            ការសិក្សា របស់ខ្ញុំបាទ-នាងខ្ញុំ។ ខ្ញុំបាទ-នាងខ្ញុំសូមចូលរួមដោយស្ម័គ្រចិត្តក្នុងការ
            ពិនិត្យ និងកែតម្រូវឲ្យបានត្រឹមត្រូវ ដោយផ្ទាល់លើព័ត៌មានផ្ទាល់ខ្លួនរបស់ខ្ញុំបាទ-នាងខ្ញុំ
            ដូចខាងក្រោម៖
        </div>

        <table class="uhsdc-table">
            <tbody>
            <tr>
                <td>1-ឆ្នាំសិក្សា</td>
                <td>2024-2025</td>
            </tr>
            <tr>
                <td>2-គោត្តនាម និងនាម</td>
                <td>អ៊ុក កែវមុនី</td>
            </tr>
            <tr>
                <td>3-ឡាតាំង</td>
                <td>UK KEVMONY</td>
            </tr>
            <tr>
                <td>4-ភេទ</td>
                <td>ស្រី</td>
            </tr>
            <tr>
                <td>5-ថ្ងៃខែឆ្នាំកំណើត</td>
                <td>03-05-2006 (dd-mm-yyyy)</td>
            </tr>
            <tr>
                <td>6-ទីកន្លែងកំណើត</td>
                <td>កំពត</td>
            </tr>
            <tr>
                <td>7-ប្រឡងជាប់ចូលរៀនឆ្នាំ</td>
                <td>2025</td>
            </tr>
            <tr>
                <td>8-ជំនាញ</td>
                <td>វេជ្ជសាស្ត្រ</td>
            </tr>
            <tr>
                <td>9-ពេលធ្វើកាតជានិស្សិតឆ្នាំទី</td>
                <td>1</td>
            </tr>
            <tr>
                <td>10-ជានិស្សិតប្រភេទ</td>
                <td>បង់ថ្លៃ</td>
            </tr>
            <tr>
                <td>11-អត្តលេខ</td>
                <td>MED240005</td>
            </tr>
            </tbody>
        </table>

        <div class="uhsdc-paragraph">
            ក្រោយពីបានពិនិត្យយ៉ាងល្អិតល្អន់ ខ្ញុំបាទ-នាងខ្ញុំបានឯកភាពថាព័ត៌មានផ្ទាល់ខ្លួន
            ទាំងនេះពិតជាត្រឹមត្រូវ តាមសញ្ញាបត្រមធ្យមសិក្សាទុតិយភូមិ សំបុត្រកំណើត
            ឬអត្តសញ្ញាណប័ណ្ណ។ ខ្ញុំបាទ-នាងខ្ញុំសូមទទួលខុសត្រូវទាំងស្រុងចំពោះមុខច្បាប់
            ជាធរមាន ហើយសូមធ្វើការផ្តិតមេដៃស្តាំ ដើម្បីជាភស្តុតាង។
        </div>

        <div class="uhsdc-footer">
            <div>
                <div class="uhsdc-qr" aria-label="QR placeholder">
                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    <span></span><span class="empty"></span><span class="empty"></span><span></span><span class="empty"></span><span></span><span class="empty"></span>
                    <span></span><span class="empty"></span><span></span><span></span><span class="empty"></span><span></span><span></span>
                    <span class="empty"></span><span></span><span></span><span class="empty"></span><span></span><span class="empty"></span><span></span>
                    <span></span><span class="empty"></span><span></span><span></span><span class="empty"></span><span></span><span class="empty"></span>
                    <span class="empty"></span><span></span><span class="empty"></span><span></span><span></span><span class="empty"></span><span></span>
                    <span></span><span></span><span></span><span class="empty"></span><span></span><span></span><span></span>
                </div>
            </div>

            <div>
                <div class="uhsdc-date">
                    រាជធានីភ្នំពេញ ថ្ងៃទី ២៨ ខែ ឧសភា ឆ្នាំ ២០២៦<br>
                    ស្នាមមេដៃស្ដាំ
                </div>

                <div class="uhsdc-signature">
                    អ៊ុក កែវមុនី
                </div>
            </div>
        </div>
    </div>
</div>
