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
        'status_message' => 'Student Status Message (Optional)',
        'notification_message' => 'Student Notification Message (Optional)',
        'group_name' => 'Group Name (Optional)',
        'parallel_stages' => 'Stages in this group',
    ],

    'placeholders' => [
        'name' => 'Example: Student Admission',
        'stage_name' => 'Example: Review application',
        'status_message' => 'Example: Under review',
        'notification_message' => 'Example: Your documents are being checked.',
        'group_name' => 'Example: Document checks',
    ],

    'helpers' => [
        'assigned_forms' => 'Forms that follow this workflow. A form can belong to only one template.',
        'parallel_group' => 'Stages in a group happen at the same time; the workflow continues when all of them are done.',
    ],

    'labels' => [
        'new_stage' => 'New stage',
        'parallel_group' => 'Parallel group :name (:count stages)',
    ],

    'stage_types' => [
        'form_submission' => 'Form Submission',
        'review' => 'Review',
        'approval' => 'Approval',
        'payment' => 'Payment',
    ],

    'actions' => [
        'add_stage' => 'Add Stage',
        'add_parallel_group' => 'Add Parallel Group',
    ],

    'validation' => [
        'stages_required' => 'Add at least one stage.',
    ],
];
