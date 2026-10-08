<?php

return [
    'navigation_label' => 'លំហូរការជូនដំណឹង',
    'model_label' => 'លំហូរការជូនដំណឹង',
    'plural_model_label' => 'លំហូរការជូនដំណឹង',

    'custom_form_column' => 'លំហូរការជូនដំណឹង',
    'custom_form_helper' => 'ស្រេចចិត្ត។ ដំណើរការដែលទម្រង់បែបបទនេះអនុវត្តតាម។',
    'candidate_notification_title' => ':form៖ :stage',
    'not_assigned' => 'មិនទាន់កំណត់',

    'sections' => [
        'overview' => 'ទិដ្ឋភាពទូទៅនៃដំណើរការ',
        'overview_description' => 'កំណត់ដំណើរការដែលអាចប្រើឡើងវិញសម្រាប់ទម្រង់បែបបទ។',
        'stages' => 'ដំណាក់កាលនៃដំណើរការ',
        'stages_description' => 'បន្ថែមដំណាក់កាលតាមលំដាប់។ អូសដំណាក់កាលដើម្បីរៀបចំលំដាប់ឡើងវិញ។',
    ],

    'fields' => [
        'name' => 'ឈ្មោះគំរូ',
        'assigned_forms' => 'ទម្រង់ដែលបានភ្ជាប់',
        'steps' => 'ជំហាន',
        'updated_at' => 'កាលបរិច្ឆេទកែប្រែ',
        'stage_name' => 'ឈ្មោះដំណាក់កាល',
        'stage_type' => 'ប្រភេទដំណាក់កាល',
        'responsible_role' => 'តួនាទីទទួលខុសត្រូវ',
        'status_message' => 'សារស្ថានភាពសម្រាប់បេក្ខជន (ជាជម្រើស)',
        'notification_message' => 'សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)',
    ],

    'placeholders' => [
        'name' => 'ឧទាហរណ៍៖ ការចុះឈ្មោះបេក្ខជន',
        'stage_name' => 'ឧទាហរណ៍៖ ពិនិត្យពាក្យស្នើសុំ',
        'automatic_role' => 'ស្វ័យប្រវត្តិ',
        'status_message' => 'ឧទាហរណ៍៖ កំពុងពិនិត្យ',
        'notification_message' => 'ឧទាហរណ៍៖ ឯកសាររបស់អ្នកកំពុងត្រូវបានពិនិត្យ។',
    ],

    'helpers' => [
        'assigned_forms' => 'ទម្រង់ដែលប្រើដំណើរការនេះ។ ទម្រង់មួយអាចភ្ជាប់ជាមួយគំរូតែមួយប៉ុណ្ណោះ។',
    ],

    'view' => [
        'stage_count' => ':count ដំណាក់កាល',
        'responsible' => 'អ្នកទទួលខុសត្រូវ៖ :name',
        'candidate' => 'បេក្ខជន',
        'automatic' => 'ស្វ័យប្រវត្តិ',
        'no_stages' => 'មិនទាន់មានដំណាក់កាលទេ។',
    ],

    'stage_types' => [
        'form_submission' => 'ការដាក់ស្នើទម្រង់',
        'review' => 'ការពិនិត្យ',
        'payment' => 'ការបង់ប្រាក់',
        'awaiting_results' => 'រង់ចាំលទ្ធផល',
        'completed' => 'បញ្ចប់',
        'rejected' => 'បានបដិសេធ',
        'approval' => 'ការអនុម័ត',
    ],

    'actions' => [
        'add_stage' => 'បន្ថែមដំណាក់កាល',
        'close' => 'បិទ',
    ],

    'validation' => [
        'stages_required' => 'សូមបន្ថែមដំណាក់កាលយ៉ាងហោចណាស់មួយ។',
        'name_already_exists' => 'ឈ្មោះដំណើរការនេះមានរួចហើយ។',
        'could_not_save' => 'មិនអាចរក្សាទុកដំណើរការបានទេ។',
    ],

    'status_defaults' => [
        'review' => 'កំពុងពិនិត្យ',
        'payment' => 'រង់ចាំការបង់ប្រាក់',
        'awaiting_results' => 'រង់ចាំលទ្ធផល',
        'completed' => 'បានបញ្ចប់',
        'rejected' => 'បានបដិសេធ',
        'approval' => 'រង់ចាំការអនុម័ត',
    ],
];
