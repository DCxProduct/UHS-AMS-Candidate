<?php

return [
    'navigation_label' => 'កូដក្រសួងនិងលេខវិក្កយបត្រ',
    'resource_label' => 'កូដក្រសួងនិងលេខវិក្កយបត្រ',
    'resource_plural_label' => 'កូដក្រសួងនិងលេខវិក្កយបត្រ',
    'search' => 'ស្វែងរកកូដក្រសួងឬលេខវិក្កយបត្រ',
    'form' => [
        'section_title' => 'ព័ត៌មានកូដ',
        'mef_code_placeholder' => 'ឧទាហរណ៍៖ MEF1',
    ],
    'fields' => [
        'mef_code' => 'កូដក្រសួង',
        'invoice_code' => 'លេខវិក្កយបត្រ',
    ],
    'validation' => [
        'invoice_code_symbols_only' => 'លេខកូដវិក្កយបត្រអាចបញ្ចូលតែលេខ និងនិមិត្តសញ្ញាប៉ុណ្ណោះ។',
    ],
    'table' => [
        'no' => 'ល.រ',
        'created_at' => 'កាលបរិច្ឆេទបង្កើត',
        'updated_at' => 'កាលបរិច្ឆេទកែប្រែ',
    ],
    'actions' => [
        'create' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
        'import_excel' => 'នាំចូល Excel',
        'export_excel' => 'នាំចេញ Excel',
    ],
    'import' => [
        'file_label' => 'ឯកសារ Excel (.xlsx) ឬ CSV',
        'modal_title' => 'បញ្ចូលកូដក្រសួងនិងលេខវិក្កយបត្រ',
        'completed_title' => 'បញ្ចូលទិន្នន័យរួចរាល់',
        'completed_body' => 'បានបញ្ចូលថ្មី :created កំណត់ត្រា និងរំលងជួរដែលស្ទួន :existing ជួរ។',
        'errors' => [
            'file_type' => 'សូមបញ្ចូលឯកសារ .xlsx ឬ .csv។',
            'cannot_read' => 'មិនអាចអានឯកសារដែលបានបញ្ចូលបានទេ។',
            'invalid_excel' => 'ឯកសារ Excel មិនត្រឹមត្រូវ ឬមិនអាចអានបាន។',
            'headers' => 'ជួរដំបូងត្រូវមានជួរឈរ៖ no, MEF Code និង Invoice Code។',
            'no_rows' => 'មិនមានទិន្នន័យកូដនៅក្នុងឯកសារទេ។',
            'incomplete_row' => 'ត្រូវបំពេញទាំង MEF Code និង Invoice Code នៅជួរទី :row។',
            'invalid_invoice_code' => 'Invoice Code នៅជួរទី :row អាចមានតែលេខ និងនិមិត្តសញ្ញាប៉ុណ្ណោះ។',
        ],
    ],
];
