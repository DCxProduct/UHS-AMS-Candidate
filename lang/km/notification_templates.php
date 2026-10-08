<?php

return [
    'navigation_label' => 'ទម្រង់សារជូនដំណឹង',
    'resource_label' => 'ទម្រង់សារជូនដំណឹង',
    'resource_plural_label' => 'ទម្រង់សារជូនដំណឹង',

    'sections' => [
        'general_info' => 'ការកំណត់ទម្រង់សារ',
        'general_info_description' => 'កំណត់កូដសម្គាល់ ប្រភេទសារ និងបណ្តាញផ្ញើសារជូនដំណឹង។',
        'variables_info' => 'អថេរស្វ័យប្រវត្តិដែលអាចប្រើប្រាស់បាន',
        'variables_info_description' => 'លោកអ្នកអាចប្រើប្រាស់ពាក្យគន្លឹះខាងក្រោមក្នុងកម្មវត្ថុ និងខ្លឹមសារ ដើម្បីបញ្ចូលទិន្នន័យជាក់ស្តែងរបស់បេក្ខជន។',
        'content_en' => 'ខ្លឹមសារជាភាសាអង់គ្លេស',
        'content_km' => 'ខ្លឹមសារជាភាសាខ្មែរ',
    ],

    'fields' => [
        'name' => 'ឈ្មោះទម្រង់សារ',
        'code' => 'កូដសម្គាល់',
        'type' => 'ប្រភេទសារ',
        'channels' => 'បណ្តាញផ្ញើសារ',
        'is_active' => 'ស្ថានភាពដំណើរការ',
        'subject_en' => 'កម្មវត្ថុ (អង់គ្លេស)',
        'body_en' => 'ខ្លឹមសារសារ (អង់គ្លេស)',
        'subject_km' => 'កម្មវត្ថុ (ខ្មែរ)',
        'body_km' => 'ខ្លឹមសារសារ (ខ្មែរ)',
    ],

    'placeholders' => [
        'name' => 'ឧ. ពាក្យស្នើសុំត្រូវបានអនុម័ត',
        'code' => 'ឧ. STAGE_APPROVED',
        'subject_en' => 'e.g. Your application has been approved',
        'body_en' => 'Dear {candidate_name}, your application for {stage_name} has been approved.',
        'subject_km' => 'ឧ. ពាក្យស្នើសុំរបស់អ្នកត្រូវបានអនុម័ត',
        'body_km' => 'សូមជម្រាបជូន {candidate_name} ពាក្យស្នើសុំរបស់អ្នកសម្រាប់ {stage_name} ត្រូវបានអនុម័ត។',
    ],

    'helpers' => [
        'code' => 'កូដអក្សរធំសម្រាប់ប្រព័ន្ធកំណត់ចំណាំក្នុងការផ្ញើសារស្វ័យប្រវត្តិ។',
    ],

    'types' => [
        'announcement' => 'សេចក្តីជូនដំណឹងទូទៅ',
        'request' => 'តម្រូវឱ្យចាត់វិធានការ',
    ],

    'channels' => [
        'database' => 'ក្នុងប្រព័ន្ធ (កណ្តឹង)',
        'mail' => 'អ៊ីមែល (SMTP)',
    ],

    'variables' => [
        'candidate_name' => 'ឈ្មោះបេក្ខជន',
        'stage_name' => 'ឈ្មោះដំណាក់កាល ឬការប្រឡង',
        'notes' => 'កំណត់ចំណាំអ្នកត្រួតពិនិត្យ ឬមូលហេតុ',
        'form_name' => 'ឈ្មោះទម្រង់ពាក្យស្នើសុំ',
    ],

    'table' => [
        'code' => 'កូដសម្គាល់',
        'name' => 'ឈ្មោះ',
        'type' => 'ប្រភេទ',
        'channels' => 'បណ្តាញ',
        'is_active' => 'ដំណើរការ',
        'updated_at' => 'កែប្រែចុងក្រោយ',
    ],

    'actions' => [
        'test_send' => 'សាកល្បងផ្ញើសារ',
        'submit_test' => 'ផ្ញើសារសាកល្បងឥឡូវនេះ',
    ],

    'modals' => [
        'test_send_heading' => 'សាកល្បងផ្ញើសារ៖ :name',
        'test_send_description' => 'ផ្ញើសារសាកល្បងដើម្បីផ្ទៀងផ្ទាត់ទម្រង់អ៊ីមែល និងការបង្ហាញកណ្តឹងជូនដំណឹងក្នុងប្រព័ន្ធ។',
    ],

    'notifications' => [
        'test_sent_title' => 'បានផ្ញើសារសាកល្បងរួចរាល់',
        'test_sent_body' => 'សារជូនដំណឹងត្រូវបានបញ្ជូនទៅកាន់ :email តាមរយៈ :channels។',
    ],
];
