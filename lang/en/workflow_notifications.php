<?php

return [
    'navigation_label' => 'Workflow Notifications',
    'model_label' => 'Workflow Notification',
    'plural_model_label' => 'Workflow Notifications',

    'custom_form_column' => 'Workflow Notification',
    'custom_form_helper' => 'Optional. The workflow this form follows.',
    'candidate_notification_title' => ':stage',
    'candidate_action_notification_title' => ':form: :action',
    'staff_notification_title' => 'Workflow action: :form: :stage',
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
        'unpaid_status_message' => 'Unpaid Status Message (Optional)',
        'paid_status_message' => 'Paid Status Message (Optional)',
        'waiting_result_status_message' => 'Pending Result Status Message (Optional)',
        'passed_result_status_message' => 'Passed Result Status Message (Optional)',
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
        'notification_message' => 'Example: Your documents are being checked.',
        'submission_status' => 'Example: Application submitted and awaiting review',
        'submission_candidate_notification' => 'Example: Your application has been submitted successfully.',
        'submission_staff_notification' => 'Example: A new application is ready for review.',
        'staff_recipient_ids' => 'Select system users',
        'staff_recipient_roles' => 'Select roles',
        'unpaid_status_message' => 'Example: Unpaid',
        'waiting_result_status_message' => 'Example: Pending',
        'stage_audiences' => [
            'payment' => [
                'status_message' => 'Example: Paid successfully',
                'notification_message' => 'Example: Your payment information has been updated.',
                'staff_notification_message' => 'Example: An application payment has been updated.',
            ],
            'awaiting_results' => [
                'status_message' => 'Example: Passed',
                'notification_message' => 'Example: Please check your application results.',
                'staff_notification_message' => 'Example: An application has a results update.',
            ],
            'completed' => [
                'status_message' => 'Example: Completed',
                'notification_message' => 'Example: Your application process is complete.',
                'staff_notification_message' => 'Example: An application process is complete.',
            ],
        ],
        'review_actions' => [
            'accept' => [
                'status_message' => 'Example: Checked',
                'notification_message' => 'Example: Your application has been checked.',
                'staff_notification_message' => 'Example: An application has been checked.',
            ],
            'send_back' => [
                'status_message' => 'Example: Returned for correction',
                'notification_message' => 'Example: Please correct your application and submit it again.',
                'staff_notification_message' => 'Example: An application was returned for correction.',
            ],
            'reject' => [
                'status_message' => 'Example: Rejected',
                'notification_message' => 'Example: Your application has been rejected.',
                'staff_notification_message' => 'Example: An application has been rejected.',
            ],
        ],
    ],

    'review_actions' => [
        'accept' => [
            'title' => 'Checked',
            'description' => 'For the Checked action.',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
        'send_back' => [
            'title' => 'Send Back',
            'description' => 'For returning an application for corrections.',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
        'reject' => [
            'title' => 'Reject',
            'description' => 'For rejecting an application.',
            'status_message' => 'Status Message (Optional)',
            'notification_message' => 'Candidate Notification Message (Optional)',
        ],
    ],

    'helpers' => [
        'payment_source' => 'Choose where payments are collected. For AMS, clicking Checked sends the application to AMS Other Fees, and AMS confirms the payment back to this system.',
        'notification_channels' => 'Where the candidate receives this stage\'s notification message. SMS goes to the candidate\'s phone through PlasGate; Email to their email address.',
        'assigned_forms' => 'Forms that follow this workflow. A form can belong to only one template.',
        'completed_responsible_role' => 'Select one or more roles. Leave empty to keep automatic handling.',
        'submission_status' => 'The same status is shown to candidates and system users after clicking Done. Leave blank to keep the existing status.',
        'submission_candidate_notification' => 'Sent to the candidate after submitting the application. Leave blank to keep the existing behavior.',
        'review_outcomes' => 'Each action has one shared status. Configure separate notification messages for the candidate and system users in the selected roles. Blank fields keep the existing behavior.',
        'shared_review_status' => 'The same status label is shown to candidates and system users.',
        'review_candidate_notification' => 'Sent only to the candidate through the selected notification channels.',
        'review_staff_notification' => 'Sent to the system bell of active system users in the selected roles when this action is used. Leave blank to send no additional staff notification.',
        'review_staff_recipients' => 'All active system users in the selected roles receive this message. Leave empty to send no staff notification.',
        'shared_stage_status' => 'The same workflow status is shown to candidates and system users. Leave blank to keep the existing status.',
        'unpaid_status_message' => 'Shown in Payment Status List. In application lists, used after review when the Checked status message is blank. Leave blank to keep the existing status labels.',
        'paid_status_message' => 'Shown to candidates and system users after payment, and in both payment lists. Leave blank to keep the existing status labels.',
        'waiting_result_status_message' => 'Shown in the exam result table after payment while the result is pending. Leave blank to use the default Pending label.',
        'passed_result_status_message' => 'Shown in the exam result table after the candidate is marked Passed. Leave blank to use the default Passed label.',
        'stage_candidate_notification' => 'Sent to the candidate through the selected channels when this stage notification is triggered. Leave blank to keep the existing behavior.',
        'stage_staff_notification' => 'Sent to the system bell of active system users in the selected roles when this stage notification is triggered. Leave blank to send no additional staff notification.',
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
        'completed' => 'Completed',
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
