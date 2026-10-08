<?php

return [
    'navigation_label' => 'Notification Templates',
    'resource_label' => 'Notification Template',
    'resource_plural_label' => 'Notification Templates',

    'sections' => [
        'general_info' => 'Template Configuration',
        'general_info_description' => 'Configure template identifiers, notification category, and active channels.',
        'variables_info' => 'Available Dynamic Variables',
        'variables_info_description' => 'You can use the following placeholders in your subject and body to inject dynamic candidate and workflow information.',
        'content_en' => 'English Content',
        'content_km' => 'Khmer Content (ភាសាខ្មែរ)',
    ],

    'fields' => [
        'name' => 'Template Name',
        'code' => 'Template Code',
        'type' => 'Category',
        'channels' => 'Delivery Channels',
        'is_active' => 'Active Status',
        'subject_en' => 'Subject (EN)',
        'body_en' => 'Message Content (EN)',
        'subject_km' => 'Subject (KM)',
        'body_km' => 'Message Content (KM)',
    ],

    'placeholders' => [
        'name' => 'e.g. Application Approved',
        'code' => 'e.g. STAGE_APPROVED',
        'subject_en' => 'e.g. Your application has been approved',
        'body_en' => 'Dear {candidate_name}, your application for {stage_name} has been approved.',
        'subject_km' => 'ឧ. ពាក្យស្នើសុំរបស់អ្នកត្រូវបានអនុម័ត',
        'body_km' => 'សូមជម្រាបជូន {candidate_name} ពាក្យស្នើសុំរបស់អ្នកសម្រាប់ {stage_name} ត្រូវបានអនុម័ត។',
    ],

    'helpers' => [
        'code' => 'Unique uppercase identifier used by workflows and system triggers.',
    ],

    'types' => [
        'announcement' => 'Announcement',
        'request' => 'Action Request',
    ],

    'channels' => [
        'database' => 'In-App Bell (Database)',
        'mail' => 'Email (SMTP)',
    ],

    'variables' => [
        'candidate_name' => 'Recipient candidate name',
        'stage_name' => 'Exam or form stage title',
        'notes' => 'Reviewer note or reason',
        'form_name' => 'Application form title',
    ],

    'table' => [
        'code' => 'Code',
        'name' => 'Name',
        'type' => 'Type',
        'channels' => 'Channels',
        'is_active' => 'Active',
        'updated_at' => 'Updated At',
    ],

    'actions' => [
        'test_send' => 'Test Send',
        'submit_test' => 'Send Test Now',
    ],

    'modals' => [
        'test_send_heading' => 'Test Notification: :name',
        'test_send_description' => 'Send a test notification to verify email formatting and in-app bell delivery.',
    ],

    'notifications' => [
        'test_sent_title' => 'Test Notification Dispatched',
        'test_sent_body' => 'Notification dispatched to :email via :channels.',
    ],
];
