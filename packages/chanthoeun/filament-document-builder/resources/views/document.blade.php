<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Document</title>

    <style>
        @font-face {
            font-family: 'Khmer Battambang';
            src: url('{{ realpath(__DIR__ . "/../../resources/fonts/KhmerOSbattambang.ttf") }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Khmer Moul Light';
            src: url('{{ realpath(__DIR__ . "/../../resources/fonts/KhmerOSmuollight.ttf") }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        @font-face {
            font-family: 'Khmer Siemreap';
            src: url('{{ realpath(__DIR__ . "/../../resources/fonts/KhmerOSsiemreap.ttf") }}') format('truetype');
            font-weight: normal;
            font-style: normal;
        }

        body {
            font-family: "Khmer Battambang", "Khmer OS Battambang", "Times New Roman", sans-serif;
            margin: 0;
            padding: 0;
            color: #000;
            font-size: 14px;
            line-height: 1.5;
        }

        .document-container {
            width: 100%;
            font-family: "Khmer Battambang", "Khmer OS Battambang", "Times New Roman", sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        /* Force editor font names to PDF font names */
        [style*="Khmer Moul Light"],
        [style*="Khmer OS Muol Light"],
        [style*="KhmerOSmuollight"],
        .khmer-moul {
            font-family: "Khmer Moul Light" !important;
        }

        [style*="Khmer Battambang"],
        [style*="Khmer OS Battambang"],
        [style*="KhmerOSbattambang"],
        .khmer-body {
            font-family: "Khmer Battambang" !important;
        }

        [style*="Khmer Siemreap"],
        [style*="Khmer OS Siemreap"],
        [style*="KhmerOSsiemreap"] {
            font-family: "Khmer Siemreap" !important;
        }

        [style*="Times New Roman"] {
            font-family: "Times New Roman" !important;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-xl { font-size: 1.25rem; }
        .text-2xl { font-size: 1.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        .w-full { width: 100%; }

        table {
            width: 100%;
            border-spacing: 0;
        }

        table[border="1"],
        table[style*="collapse"] {
            border-collapse: collapse;
        }

        th, td {
            padding: 5px;
            vertical-align: top;
        }

        [style*="border-radius"] {
            border-collapse: separate !important;
        }

        img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>

<body>
<div class="document-container">
    {!! $htmlContent !!}
</div>
</body>
</html>
