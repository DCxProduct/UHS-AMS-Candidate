<?php

return [
    'navigation_label' => 'Workflow Notifications',
    'model_label' => 'Workflow Notification',
    'plural_model_label' => 'Workflow Notifications',

    'custom_form_column' => 'Workflow Notification',
    'custom_form_helper' => 'Optional. The workflow this form follows.',
    'not_assigned' => 'Not assigned',

    'sections' => [
        'overview' => 'Workflow Overview',
        'overview_description' => 'Define the reusable workflow used by forms.',
        'stages' => 'Workflow Stages',
        'stages_description' => 'Add the stages in order. Drag stages to rearrange them.',
    ],

    'fields' => [
        'name' => 'Template Name',
        'assigned_forms' => 'Assigned Forms',
        'steps' => 'Steps',
        'updated_at' => 'Updated At',
        'stage_name' => 'Stage Name',
        'stage_type' => 'Stage Type',
        'responsible_role' => 'Responsible Role',
        'status_message' => 'Candidate Status Message (Optional)',
        'notification_message' => 'Candidate Notification Message (Optional)',
    ],

    'placeholders' => [
        'name' => 'Example: Candidate Admission',
        'stage_name' => 'Example: Review application',
        'automatic_role' => 'Automatic',
        'status_message' => 'Example: Under review',
        'notification_message' => 'Example: Your documents are being checked.',
    ],

    'helpers' => [
        'assigned_forms' => 'Forms that follow this workflow. A form can belong to only one template.',
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
        'approval' => 'Approval',
    ],

    'actions' => [
        'add_stage' => 'Add Stage',
        'close' => 'Close',
    ],

    'validation' => [
        'stages_required' => 'Add at least one stage.',
    ],
];
