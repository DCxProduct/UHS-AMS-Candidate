@php
    $statePath = 'data';
@endphp

<style>
    @font-face {
        font-family: 'UHS-Battambang';
        src: url('{{ asset('KhmerOS_battambang.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
    }

    @font-face {
        font-family: 'UHS-Muol';
        src: url('{{ asset('KhmerOS_muollight.ttf') }}') format('truetype');
        font-weight: 400;
        font-style: normal;
    }

    .nee-wrapper {
        background: #e5e7eb;
        padding: 12px;
        font-family: 'UHS-Battambang', 'Khmer OS Battambang', 'Noto Sans Khmer', sans-serif;
        color: #111;
    }

    .nee-toolbar {
        width: 210mm;
        margin: 0 auto 10px;
        display: flex;
        justify-content: flex-end;
    }

    .nee-print-btn {
        border: 0;
        border-radius: 8px;
        background: #15803d;
        color: #fff;
        padding: 8px 16px;
        font-size: 14px;
        cursor: pointer;
        font-family: inherit;
    }

    .nee-page {
        width: 210mm;
        min-height: 297mm;
        margin: 0 auto 12px;
        background: #fff;
        position: relative;
        padding: 10mm 14mm 10mm 14mm;
        box-shadow: 0 0 4px rgba(0, 0, 0, 0.18);
        overflow: hidden;
    }

    .receipt-copy {
        position: relative;
        min-height: 128mm;
        padding: 0;
    }

    .receipt-header {
        text-align: center;
        font-family: 'UHS-Muol', 'Khmer OS Muol Light', serif;
        font-size: 10.7px;
        line-height: 1.55;
        margin-top: 1mm;
    }

    .receipt-ministry {
        margin-top: 9mm;
        font-family: 'UHS-Muol', 'Khmer OS Muol Light', serif;
        font-size: 9px;
        line-height: 1.65;
        width: 75mm;
    }

    .receipt-photo {
        position: absolute;
        top: 6mm;
        right: 18mm;
        width: 30mm;
        height: 38mm;
        border: 1.2px solid #222;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-family: 'UHS-Muol', 'Khmer OS Muol Light', serif;
        font-size: 9.5px;
        line-height: 1.8;
        background: #fff;
    }

    .receipt-title {
        text-align: center;
        font-family: 'UHS-Muol', 'Khmer OS Muol Light', serif;
        font-size: 10.4px;
        text-decoration: underline;
        margin-top: 8mm;
        margin-bottom: 7mm;
    }

    .receipt-body {
        font-size: 10.1px;
        line-height: 1.55;
        margin-top: 1mm;
    }

    .receipt-row {
        margin-bottom: 1.4mm;
        white-space: nowrap;
    }

    .receipt-line {
        display: inline-block;
        height: 16px;
        border: 0;
        border-bottom: 1px dotted #111;
        background: transparent;
        outline: none;
        padding: 0 2px;
        font-size: 10px;
        font-family: inherit;
        vertical-align: baseline;
    }

    .w-15 { width: 15mm; }
    .w-18 { width: 18mm; }
    .w-22 { width: 22mm; }
    .w-25 { width: 25mm; }
    .w-30 { width: 30mm; }
    .w-35 { width: 35mm; }
    .w-40 { width: 40mm; }
    .w-45 { width: 45mm; }
    .w-50 { width: 50mm; }
    .w-55 { width: 55mm; }
    .w-60 { width: 60mm; }
    .w-65 { width: 65mm; }
    .w-70 { width: 70mm; }
    .w-75 { width: 75mm; }

    .receipt-signatures {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12mm;
        text-align: center;
        margin-top: 5mm;
        font-size: 10px;
        line-height: 1.6;
    }

    .sign-space {
        height: 14mm;
    }

    .cut-line {
        border-top: 1px dashed #b79ced;
        margin: 7mm 0 5mm;
    }

    .page-number {
        position: absolute;
        right: 6mm;
        bottom: 3mm;
        font-size: 10px;
    }

    select.receipt-line {
        appearance: none;
        -webkit-appearance: none;
        border-radius: 0;
    }

    @media print {
        body {
            background: white !important;
        }

        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        .fi-ac,
        .nee-toolbar {
            display: none !important;
        }

        .nee-wrapper {
            background: #fff !important;
            padding: 0 !important;
        }

        .nee-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 !important;
            box-shadow: none !important;
            page-break-after: always;
        }

        .nee-page:last-child {
            page-break-after: auto;
        }
    }
</style>

