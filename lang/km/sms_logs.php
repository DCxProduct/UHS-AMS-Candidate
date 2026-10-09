<?php

return [
    'navigation_label' => 'ប្រវត្តិ SMS',
    'model_label' => 'SMS',
    'plural_model_label' => 'ប្រវត្តិ SMS',

    'fields' => [
        'created_at' => 'កាលបរិច្ឆេទ',
        'user' => 'បេក្ខជន',
        'phone' => 'លេខទូរស័ព្ទ',
        'sent_to' => 'បានផ្ញើទៅ :phone',
        'sent_to_label' => 'បានផ្ញើទៅ',
        'content' => 'សារ',
        'source' => 'ប្រភព',
        'status' => 'ស្ថានភាព',
        'response' => 'ចម្លើយពី PlasGate',
    ],

    'sources' => [
        'workflow' => 'ការជូនដំណឹងដំណើរការ',
        'password_reset' => 'លេខកូដកំណត់ពាក្យសម្ងាត់',
        'notification' => 'ការជូនដំណឹង',
        'test' => 'SMS សាកល្បង',
    ],

    'statuses' => [
        'sent' => 'PlasGate បានទទួល',
        'failed' => 'បរាជ័យ',
    ],

    'actions' => [
        'details' => 'ព័ត៌មានលម្អិត',
        'close' => 'បិទ',
    ],

    'hint' => '"PlasGate បានទទួល" មានន័យថា PlasGate បានទទួល SMS ហើយ។ ថាតើវាបានទៅដល់ទូរស័ព្ទឬអត់ អាចមើលក្នុងគណនី PlasGate នៅ Campaign។ លេខកូដកំណត់ពាក្យសម្ងាត់ត្រូវបានលាក់ជា ******។',
];
