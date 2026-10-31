<?php

return [
    'navigation_label' => 'Workflow Notifications',
    'model_label' => 'Workflow Notification',
    'plural_model_label' => 'Workflow Notifications',

    'custom_form_column' => 'Workflow Notification',
    'custom_form_helper' => 'Optional. The workflow this form follows.',
    'candidate_notification_title' => ':stage',
    'candidate_action_notification_title' => ':form: :action',
    'staff_notification_title' => ':form: :stage',
    'staff_notification_body' => ':student has reached the :stage stage for :form.',
    'not_assigned' => 'Not assigned',

    'sections' => [
        'overview' => 'Workflow Overview',
        'overview_description' => 'Define the reusable workflow used by forms.',
        'stages' => 'Workflow Stages',
        'stages_description' => 'Add the stages in order. Drag stages to rearrange them.',
        'for_candidates' => 'For Candidates',
        'for_system_users' => 'For System Users',
        'review_outcomes' => 'Review Actions',
    ],

    'channels' => [
        'system' => 'System (bell)',
        'sms' => 'SMS',
        'email' => 'Email',
    ],

    'fields' => [
        'email_template' => 'Email template',
        'sms_template' => 'SMS template',
        'notification_channels' => 'Send notification by',
        'name' => 'Template Name',
        'assigned_forms' => 'Assigned Forms',
        'steps' => 'Steps',
        'updated_at' => 'Updated At',
        'stage_name' => 'Stage Name',
        'stage_type' => 'Stage Type',
        'payment_source' => 'Payment Source',
        'responsible_role' => 'Responsible Role',
        'status_message' => 'Candidate Status Message (Optional)',
        'shared_status_message' => 'Status Message (Optional)',
        'alert_message' => 'Alert Message (Optional)',
        'pending_alert_message' => 'Pending Alert Message (Optional)',
        'unpaid_status_message' => 'Next Status Message in Payment Status List (Optional)',
        'paid_status_message' => 'Status Message (Optional)',
        'waiting_result_status_message' => 'Next Status Message in Candidate Statistics (Optional)',
        'passed_result_status_message' => 'Status Message (Optional)',
        'notification_message' => 'Candidate Notification Message (Optional)',
        'staff_notification_message' => 'System User Notification Message (Optional)',
        'staff_recipient_ids' => 'Send Notification To',
        'staff_recipient_roles' => 'Send Notification To',
    ],

    'payment_sources' => [
        'system' => 'This system',
        'ams' => 'AMS system',
    ],

    'placeholders' => [
        'plain_email' => 'Plain email (stage message only)',
        'plain_sms' => 'Plain SMS (stage message only)',
        'name' => 'Example: Candidate Admission',
        'stage_name' => 'Example: Review application',
        'automatic_role' => 'Automatic',
        'status_message' => 'Example: Under review',
        'alert_message' => 'Example: Action completed successfully.',
        'pending_alert_message' => 'Example: Result set to pending.',
        'notification_message' => 'Example: Your documents are being checked.',
        'submission_status' => 'Example: Application submitted and awaiting review',
        'submission_candidate_notification' => 'Example: Your application has been submitted successfully.',
        'submission_staff_notification' => 'Example: :candidate submitted :form.',
        'staff_recipient_ids' => 'Select system users',
        'staff_recipient_roles' => 'Select roles',
        'unpaid_status_message' => 'Example: Unpaid',
        'waiting_result_status_message' => 'Example: Pending',
        'stage_audiences' => [
            'payment' => [
                'status_message' => 'Example: Paid successfully',
                'notification_message' => 'Example: Your payment information has been updated.',
                'staff_notification_message' => 'Example: :candidate completed payment for :form.',
            ],
            'awaiting_results' => [
                'status_message' => 'Example: Passed',
                'notification_message' => 'Example: Please check your application results.',
                'staff_notification_message' => 'Example: Results for :candidate in :form have been updated.',
            ],
            'completed' => [
                'status_message' => 'Example: Completed',
                'notification_message' => 'Example: Your application process is complete.',
                'staff_notification_message' => 'Example: :candidate completed :form.',
            ],
        ],
        'review_actions' => [
            'accept' => [
                'status_message' => 'Example: Checked',
                'notification_message' => 'Example: Your application has been checked.',
                'staff_notification_message' => 'Example: The :form application from :candidate has been checked.',
            ],
            'send_back' => [
                'status_message' => 'Example: Returned for correction',
                'notification_message' => 'Example: Please correct your application and submit it again.',
                'staff_notification_message' => 'Example: The :form application from :candidate was returned for correction.',
            ],
            'reject' => [
                'status_message' => 'Example: Rejected',
                'notification_message' => 'Example: Your application has been rejected.',
                'staff_notification_message' => 'Example: The :form application from :candidate has been rejected.',
            ],
        ],
    ],

    'review_actions' => [
        'accept' => [
            'title' => 'Checked',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
        'send_back' => [
            'title' => 'Send Back',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
        'reject' => [
            'title' => 'Reject',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
    ],

    'helpers' => [
        'staff_message_placeholders' => ':candidate = candidate name; :form = application form name. Example: :candidate submitted :form.',
        'assigned_forms' => 'Forms that follow this workflow. A form can belong to only one template.',
        'passed_result_status_message' => 'Shown in the exam result table after the candidate is marked Passed. Leave blank to use the default Passed label.',
    ],

    'view' => [
        'stage_count' => ':count stages',
        'responsible' => 'Responsible: :name',
        'candidate' => 'Candidate',
        'automatic' => 'Automatic',
        'no_stages' => 'No stages configured.',
    ],

    'stage_types' => [
        'form_submission' => 'Form Submission',
        'review' => 'Review',
        'payment' => 'Payment',
        'awaiting_results' => 'Awaiting Results',
        'completed' => 'Awaiting Successful Candidate Notification',
        'rejected' => 'Rejected',
        'approval' => 'Approval',
    ],

    'actions' => [
        'add_stage' => 'Add Stage',
        'close' => 'Close',
    ],

    'validation' => [
        'ams_payment_only' => 'This application must be paid in AMS.',
        'stages_required' => 'Add at least one stage.',
        'duplicate_stage_type' => 'Each stage type can be added only once. The ":stage" stage is duplicated.',
        'invalid_stage_type' => 'Select a valid stage type for every stage.',
        'name_already_exists' => 'A workflow with this name already exists.',
        'could_not_save' => 'The workflow could not be saved.',
    ],

    'status_defaults' => [
        'review' => 'Under review',
        'payment' => 'Waiting for payment',
        'awaiting_results' => 'Waiting for results',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'approval' => 'Waiting for approval',
    ],
];
