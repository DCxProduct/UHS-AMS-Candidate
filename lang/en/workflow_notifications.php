<?php

return [
    'navigation_label' => 'Workflow Notifications',
    'model_label' => 'Workflow Notification',
    'plural_model_label' => 'Workflow Notifications',

    'custom_form_column' => 'Workflow Notification',
    'custom_form_helper' => 'Optional. The workflow this form follows.',
    'candidate_notification_title' => ':form: :stage',
    'staff_notification_title' => 'Workflow action: :form: :stage',
    'staff_notification_body' => ':student has reached the :stage stage for :form.',
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
        'rejected' => 'Rejected',
        'approval' => 'Approval',
    ],

    'actions' => [
        'add_stage' => 'Add Stage',
        'close' => 'Close',
    ],

    'validation' => [
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
