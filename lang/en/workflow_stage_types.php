<?php

return [
    'navigation_label' => 'Stage Types',
    'resource_label' => 'Stage Type',
    'resource_plural_label' => 'Stage Types',
    'search' => 'Search stage types',
    'states' => [
        'inactive' => 'Inactive',
    ],
    'validation' => [
        'behavior_immutable' => 'The stage action cannot be changed after creation.',
        'delete_protected' => 'Built-in stage types and stage types used by a workflow cannot be deleted. You can deactivate them instead.',
        'invalid_selection' => 'Select an available stage type.',
        'enabled_actions_required' => 'Select at least one button for this stage.',
        'enabled_actions_invalid' => 'Choose one of the listed buttons.',
        'enabled_actions_same_group' => 'Choose buttons from the same group. Use separate stages for different groups.',
        'enabled_actions_group_immutable' => 'This stage’s button group is fixed. Choose buttons from its existing group, or create another stage.',
    ],
    'form' => [
        'section_title' => 'Stage Type Details',
        'enabled_actions_create_help' => 'Select related buttons for this stage.',
        'enabled_actions_edit_help' => 'Stage group: :group.',
    ],
    'fields' => [
        'label_km' => 'Name Khmer',
        'label_en' => 'Name English',
        'behavior' => 'Stage action',
        'is_active' => 'Active',
        'enabled_actions' => 'Action buttons for this stage',
    ],
    'picker_groups' => [
        'candidate' => 'Candidate actions',
        'system' => 'System user actions',
    ],
    'groups' => [
        'form_submission' => 'Submission',
        'review' => 'Review',
        'payment' => 'Payment',
        'awaiting_results' => 'Results',
        'completed' => 'Notification',
    ],
    'helpers' => [
        'apply_review_settings' => 'Copies this type’s review settings into this workflow. Save the workflow to keep them.',
    ],
    'config_actions' => [
        'submitted' => 'Submitted',
        'unpaid' => 'Unpaid',
        'paid' => 'Paid',
        'pending' => 'Pending result',
        'passed' => 'Passed',
        'notify' => 'Notify',
    ],
    'button_actions' => [
        'submitted' => 'Submit form',
        'accept' => 'Check application',
        'send_back' => 'Send back',
        'reject' => 'Reject',
        'paid' => 'Record payment',
        'passed' => 'Mark passed',
        'notify' => 'Notify candidate',
    ],
    'table' => [
        'no' => 'No.',
        'label' => 'Name',
        'selected_buttons' => 'Selected action buttons',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],
    'actions' => [
        'apply_review_settings' => 'Apply review settings',
        'manage' => 'Manage stage types',
    ],
];