<div class="nee-wrapper">
    <div class="nee-toolbar">
        <button type="button" class="nee-print-btn" onclick="window.print()">
            បោះពុម្ព
        </button>
    </div>

    {{-- PAGE 1 --}}
    <div class="nee-page">
        @foreach ([1, 2] as $copy)
            <div class="receipt-copy">
                <div class="receipt-header">
                    ព្រះរាជាណាចក្រកម្ពុជា<br>
                    ជាតិ សាសនា ព្រះមហាក្សត្រ
                </div>

                <div class="receipt-photo">
                    បិទរូបថតថ្មី<br>
                    ៤ x ៦
                </div>

                <div class="receipt-ministry">
                    គណៈកម្មាធិការប្រឡងថ្នាក់ជាតិ<br>
                    សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល
                </div>

                <div class="receipt-title">
                    បង្កាន់ដៃទទួលពាក្យ
                </div>

                <div class="receipt-body">
                    <div class="receipt-row">
                        លេខរៀង
                        <input class="receipt-line w-25" type="text" wire:model.live="{{ $statePath }}.application_no">
                    </div>

                    <div class="receipt-row">
                        នាមត្រកូល និងនាមខ្លួន
                        <input class="receipt-line w-50" type="text" wire:model.live="{{ $statePath }}.name">
                        អក្សរឡាតាំង
                        <input class="receipt-line w-45" type="text" wire:model.live="{{ $statePath }}.latin_name">
                    </div>

                    <div class="receipt-row">
                        ថ្ងៃខែឆ្នាំកំណើត
                        <input class="receipt-line w-25" type="text" wire:model.live="{{ $statePath }}.date_of_birth">
                        ភេទ
                        <select class="receipt-line w-18" wire:model.live="{{ $statePath }}.gender">
                            <option value=""></option>
                            <option value="male">ប្រុស</option>
                            <option value="female">ស្រី</option>
                        </select>
                        ទីកន្លែងកំណើត
                        <input class="receipt-line w-35" type="text" wire:model.live="{{ $statePath }}.birth_place">
                    </div>

                    <div class="receipt-row">
                        បេក្ខជនមកពី
                        <input class="receipt-line w-40" type="text" wire:model.live="{{ $statePath }}.extra_data.workplace">
                        សុំប្រឡងចេញថ្នាក់ជាតិផ្នែកទ្រឹស្តីថ្នាក់
                        <input class="receipt-line w-22" type="text" wire:model.live="{{ $statePath }}.major_applied">
                    </div>

                    <div class="receipt-row">
                        សម្រាប់ការប្រឡងចេញថ្នាក់ជាតិសម័យប្រឡងថ្ងៃទី២៣-២៤ ខែឧសភា ឆ្នាំ២០២៦។
                    </div>

                    <div class="receipt-row">
                        លេខទូរស័ព្ទបេក្ខជនសម្រាប់ទំនាក់ទំនង៖
                        <input class="receipt-line w-75" type="text" wire:model.live="{{ $statePath }}.phone">
                    </div>

                    <div class="receipt-row">
                        និស្សិតត្រូវបង់ថវិកាចំនួន ១០០ ០០០ (ដប់ម៉ឺន)រៀល
                        ដើម្បីចូលរួមចំណែកក្នុងការរៀបចំការប្រឡងចេញថ្នាក់ជាតិ។
                    </div>
                </div>

                <div class="receipt-signatures">
                    <div>
                        ថ្ងៃ .......... ខែ .......... ឆ្នាំម្សាញ់ សប្តស័ក ព.ស.២៥៦៩<br>
                        រាជធានីភ្នំពេញ ថ្ងៃទី
                        <input class="receipt-line w-15" type="text" wire:model.live="{{ $statePath }}.extra_data.receiver_day">
                        ខែ
                        <input class="receipt-line w-15" type="text" wire:model.live="{{ $statePath }}.extra_data.receiver_month">
                        ឆ្នាំ២០២៦<br>
                        ហត្ថលេខា និងឈ្មោះអ្នកទទួល
                        <div class="sign-space"></div>
                    </div>

                    <div>
                        ថ្ងៃ .......... ខែ .......... ឆ្នាំម្សាញ់ សប្តស័ក ព.ស.២៥៦៩<br>
                        រាជធានីភ្នំពេញ ថ្ងៃទី
                        <input class="receipt-line w-15" type="text" wire:model.live="{{ $statePath }}.extra_data.student_day">
                        ខែ
                        <input class="receipt-line w-15" type="text" wire:model.live="{{ $statePath }}.extra_data.student_month">
                        ឆ្នាំ២០២៦<br>
                        ហត្ថលេខា និងឈ្មោះសាមីខ្លួន
                        <div class="sign-space"></div>
                    </div>
                </div>
            </div>

            @if ($copy === 1)
                <div class="cut-line"></div>
            @endif
        @endforeach

        <div class="page-number">1</div>
    </div>
</div>
