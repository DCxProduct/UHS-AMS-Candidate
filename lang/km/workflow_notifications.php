<?php

return [
    'navigation_label' => 'លំហូរការជូនដំណឹង',
    'model_label' => 'លំហូរការជូនដំណឹង',
    'plural_model_label' => 'លំហូរការជូនដំណឹង',

    'custom_form_column' => 'លំហូរការជូនដំណឹង',
    'custom_form_helper' => 'ស្រេចចិត្ត។ ដំណើរការដែលទម្រង់បែបបទនេះអនុវត្តតាម។',
    'candidate_notification_title' => ':stage',
    'candidate_action_notification_title' => ':form៖ :action',
    'staff_notification_title' => ':form៖ :stage',
    'staff_notification_body' => ':student បានមកដល់ដំណាក់កាល :stage នៃ :form។',
    'not_assigned' => 'មិនទាន់កំណត់',

    'sections' => [
        'overview' => 'ទិដ្ឋភាពទូទៅនៃដំណើរការ',
        'overview_description' => 'កំណត់ដំណើរការដែលអាចប្រើឡើងវិញសម្រាប់ទម្រង់បែបបទ។',
        'stages' => 'ដំណាក់កាលនៃដំណើរការ',
        'stages_description' => 'បន្ថែមដំណាក់កាលតាមលំដាប់។ អូសដំណាក់កាលដើម្បីរៀបចំលំដាប់ឡើងវិញ។',
        'for_candidates' => 'សម្រាប់បេក្ខជន',
        'for_system_users' => 'សម្រាប់អ្នកប្រើប្រាស់ប្រព័ន្ធ',
        'review_outcomes' => 'សកម្មភាពពិនិត្យពាក្យស្នើសុំ',
    ],

    'channels' => [
        'system' => 'ប្រព័ន្ធ (កណ្ដឹង)',
        'sms' => 'SMS',
        'email' => 'អ៊ីមែល',
    ],

    'fields' => [
        'email_template' => 'គំរូអ៊ីមែល',
        'sms_template' => 'គំរូ SMS',
        'notification_channels' => 'ផ្ញើការជូនដំណឹងតាម',
        'name' => 'ឈ្មោះគំរូ',
        'assigned_forms' => 'ទម្រង់ដែលបានភ្ជាប់',
        'steps' => 'ជំហាន',
        'updated_at' => 'កាលបរិច្ឆេទកែប្រែ',
        'stage_name' => 'ឈ្មោះដំណាក់កាល',
        'stage_type' => 'ប្រភេទដំណាក់កាល',
        'payment_source' => 'ប្រភពនៃការបង់ប្រាក់',
        'responsible_role' => 'តួនាទីទទួលខុសត្រូវ',
        'status_message' => 'សារស្ថានភាពសម្រាប់បេក្ខជន (ជាជម្រើស)',
        'shared_status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
        'alert_message' => 'សារ Alert (ជាជម្រើស)',
        'pending_alert_message' => 'សារ Alert ពេលកំពុងរង់ចាំ (ជាជម្រើស)',
        'unpaid_status_message' => 'សារស្ថានភាពបន្ទាប់នៅក្នុងមុឺនុយ បញ្ជីស្ថានភាពបង់ប្រាក់ (ជាជម្រើស)',
        'paid_status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
        'waiting_result_status_message' => 'សារស្ថានភាពបន្ទាប់នៅក្នុងមុឺនុយស្ថិតិបេក្ខជន (ជាជម្រើស)',
        'passed_result_status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
        'notification_message' => 'សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)',
        'staff_notification_message' => 'សារជូនដំណឹងសម្រាប់អ្នកប្រើប្រាស់ប្រព័ន្ធ (ជាជម្រើស)',
        'staff_recipient_ids' => 'ផ្ញើការជូនដំណឹងទៅ',
        'staff_recipient_roles' => 'ផ្ញើការជូនដំណឹងទៅ',
    ],

    'payment_sources' => [
        'system' => 'ប្រព័ន្ធនេះ',
        'ams' => 'ប្រព័ន្ធ AMS',
    ],

    'placeholders' => [
        'plain_email' => 'អ៊ីមែលធម្មតា (សារនៃដំណាក់កាលប៉ុណ្ណោះ)',
        'plain_sms' => 'SMS ធម្មតា (សារនៃដំណាក់កាលប៉ុណ្ណោះ)',
        'name' => 'ឧទាហរណ៍៖ ការចុះឈ្មោះបេក្ខជន',
        'stage_name' => 'ឧទាហរណ៍៖ ពិនិត្យពាក្យស្នើសុំ',
        'automatic_role' => 'ស្វ័យប្រវត្តិ',
        'status_message' => 'ឧទាហរណ៍៖ កំពុងពិនិត្យ',
        'alert_message' => 'ឧទាហរណ៍៖ បានអនុវត្តដោយជោគជ័យ។',
        'pending_alert_message' => 'ឧទាហរណ៍៖ បានកំណត់លទ្ធផលឱ្យកំពុងរង់ចាំ។',
        'notification_message' => 'ឧទាហរណ៍៖ ឯកសាររបស់អ្នកកំពុងត្រូវបានពិនិត្យ។',
        'submission_status' => 'ឧទាហរណ៍៖ បានដាក់ពាក្យស្នើសុំ និងកំពុងរង់ចាំការពិនិត្យ',
        'submission_candidate_notification' => 'ឧទាហរណ៍៖ ពាក្យស្នើសុំរបស់អ្នកត្រូវបានដាក់ស្នើដោយជោគជ័យ។',
        'submission_staff_notification' => 'ឧទាហរណ៍៖ :candidate បានដាក់ពាក្យ :form។',
        'staff_recipient_ids' => 'ជ្រើសរើសអ្នកប្រើប្រាស់ប្រព័ន្ធ',
        'staff_recipient_roles' => 'ជ្រើសរើសតួនាទី',
        'unpaid_status_message' => 'ឧទាហរណ៍៖ មិនទាន់បង់ប្រាក់',
        'waiting_result_status_message' => 'ឧទាហរណ៍៖ កំពុងរង់ចាំ',
        'stage_audiences' => [
            'payment' => [
                'status_message' => 'ឧទាហរណ៍៖ បានបង់ប្រាក់រួចរាល់',
                'notification_message' => 'ឧទាហរណ៍៖ ព័ត៌មានបង់ប្រាក់របស់អ្នកត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ :candidate បានបង់ប្រាក់សម្រាប់ :form រួចរាល់។',
            ],
            'awaiting_results' => [
                'status_message' => 'ឧទាហរណ៍៖ បានជាប់',
                'notification_message' => 'ឧទាហរណ៍៖ សូមពិនិត្យលទ្ធផលពាក្យស្នើសុំរបស់អ្នក។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ លទ្ធផល :form របស់ :candidate ត្រូវបានធ្វើបច្ចុប្បន្នភាព។',
            ],
            'completed' => [
                'status_message' => 'ឧទាហរណ៍៖ បានបញ្ចប់',
                'notification_message' => 'ឧទាហរណ៍៖ ដំណើរការពាក្យស្នើសុំរបស់អ្នកបានបញ្ចប់។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ ដំណើរការ :form របស់ :candidate បានបញ្ចប់។',
            ],
        ],
        'review_actions' => [
            'accept' => [
                'status_message' => 'ឧទាហរណ៍៖ បានពិនិត្យ',
                'notification_message' => 'ឧទាហរណ៍៖ ពាក្យស្នើសុំរបស់អ្នកត្រូវបានពិនិត្យរួចរាល់។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ ពាក្យ :form របស់ :candidate ត្រូវបានពិនិត្យរួចរាល់។',
            ],
            'send_back' => [
                'status_message' => 'ឧទាហរណ៍៖ បញ្ជូនត្រឡប់ដើម្បីកែតម្រូវ',
                'notification_message' => 'ឧទាហរណ៍៖ សូមកែតម្រូវពាក្យស្នើសុំរបស់អ្នក ហើយដាក់ស្នើម្ដងទៀត។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ ពាក្យ :form របស់ :candidate ត្រូវបានបញ្ជូនត្រឡប់ដើម្បីកែតម្រូវ។',
            ],
            'reject' => [
                'status_message' => 'ឧទាហរណ៍៖ បានបដិសេធ',
                'notification_message' => 'ឧទាហរណ៍៖ ពាក្យស្នើសុំរបស់អ្នកត្រូវបានបដិសេធ។',
                'staff_notification_message' => 'ឧទាហរណ៍៖ ពាក្យ :form របស់ :candidate ត្រូវបានបដិសេធ។',
            ],
        ],
    ],

    'review_actions' => [
        'accept' => [
            'title' => 'បានពិនិត្យ',
            'status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
            'notification_message' => 'សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)',
        ],
        'send_back' => [
            'title' => 'បញ្ជូនត្រឡប់',
            'status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
            'notification_message' => 'សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)',
        ],
        'reject' => [
            'title' => 'បដិសេធ',
            'status_message' => 'សារស្ថានភាព (ជាជម្រើស)',
            'notification_message' => 'សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)',
        ],
    ],

    'helpers' => [
        'staff_message_placeholders' => ':candidate = ឈ្មោះបេក្ខជន; :form = ឈ្មោះទម្រង់ពាក្យស្នើសុំ។ ឧទាហរណ៍៖ :candidate បានដាក់ស្នើទម្រង់ :form។',
        'assigned_forms' => 'ទម្រង់ដែលប្រើដំណើរការនេះ។ ទម្រង់មួយអាចភ្ជាប់ជាមួយគំរូតែមួយប៉ុណ្ណោះ។',
        'passed_result_status_message' => 'បង្ហាញក្នុងតារាងលទ្ធផលប្រឡង ក្រោយកំណត់លទ្ធផលថាបានជាប់។ ទុកទទេដើម្បីប្រើស្ថានភាពបានជាប់លំនាំដើម។',
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
        'review' => 'ការពិនិត្យពាក្យស្នើសុំ',
        'payment' => 'ការបង់ប្រាក់',
        'awaiting_results' => 'រង់ចាំលទ្ធផល',
        'completed' => 'រង់ចាំសេចក្ដីជូនដំណឹងបេក្ខជនប្រឡងជាប់ជ័យលាភី',
        'rejected' => 'បានបដិសេធ',
        'approval' => 'ការអនុម័ត',
    ],

    'actions' => [
        'add_stage' => 'បន្ថែមដំណាក់កាល',
        'close' => 'បិទ',
    ],

    'validation' => [
        'ams_payment_only' => 'ពាក្យស្នើសុំនេះត្រូវបង់ប្រាក់ក្នុងប្រព័ន្ធ AMS។',
        'stages_required' => 'សូមបន្ថែមដំណាក់កាលយ៉ាងហោចណាស់មួយ។',
        'duplicate_stage_type' => 'ប្រភេទដំណាក់កាលនីមួយៗអាចបន្ថែមបានតែម្តងប៉ុណ្ណោះ។ ដំណាក់កាល «:stage» មានស្ទួន។',
        'invalid_stage_type' => 'សូមជ្រើសរើសប្រភេទដំណាក់កាលដែលត្រឹមត្រូវសម្រាប់គ្រប់ដំណាក់កាល។',
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
