<?php

return [
    'navigation_label' => 'MEF & Invoice Code',
    'resource_label' => 'MEF & Invoice Code',
    'resource_plural_label' => 'MEF & Invoice Codes',
    'search' => 'Search MEF or invoice codes',
    'form' => [
        'section_title' => 'Code Information',
        'mef_numbers_only' => 'Enter numbers only',
    ],
    'fields' => [
        'mef_code' => 'MEF Code',
        'invoice_code' => 'Invoice Code',
    ],
    'validation' => [
        'mef_code_numbers_only' => 'MEF Code can contain numbers only (0–9).',
        'invoice_code_symbols_only' => 'Invoice Code can contain numbers and symbols only.',
    ],
    'table' => [
        'no' => 'No',
        'created_at' => 'Created At',
        'updated_at' => 'Updated At',
    ],
    'actions' => [
        'create' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
        'import_excel' => 'Import Excel',
        'export_excel' => 'Export Excel',
    ],
    'import' => [
        'file_label' => 'Excel file (.xlsx) or CSV',
        'modal_title' => 'Upload MEF and Invoice Codes',
        'completed_title' => 'Import completed',
        'completed_body' => ':created new records imported; :existing duplicate rows skipped.',
        'errors' => [
            'file_type' => 'Upload an .xlsx or .csv file.',
            'cannot_read' => 'The uploaded file could not be read.',
            'invalid_excel' => 'The Excel file is invalid or cannot be read.',
            'headers' => 'The first row must contain the columns: no, MEF Code, Invoice Code.',
            'no_rows' => 'No code records were found in the file.',
            'incomplete_row' => 'Both MEF Code and Invoice Code are required on row :row.',
            'invalid_invoice_code' => 'Invoice Code on row :row must contain numbers and symbols only.',
        ],
    ],
];
