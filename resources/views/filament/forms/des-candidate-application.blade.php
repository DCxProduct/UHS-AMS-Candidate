@php
    $logoPath = public_path('images/UHS_logo.png');

    $logoUrl = file_exists($logoPath)
        ? asset('images/UHS_logo.png')
        : '';
@endphp

<style>
    :root {
        --des-shell-bg: #eef2f7;
        --des-shell-border: #d6dce5;
        --des-page-bg: #ffffff;
        --des-page-text: #111827;
        --des-line: #111111;
        --des-muted: #4b5563;
        --des-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
    }

    html.dark {
        --des-shell-bg: #000000;
        --des-shell-border: #374151;
        --des-page-bg: #ffffff;
        --des-page-text: #111827;
        --des-line: #111111;
        --des-muted: #4b5563;
        --des-shadow: 0 18px 45px rgba(0, 0, 0, 0.55);
    }

    .des-wrapper {
        background: var(--des-shell-bg);
        border: 1px solid var(--des-shell-border);
        border-radius: 18px;
        overflow-x: auto;
        padding: 24px 0;
    }

    .des-page {
        width: 900px;
        min-height: 1240px;
        margin: 0 auto 24px;
        background: var(--des-page-bg);
        color: var(--des-page-text);
        box-shadow: var(--des-shadow);
        padding: 24px 34px 34px;
        font-family: "Khmer OS Battambang", "Khmer OS", "Noto Sans Khmer", Arial, sans-serif;
        font-size: 12px;
        line-height: 1.35;
    }

    .des-page * {
        box-sizing: border-box;
    }

    .des-border-page {
        min-height: 1180px;
        border: 2px solid var(--des-line);
        padding: 10px 12px 18px;
        position: relative;
    }

    .des-page input[type="text"],
    .des-page input[type="email"],
    .des-page textarea {
        color: #111111 !important;
        font-family: "Khmer OS Battambang", "Khmer OS", Arial, sans-serif;
    }

    .des-page input[type="checkbox"],
    .des-page input[type="radio"] {
        appearance: none;
        -webkit-appearance: none;
        width: 14px;
        height: 14px;
        border: 1.4px solid var(--des-line);
        background: #ffffff;
        border-radius: 0;
        margin: 0 4px;
        position: relative;
        vertical-align: middle;
        cursor: pointer;
    }

    .des-page input[type="checkbox"]:checked::after,
    .des-page input[type="radio"]:checked::after {
        content: "✓";
        position: absolute;
        left: 1px;
        top: -6px;
        font-size: 18px;
        font-weight: 900;
        color: #000000;
    }

    .des-line-input {
        border: 0;
        border-bottom: 1px dotted var(--des-line);
        background: transparent;
        outline: none;
        height: 19px;
        padding: 0 2px;
        font-size: 12px;
    }

    .des-line-input:focus {
        background: rgba(255, 255, 180, 0.45);
    }

    .des-line-input.full {
        width: 100%;
    }

    .des-line-input.sm {
        width: 55px;
    }

    .des-line-input.md {
        width: 120px;
    }

    .des-line-input.lg {
        width: 220px;
    }

    .des-line-input.xl {
        width: 340px;
    }

    .des-logo-area {
        display: flex;
        align-items: center;
        gap: 9px;
    }

    .des-logo {
        width: 74px;
        height: 74px;
        object-fit: contain;
    }

    .des-logo-title {
        font-size: 42px;
        font-weight: 900;
        letter-spacing: 1px;
        line-height: 1;
        color: #1d9bd7;
        font-family: Arial, sans-serif;
    }

    .des-logo-subtitle {
        font-size: 11px;
        margin-top: 4px;
        line-height: 1.35;
        font-family: Arial, sans-serif;
    }

    .des-header {
        display: grid;
        grid-template-columns: 260px 1fr 210px;
        gap: 10px;
        align-items: start;
    }

    .des-register-no {
        text-align: right;
        padding-top: 24px;
        font-size: 17px;
        font-weight: 700;
    }

    .des-main-title {
        text-align: center;
        font-size: 22px;
        font-weight: 900;
        line-height: 1.55;
        margin-top: 18px;
    }

    .des-specialty-note {
        margin-top: 20px;
        border: 1.2px solid var(--des-line);
        border-bottom: 0;
        text-align: center;
        font-size: 13px;
        font-weight: 800;
        padding: 4px 8px;
    }

    .des-specialty-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .des-specialty-table td {
        border: 1.2px solid var(--des-line);
        padding: 3px 5px;
        height: 28px;
        vertical-align: middle;
        font-size: 10.5px;
        line-height: 1.25;
    }

    .des-specialty-no {
        width: 34px;
        text-align: center;
    }

    .des-specialty-text {
        width: calc((100% - 68px) / 2);
    }

    .des-specialty-empty {
        background: #bcbcbc;
    }

    .des-personal-info {
        width: 80%;
        margin: 44px auto 0;
        font-size: 16px;
        line-height: 2;
    }

    .des-form-row {
        display: flex;
        align-items: flex-end;
        gap: 7px;
        margin-bottom: 4px;
    }

    .des-form-row .des-line-input {
        flex: 1;
    }

    .des-small-note {
        font-size: 10.5px;
        font-style: italic;
        line-height: 1.4;
    }

    .des-receipt-box {
        border: 1.7px solid var(--des-line);
        padding: 18px 20px 18px;
        min-height: 545px;
        position: relative;
        margin-bottom: 26px;
    }

    .des-receipt-title {
        text-align: center;
        font-size: 22px;
        font-weight: 900;
        margin-bottom: 16px;
    }

    .des-receipt-row {
        display: flex;
        align-items: flex-end;
        gap: 7px;
        margin-bottom: 8px;
        font-size: 15px;
        line-height: 1.6;
    }

    .des-receipt-row .des-line-input {
        flex: 1;
    }

    .des-receipt-signatures {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        margin-top: 24px;
        text-align: center;
        font-size: 14px;
        line-height: 1.8;
    }

    .des-photo-box {
        width: 105px;
        height: 125px;
        border: 1.4px solid var(--des-line);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 13px;
        line-height: 1.7;
        background: #ffffff;
    }

    .des-receipt-photo {
        position: absolute;
        right: 22px;
        bottom: 22px;
    }

    .des-kingdom-title {
        text-align: center;
        font-size: 18px;
        font-weight: 900;
        line-height: 1.7;
        margin-bottom: 16px;
    }

    .des-letter-title {
        text-align: center;
        font-size: 21px;
        font-weight: 900;
        line-height: 1.55;
        margin-bottom: 16px;
    }

    .des-letter-text {
        font-size: 15px;
        line-height: 1.95;
        text-align: justify;
    }

    .des-center-heading {
        text-align: center;
        font-size: 17px;
        font-weight: 900;
        line-height: 1.7;
        margin: 16px 0;
    }

    .des-doc-list {
        font-size: 14px;
        line-height: 1.85;
        margin-top: 10px;
    }

    .des-letter-bottom {
        display: grid;
        grid-template-columns: 1fr 210px;
        gap: 30px;
        margin-top: 24px;
        align-items: start;
    }

    .des-letter-date {
        text-align: center;
        font-size: 14px;
        line-height: 1.9;
    }
    .des-resume-header {
        display: grid;
        grid-template-columns: 220px 1fr 120px;
        gap: 14px;
        align-items: start;
        margin-bottom: 12px;
    }

    .des-logo-card {
        border: 1.2px solid var(--des-line);
        height: 76px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
    }

    .des-kh-logo-title {
        font-size: 30px;
        font-weight: 900;
        color: #111111;
        line-height: 1;
    }

    .des-resume-title {
        text-align: center;
        font-size: 22px;
        font-weight: 900;
        line-height: 1.45;
    }

    .des-resume-subtitle {
        display: inline-block;
        border: 1.2px solid var(--des-line);
        padding: 5px 28px;
        font-size: 19px;
        font-weight: 900;
        margin-top: 8px;
    }

    .des-resume-note {
        font-size: 12px;
        margin-top: 5px;
    }

    .des-resume-section-title {
        font-size: 15px;
        font-weight: 900;
        margin-top: 8px;
        margin-bottom: 4px;
    }

    .des-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 7px;
        margin-bottom: 3px;
        font-size: 13px;
        line-height: 1.65;
    }

    .des-row .des-line-input {
        flex: 1;
        min-width: 80px;
    }

    .des-small-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-top: 8px;
    }

    .des-small-table th,
    .des-small-table td {
        border: 1.2px solid var(--des-line);
        height: 30px;
        padding: 3px 5px;
        text-align: center;
        vertical-align: middle;
        font-size: 11px;
        line-height: 1.35;
    }

    .des-small-table input {
        width: 100%;
        height: 100%;
        border: 0;
        outline: none;
        background: transparent;
        text-align: center;
        font-size: 11px;
    }

    .des-contract-title {
        text-align: center;
        font-size: 21px;
        font-weight: 900;
        line-height: 1.75;
        margin-bottom: 28px;
    }

    .des-contract-body {
        font-size: 16px;
        line-height: 2.15;
        text-align: justify;
    }

    .des-contract-body .des-line-input {
        height: 20px;
        font-size: 14px;
    }

    .des-bullet {
        margin-top: 18px;
        margin-bottom: 16px;
    }

    @media print {
        .des-wrapper {
            background: #ffffff;
            border: none;
            border-radius: 0;
            padding: 0;
        }

        .des-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }
