<?php

return [
    'title' => 'Backup Data',

    'sections' => [
        'export' => 'Database Export',
        'export_description' => 'Select one or more database tables and download them in an Excel-compatible backup file.',
    ],

    'fields' => [
        'tables' => 'Tables',
    ],

    'placeholders' => [
        'tables' => 'Select tables',
    ],

    'actions' => [
        'export' => 'Export Excel',
        'erase' => 'Erase Data',
        'select_all' => 'Select All Tables',
        'clear' => 'Clear',
    ],

    'confirmations' => [
        'erase' => 'Erase all data from the selected tables? This keeps the table structure but deletes the rows.',
    ],

    'helpers' => [
        'tables' => ':selected of :total tables selected',
    ],

    'stats' => [
        'total_tables' => 'Total Tables',
        'selected_tables' => 'Selected Tables',
        'file_type' => 'File Type',
    ],

    'validation' => [
        'tables_required' => 'Please select at least one table.',
    ],

    'notifications' => [
        'no_tables' => 'Please select at least one table before exporting.',
        'erased' => 'Selected table data erased successfully.',
        'erase_failed' => 'Failed to erase the selected table data.',
        'zip_extension_missing' => 'ZIP extension is required to export XLSX backups.',
    ],
];