</style>

<div class="des-wrapper">
    {{-- PAGE 1 --}}
    <div class="des-page">
        <div class="des-border-page">
            <div class="des-header">
                <div class="des-logo-area">
                    <img src="{{ $logoUrl }}" class="des-logo" alt="UHS Logo">
                    <div>
                        <div class="des-logo-title">UHS</div>
                        <div class="des-logo-subtitle">University of Health Sciences</div>
                    </div>
                </div>

                <div></div>

                <div class="des-register-no">
                    លេខបញ្ជី
                    <input class="des-line-input md" type="text">
                </div>
            </div>

            <div class="des-main-title">
                ពាក្យសុំចុះឈ្មោះជាបេក្ខជនប្រឡងជ្រើសរើសចូលរៀន<br>
                ថ្នាក់វេជ្ជបណ្ឌិតឯកទេស<br>
                នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០២៣-២០២៤
            </div>

            <div class="des-specialty-note">
                សូមជ្រើសរើសយកឯកទេស ដោយគូសសញ្ញា ✓ ក្នុងប្រអប់តែមួយគត់នៃបញ្ជីឯកទេសខាងក្រោម
            </div>

            <table class="des-specialty-table">
                <tr>
                    <td class="des-specialty-no">1.</td>
                    <td class="des-specialty-text"><label><input type="checkbox"> កាយវិភាគ និងកោសិការោគវិទ្យា (Anatomopathologie: ANA)</label></td>
                    <td class="des-specialty-no">12.</td>
                    <td class="des-specialty-text"><label><input type="checkbox"> សម្ភព និង រោគស្ត្រី (Gynécologie-obstétrique: GYN)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">2.</td>
                    <td><label><input type="checkbox"> ប្រសោធនកម្ម ដាក់ថ្នាំសណ្តំ និង សង្គ្រោះបន្ទាន់<br><span class="des-small-note">(Anesthésie Réanimation et Médecine d’Urgence: ANE)</span></label></td>
                    <td class="des-specialty-no">13.</td>
                    <td><label><input type="checkbox"> វិទ្យុសាស្ត្រ និង រូបភាពវេជ្ជសាស្ត្រ<br><span class="des-small-note">(Radiologie et Imagerie Médicale: IMA)</span></label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">3.</td>
                    <td><label><input type="checkbox"> ជំងឺមហារីក (Oncologie: CAN)</label></td>
                    <td class="des-specialty-no">14.</td>
                    <td><label><input type="checkbox"> វេជ្ជសាស្ត្រទូទៅ (Médecine Interne: MEG)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">4.</td>
                    <td><label><input type="checkbox"> បេះដូងវិទ្យា (Cardiologie: CAR)</label></td>
                    <td class="des-specialty-no">15.</td>
                    <td><label><input type="checkbox"> សល្យសាស្ត្រប្រព័ន្ធប្រសាទ (Neuro-Chirurgie: NEU)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">5.</td>
                    <td><label><input type="checkbox"> សល្យសាស្ត្រទូទៅ និងប្រព័ន្ធរំលាយអាហារ<br><span class="des-small-note">(Chirurgie Générale et Digestive: CHV)</span></label></td>
                    <td class="des-specialty-no">16.</td>
                    <td><label><input type="checkbox"> ចក្ខុរោគ (Ophtalmologie: OPT)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">6.</td>
                    <td><label><input type="checkbox"> សល្យសាស្ត្រជំងឺឆ្អឹង និងបាក់បែក<br><span class="des-small-note">(Chirurgie Orthopédique et Traumatologique: CHO)</span></label></td>
                    <td class="des-specialty-no">17.</td>
                    <td><label><input type="checkbox"> ត្រចៀក ច្រមុះ បំពង់ក (ORL)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">7.</td>
                    <td><label><input type="checkbox"> សល្យសាស្ត្រកុមារ (Chirurgie Pédiatrique: CHP)</label></td>
                    <td class="des-specialty-no">18.</td>
                    <td><label><input type="checkbox"> វេជ្ជជីវរោគកុមារ (Pédiatrie: PED)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">8.</td>
                    <td><label><input type="checkbox"> កែសម្ផស្សសល្យសាស្ត្រ<br><span class="des-small-note">(Chirurgie Plastique et Reconstructive: CPL)</span></label></td>
                    <td class="des-specialty-no">19.</td>
                    <td><label><input type="checkbox"> ជំងឺសួត (Pneumologie: PNE)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">9.</td>
                    <td><label><input type="checkbox"> ជំងឺសើស្បែក (Dermatologie: DER)</label></td>
                    <td class="des-specialty-no">20.</td>
                    <td><label><input type="checkbox"> វិកលវិទ្យា (Psychiatrie: PSY)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">10.</td>
                    <td><label><input type="checkbox"> ជំងឺទឹកនោមផ្អែម និងជំងឺក្រពេញ<br><span class="des-small-note">(Diabétologie-Endocrinologie et Maladies Métabolique: END)</span></label></td>
                    <td class="des-specialty-no">21.</td>
                    <td><label><input type="checkbox"> សល្យសាស្ត្រប្រព័ន្ធទឹកមូត្រ (Chirurgie Urologique: URO)</label></td>
                </tr>
                <tr>
                    <td class="des-specialty-no">11.</td>
                    <td><label><input type="checkbox"> ជំងឺថ្លើម ក្រពះ ពោះវៀន (Hépato-Gastro-Enterologie: GAS)</label></td>
                    <td class="des-specialty-empty"></td>
                    <td class="des-specialty-empty"></td>
                </tr>
            </table>

            <div class="des-personal-info">
                <div class="des-form-row">
                    <span>នាមត្រកូល និង នាមខ្លួន</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-form-row" style="font-size: 13px;">
                    <span>Family Name:</span>
                    <input class="des-line-input" type="text">
                    <span>Given Names:</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-small-note">(សូមសរសេរអក្សរធំទាំងអស់)</div>

                <div class="des-form-row">
                    <span>ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span>
                    <input class="des-line-input" type="text">
                    <span>ភេទ</span>
                    <input class="des-line-input md" type="text">
                </div>

                <div class="des-form-row">
                    <span>ទីកន្លែងកំណើត (រាជធានី/ខេត្ត)</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-form-row">
                    <span>កម្រិតសញ្ញាបត្របច្ចុប្បន្ន៖</span>
                    <label><input type="checkbox"> បរិ.វិ.វេជ្ជសាស្ត្រ</label>
                    <label><input type="checkbox"> វេជ្ជបណ្ឌិត</label>
                    <span>ឆ្នាំបញ្ចប់</span>
                    <input class="des-line-input sm" type="text">
                </div>

                <div class="des-form-row">
                    <span>មកពីសាកលវិទ្យាល័យ</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-form-row">
                    <span>ឆ្នាំសិក្សាប្រឡងថ្នាក់ជាតិជាប់ចូលរៀនវិស័យសុខាភិបាល</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-form-row">
                    <span>មុខរបរបច្ចុប្បន្ន</span>
                    <input class="des-line-input" type="text">
                    <span>ស.វិទ្យាល័យ/អង្គភាព</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-form-row">
                    <span>លេខទូរស័ព្ទ</span>
                    <input class="des-line-input" type="text">
                    <span>អ៊ីមែល</span>
                    <input class="des-line-input" type="email">
                </div>
            </div>
        </div>
    </div>

    {{-- PAGE 2 --}}
    <div class="des-page">
        @for ($i = 1; $i <= 2; $i++)
            <div class="des-receipt-box">
                <div class="des-receipt-title">បង្កាន់ដៃទទួលពាក្យ</div>

                <div class="des-receipt-row">
                    <span>- នាមត្រកូល និង នាមខ្លួន (ជាអក្សរខ្មែរ)៖</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-receipt-row">
                    <span>- ឈ្មោះអក្សរឡាតាំង (សរសេរអក្សរធំ)</span>
                    <input class="des-line-input" type="text">
                    <span>ភេទ</span>
                    <input class="des-line-input sm" type="text">
                    <span>សញ្ជាតិ</span>
                    <input class="des-line-input sm" type="text">
                </div>

                <div class="des-receipt-row">
                    <span>- ថ្ងៃខែឆ្នាំកំណើត</span>
                    <input class="des-line-input" type="text">
                    <span>ទីកន្លែងកំណើត</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-receipt-row">
                    <span>- កម្រិតសញ្ញាបត្របច្ចុប្បន្ន</span>
                    <input class="des-line-input" type="text">
                    <span>មកពីសាកលវិទ្យាល័យ/សាលា</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-receipt-row">
                    <span>- ទូរស័ព្ទសាមីខ្លួន</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-receipt-row">
                    <span>- ឈ្មោះអាណាព្យាបាល ឬ អ្នកទំនាក់ទំនងបន្ទាន់</span>
                    <input class="des-line-input" type="text">
                    <span>ទូរស័ព្ទអាណាព្យាបាល</span>
                    <input class="des-line-input" type="text">
                </div>

                <div class="des-receipt-row" style="margin-top: 18px;">
                    <span>សុំចុះឈ្មោះជាបេក្ខជនប្រឡងជ្រើសរើសចូលរៀន ថ្នាក់វេជ្ជបណ្ឌិតឯកទេស</span>
                    <input class="des-line-input" type="text">
                </div>

                <div style="font-size:15px; line-height:1.8; margin-top:8px;">
                    នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០២៣ - ២០២៤ ។
                </div>

                <div class="des-receipt-signatures">
                    <div>
                        រាជធានីភ្នំពេញ ថ្ងៃទី
                        <input class="des-line-input sm" type="text">
                        ខែ
                        <input class="des-line-input sm" type="text">
                        ឆ្នាំ២០
                        <input class="des-line-input sm" type="text"><br>
                        អ្នកទទួលពាក្យ
                    </div>

                    <div>
                        រាជធានីភ្នំពេញ ថ្ងៃទី
                        <input class="des-line-input sm" type="text">
                        ខែ
                        <input class="des-line-input sm" type="text">
                        ឆ្នាំ២០
                        <input class="des-line-input sm" type="text"><br>
                        ហត្ថលេខា និង ឈ្មោះសាមីខ្លួន
                    </div>
                </div>

                <div class="des-receipt-photo">
                    <div class="des-photo-box">
                        បិទរូបថតថ្មី<br>
                        ៤ x ៦<br><br>
                        លេខបញ្ជី៖<br>
                        <input class="des-line-input sm" type="text">
                    </div>
                </div>
            </div>
        @endfor
    </div>

    {{-- PAGE 3 --}}
    <div class="des-page">
        <div class="des-kingdom-title">
            ព្រះរាជាណាចក្រកម្ពុជា<br>
            ជាតិ សាសនា ព្រះមហាក្សត្រ
        </div>

        <div class="des-letter-title">
            ពាក្យសុំចុះឈ្មោះជាបេក្ខជនប្រឡងជ្រើសរើសចូលរៀន<br>
            ថ្នាក់វេជ្ជបណ្ឌិតឯកទេស<br>
            នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០២៣ - ២០២៤
        </div>

        <div class="des-letter-text">
            - ខ្ញុំបាទ/នាងខ្ញុំឈ្មោះ (ជាអក្សរខ្មែរ)៖
            <input class="des-line-input lg" type="text">
            ជាអក្សរឡាតាំង
            <input class="des-line-input lg" type="text">
            ភេទ
            <input class="des-line-input sm" type="text">
            សញ្ជាតិ
            <input class="des-line-input sm" type="text">
            ថ្ងៃខែឆ្នាំកំណើត
            <input class="des-line-input md" type="text">
            ទីកន្លែងកំណើត
            <input class="des-line-input lg" type="text"><br>

            - មកពីសាកលវិទ្យាល័យ/សាលា
            <input class="des-line-input xl" type="text">
            <br>

            - កម្រិតសញ្ញាបត្របច្ចុប្បន្ន
            <input class="des-line-input lg" type="text">
            កាលបរិច្ឆេទប្រឡងជាប់
            <input class="des-line-input md" type="text"><br>

            - សព្វថ្ងៃជា
            <input class="des-line-input lg" type="text">
            នៅ (សាកលវិទ្យាល័យ/អង្គភាព/ក្រសួង)
            <input class="des-line-input lg" type="text"><br>

            <strong>
                (ប្រសិនបើបេក្ខជនជាមន្ត្រីរាជការ ត្រូវមានលិខិតបញ្ជាក់ពីប្រធានមន្ទីរសុខាភិបាលខេត្ត/រាជធានី)
            </strong>
        </div>

        <div class="des-center-heading">
            សូមគោរពជូន<br>
            ឯកឧត្តមប្រធានគណៈកម្មការប្រឡងជ្រើសរើសនិស្សិតចូលរៀនថ្នាក់វេជ្ជបណ្ឌិតឯកទេសនៅ ស.វ.ស
        </div>

        <div class="des-letter-text">
            ខ្ញុំបាទ/នាងខ្ញុំសូមគោរពជម្រាបជូនឯកឧត្តមមេត្តាជ្រាបថា ខ្ញុំបាទ/នាងខ្ញុំមានបំណងចូលរៀន
            ថ្នាក់វេជ្ជបណ្ឌិតឯកទេស ខ្នែក
            <input class="des-line-input lg" type="text">
            នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០២៣ - ២០២៤។<br>

            ខ្ញុំបាទ/នាងខ្ញុំសូមធានាថា ប្រសិនបើបានប្រឡងជាប់ចូលរៀននៅខ្នែកនេះនៅ ស.វ.ស
            ខ្ញុំបាទ/នាងខ្ញុំនឹងពុំទាមទារឱ្យរាជរដ្ឋាភិបាលដោះស្រាយការងារសម្រាប់រូបខ្ញុំបាទ/នាងខ្ញុំ
            ក្រោយពីបានបញ្ចប់ការសិក្សាឡើយ។<br>

            ខ្ញុំបាទ/នាងខ្ញុំសូមសន្យាថា នឹងគោរពតាមនូវរាល់លក្ខខណ្ឌ គោលការណ៍ បទបញ្ជាផ្ទៃក្នុង
            នៃការប្រឡង និងសេចក្តីសម្រេចនានារបស់ គណៈកម្មការប្រឡងជ្រើសរើសនិស្សិតចូលរៀន
            ថ្នាក់វេជ្ជបណ្ឌិតឯកទេសនេះ។<br>

            អាស្រ័យហេតុនេះ សូមឯកឧត្តមមេត្តាអនុញ្ញាតចុះឈ្មោះខ្ញុំបាទ/នាងខ្ញុំក្នុងបញ្ជីជាបេក្ខជន
            ប្រឡងជ្រើសរើសចូលរៀន ថ្នាក់វេជ្ជបណ្ឌិតឯកទេស
            <input class="des-line-input lg" type="text">
            នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០២៣-២០២៤
            ដោយសេចក្តីអនុគ្រោះ។
        </div>

        <div class="des-doc-list">
            សូមជូនភ្ជាប់មកជាមួយនូវ៖<br>
            ១. ស្លាកបត្រឯកតជន ឬបង្កាន់ដៃទទួលពាក្យ ............................................................. ០១ ច្បាប់<br>
            ២. ពាក្យសុំចុះឈ្មោះជាបេក្ខជនប្រឡងជ្រើសរើស ......................................................... ០២ ច្បាប់<br>
            ៣. វិញ្ញាបនបត្រ ឬ សញ្ញាបត្រនៃកម្រិតសិក្សាចុងក្រោយ ................................................ ០១ ច្បាប់<br>
            ៤. ព្រឹត្តិបត្រពិន្ទុ ពីឆ្នាំទី២ រហូតដល់ឆ្នាំចុងក្រោយ មាន GPA ច្បាប់ដើម ........................... ០១ ច្បាប់<br>
            ៥. ជីវប្រវត្តិសង្ខេប បិទរូបថតថ្មី ៤ x ៦ ហាមប្រើរូបថតសេន ............................................ ០២ ច្បាប់<br>
            ៦. កិច្ចសន្យាគោរពបទបញ្ជាផ្ទៃក្នុងស្តីពីការប្រឡង ..................................................... ០១ ច្បាប់<br>
            ៧. ចំពោះបេក្ខជនជាមន្ត្រីរាជការ ត្រូវមានលិខិតបញ្ជាក់ពីប្រធានអង្គភាព ............................ ០១ ច្បាប់
        </div>

        <div class="des-letter-bottom">
            <div class="des-letter-date">
                ថ្ងៃ ខែ ឆ្នាំថោះ បញ្ចស័ក ព.ស.២៥៦៧<br>
                រាជធានីភ្នំពេញ ថ្ងៃទី
                <input class="des-line-input sm" type="text">
                ខែ
                <input class="des-line-input sm" type="text">
                ឆ្នាំ២០
                <input class="des-line-input sm" type="text"><br><br>
                បានពិនិត្យត្រឹមត្រូវ<br>
                អ្នកទទួលពាក្យ
            </div>

            <div style="text-align:center;">
                ហត្ថលេខា និង ឈ្មោះសាមីខ្លួន<br><br>
                <div style="height:70px;"></div>
                <div class="des-photo-box" style="margin:0 auto;">
                    បិទរូបថតថ្មី<br>
                    ៤ x ៦
                </div>
            </div>
        </div>
    </div>
    {{-- PAGE 4 --}}
    <div class="des-page">
        <div class="des-resume-header">
            <div class="des-logo-card">
                <img src="{{ $logoUrl }}" class="des-logo" alt="UHS Logo">
                <div>
                    <div class="des-kh-logo-title">ស.វ.ស</div>
                    <div style="font-size:10px;">University of Health Sciences</div>
                </div>
            </div>

            <div style="text-align:center;">
                <div class="des-resume-title">
                    ព្រះរាជាណាចក្រកម្ពុជា<br>
                    ជាតិ សាសនា ព្រះមហាក្សត្រ
                </div>

                <div class="des-resume-subtitle">
                    ប្រវត្តិរូបសង្ខេប
                </div>

                <div class="des-resume-note">
                    (ត្រូវសរសេរដោយខ្លួនឯងផ្ទាល់ ហាមគូសលុប)
                </div>
            </div>

            <div class="des-photo-box">
                បិទរូបថតថ្មី<br>
                ៤ x ៦
            </div>
        </div>

        <div class="des-resume-section-title">I- ព័ត៌មានផ្ទាល់ខ្លួន</div>

        <div class="des-row">
            <span>- គោត្តនាម និង នាម (ជាអក្សរខ្មែរ)៖</span>
            <input class="des-line-input" type="text">
            <span>អក្សរឡាតាំង</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-row">
            <span>- ភេទ</span>
            <input class="des-line-input sm" type="text">
            <span>សញ្ជាតិ</span>
            <input class="des-line-input sm" type="text">
            <span>ជនជាតិ</span>
            <input class="des-line-input sm" type="text">
            <span>សាសនា</span>
            <input class="des-line-input sm" type="text">
            <label>រៀបការ <input type="checkbox"></label>
            <label>នៅលីវ <input type="checkbox"></label>
        </div>

        <div class="des-row">
            <span>- ថ្ងៃ-ខែ-ឆ្នាំកំណើត</span>
            <input class="des-line-input md" type="text">
            <span>ទីកន្លែងកំណើត៖ ភូមិ</span>
            <input class="des-line-input md" type="text">
            <span>ឃុំ/សង្កាត់</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>ស្រុក/ខណ្ឌ</span>
            <input class="des-line-input md" type="text">
            <span>រាជធានី/ខេត្ត</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- អាសយដ្ឋានបច្ចុប្បន្ន៖ ផ្ទះលេខ</span>
            <input class="des-line-input sm" type="text">
            <span>ផ្លូវលេខ</span>
            <input class="des-line-input sm" type="text">
            <span>ឃុំ/សង្កាត់</span>
            <input class="des-line-input md" type="text">
            <span>ស្រុក/ខណ្ឌ</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- កម្រិតវប្បធម៌ជាតិ</span>
            <input class="des-line-input" type="text">
            <span>សម័យប្រឡង</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- កម្រិតសញ្ញាបត្រជំនាញ</span>
            <input class="des-line-input" type="text">
            <span>សម័យប្រឡង</span>
            <input class="des-line-input md" type="text">
            <span>មកពីសាកលវិទ្យាល័យ</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-row">
            <span>- មុខរបរបច្ចុប្បន្ន</span>
            <input class="des-line-input" type="text">
            <span>ទីកន្លែងធ្វើការ/អង្គភាព</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-resume-section-title">II- ព័ត៌មានគ្រួសារ</div>
        <div class="des-resume-section-title" style="font-size:13px;">ក- អំពីឪពុក-ម្តាយបង្កើត</div>

        <div class="des-row">
            <span>- ឪពុកឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ឆ្នាំកំណើត</span>
            <input class="des-line-input sm" type="text">
            <span>ជនជាតិ</span>
            <input class="des-line-input sm" type="text">
            <span>សញ្ជាតិ</span>
            <input class="des-line-input sm" type="text">
            <label>នៅរស់ <input type="checkbox"></label>
            <label>ស្លាប់ <input type="checkbox"></label>
        </div>

        <div class="des-row">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
            <span>ទីកន្លែងធ្វើការ</span>
            <input class="des-line-input" type="text">
            <span>លេខទូរស័ព្ទ</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- ម្តាយឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ឆ្នាំកំណើត</span>
            <input class="des-line-input sm" type="text">
            <span>ជនជាតិ</span>
            <input class="des-line-input sm" type="text">
            <span>សញ្ជាតិ</span>
            <input class="des-line-input sm" type="text">
            <label>នៅរស់ <input type="checkbox"></label>
            <label>ស្លាប់ <input type="checkbox"></label>
        </div>

        <div class="des-row">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
            <span>ទីកន្លែងធ្វើការ</span>
            <input class="des-line-input" type="text">
            <span>លេខទូរស័ព្ទ</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- អាសយដ្ឋានបច្ចុប្បន្ន៖ ផ្ទះលេខ</span>
            <input class="des-line-input sm" type="text">
            <span>ផ្លូវលេខ</span>
            <input class="des-line-input sm" type="text">
            <span>ឃុំ/សង្កាត់</span>
            <input class="des-line-input md" type="text">
            <span>ស្រុក/ខណ្ឌ</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-row">
            <span>- អាណាព្យាបាលឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ត្រូវជា</span>
            <input class="des-line-input md" type="text">
            <span>លេខទូរស័ព្ទ</span>
            <input class="des-line-input md" type="text">
        </div>

        <div class="des-resume-section-title">ខ- អំពីបងប្អូនបង្កើត</div>

        <div class="des-row">
            <span>១- ឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ភេទ</span>
            <input class="des-line-input sm" type="text">
            <span>ឆ្នាំកំណើត</span>
            <input class="des-line-input md" type="text">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-row">
            <span>២- ឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ភេទ</span>
            <input class="des-line-input sm" type="text">
            <span>ឆ្នាំកំណើត</span>
            <input class="des-line-input md" type="text">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-row">
            <span>៣- ឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ភេទ</span>
            <input class="des-line-input sm" type="text">
            <span>ឆ្នាំកំណើត</span>
            <input class="des-line-input md" type="text">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-resume-section-title">គ- អំពីប្តីឬ ប្រពន្ធ និង កូន</div>

        <div class="des-row">
            <span>- ប្តីឬ ប្រពន្ធឈ្មោះ</span>
            <input class="des-line-input" type="text">
            <span>ថ្ងៃខែឆ្នាំកំណើត</span>
            <input class="des-line-input md" type="text">
            <span>មុខរបរ</span>
            <input class="des-line-input" type="text">
        </div>

        <div class="des-row">
            <span>- មានកូនចំនួន</span>
            <input class="des-line-input sm" type="text">
            <span>នាក់៖ ប្រុស</span>
            <input class="des-line-input sm" type="text">
            <span>នាក់ ស្រី</span>
            <input class="des-line-input sm" type="text">
            <span>នាក់</span>
        </div>

        <div class="des-resume-section-title">III- ព័ត៌មានសិក្សា</div>

        <table class="des-small-table">
            <thead>
            <tr>
                <th>គ្រឹះស្ថានបណ្តុះបណ្តាល</th>
                <th>កម្រិតសញ្ញាបត្រ និង ជំនាញ</th>
                <th>ពីឆ្នាំណា ដល់ឆ្នាំណា</th>
                <th>ប្រទេស</th>
                <th>ឆ្នាំបញ្ចប់ការសិក្សា ឬ ទទួលបានសញ្ញាបត្រ</th>
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
            </tbody>
        </table>

        <div class="des-resume-section-title">IV- ប្រវត្តិការងារ</div>

        <table class="des-small-table">
            <thead>
            <tr>
                <th>ឆ្នាំចូលបំពេញការងារ</th>
                <th>ឆ្នាំបញ្ចប់ការងារ</th>
                <th>អង្គភាព/ស្ថាប័ន</th>
                <th>ក្រសួង</th>
                <th>តួនាទី</th>
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
            </tbody>
        </table>

        <div style="font-size:13px; line-height:1.7; text-align:justify; margin-top:12px;">
            ខ្ញុំបាទ/នាងខ្ញុំសូមធានាអះអាងថា សេចក្តីរៀបរាប់ក្នុងប្រវត្តិរូបសង្ខេបខាងលើនេះ
            ពិតជាត្រឹមត្រូវឥតក្លែងបន្លំឡើយ។ ប្រសិនបើមានចំណុចណាមួយប្រាសចាកពីការពិត
            ខ្ញុំបាទ/នាងខ្ញុំសូមទទួលខុសត្រូវទាំងស្រុងចំពោះមុខច្បាប់ជាធរមាន។
        </div>

        <div style="text-align:right; font-size:14px; line-height:1.8; margin-top:14px;">
            រាជធានីភ្នំពេញ ថ្ងៃទី
            <input class="des-line-input sm" type="text">
            ខែ
            <input class="des-line-input sm" type="text">
            ឆ្នាំ២០
            <input class="des-line-input sm" type="text"><br>
            ស្នាមមេដៃស្តាំ និង ឈ្មោះសាមីខ្លួន
        </div>
    </div>

    {{-- PAGE 5 --}}
    <div class="des-page">
        <div class="des-contract-title">
            គណៈកម្មការប្រឡងជ្រើសរើសនិស្សិតចូលរៀន<br>
            ថ្នាក់វេជ្ជបណ្ឌិតឯកទេសនៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល<br>
            កិច្ចសន្យា
        </div>

        <div class="des-contract-body">
            ខ្ញុំបាទ/នាងខ្ញុំឈ្មោះ
            <input class="des-line-input xl" type="text">
            ភេទ
            <input class="des-line-input sm" type="text">
            ថ្ងៃ ខែ ឆ្នាំកំណើត
            <input class="des-line-input lg" type="text">
            ទីកន្លែងកំណើត
            <input class="des-line-input xl" type="text">
            បានដាក់ពាក្យសុំជាបេក្ខជនប្រឡងជ្រើសរើសនិស្សិតចូលរៀនថ្នាក់វេជ្ជបណ្ឌិតឯកទេស
            <input class="des-line-input xl" type="text">
            នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០
            <input class="des-line-input sm" type="text">
            -២០
            <input class="des-line-input sm" type="text">
            សូមបញ្ជាក់អះអាងថា៖

            <div class="des-bullet">
                • ខ្ញុំបាទ/នាងខ្ញុំបានអាន និង យល់ច្បាស់ អំពីខ្លឹមសារនៃសេចក្តីជូនដំណឹង
                និង បទបញ្ជាផ្ទៃក្នុងសម្រាប់ការប្រឡងជ្រើសរើសនិស្សិតចូលរៀនថ្នាក់វេជ្ជបណ្ឌិតឯកទេស
                នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល សម្រាប់ឆ្នាំសិក្សា ២០
                <input class="des-line-input sm" type="text">
                -២០
                <input class="des-line-input sm" type="text">
                ។
            </div>

            <div class="des-bullet">
                • ខ្ញុំបាទ/នាងខ្ញុំសូមសន្យាថា នឹងគោរពអនុវត្តតាមគោលការណ៍/បទបញ្ជាផ្ទៃក្នុងនេះជាដាច់ខាត។
            </div>

            <div class="des-bullet">
                • ប្រសិនបើខ្ញុំបាទ/នាងខ្ញុំបានរំលោភលើចំណុចណាមួយនៃគោលការណ៍/បទបញ្ជាផ្ទៃក្នុង
                នៃការប្រឡងនេះ ខ្ញុំបាទ/នាងខ្ញុំសុខចិត្តទទួលយល់ព្រមតាមការសម្រេចរបស់
                គណៈកម្មការប្រឡងជ្រើសរើសនិស្សិតចូលរៀនថ្នាក់វេជ្ជបណ្ឌិតឯកទេស
                នៅសាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល និង សូមទទួលខុសត្រូវចំពោះមុខច្បាប់ជាធរមាន។
            </div>

            <div style="text-align:right; margin-top:70px; line-height:2;">
                ថ្ងៃ ខែ ឆ្នាំថោះ បញ្ចស័ក ព.ស.២៥៦៧<br>
                ធ្វើនៅរាជធានីភ្នំពេញ, ថ្ងៃទី
                <input class="des-line-input sm" type="text">
                ខែ
                <input class="des-line-input sm" type="text">
                ឆ្នាំ២០
                <input class="des-line-input sm" type="text"><br><br>
                ស្នាមមេដៃ និង ឈ្មោះ
            </div>
        </div>
    </div>
</div>
