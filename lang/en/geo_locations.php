<?php

return [
    'navigation_label' => 'Geo Locations',
    'navigation_group' => 'Settings',

    'resource_label' => 'Geo Location',
    'resource_plural_label' => 'Geo Locations',

    'actions' => [
        'new' => 'Create New',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
    ],

    'filters' => [
        'search_placeholder' => 'Search...',
        'filter_location' => 'Filter Location',
        'show_by_type' => 'Show By Type',
        'all_types' => 'All Types',
        'select_province' => 'Select province',
        'select_district' => 'Select district',
        'select_commune' => 'Select commune',
        'select_village' => 'Select village',
    ],

    'types' => [
        'province' => 'Province / Capital',
        'district' => 'District / Khan',
        'commune' => 'Commune / Sangkat',
        'village' => 'Village',
    ],

    'form' => [
        'section_title' => 'Geo Location Information',
        'section_description' => 'Create Province, District, Commune, and Village hierarchy.',

        'name_kh' => 'Khmer Name',
        'name_kh_placeholder' => 'Enter Khmer Name',

        'name_en' => 'English Name',
        'name_en_placeholder' => 'Enter English Name',

        'code' => 'Code',
        'code_placeholder' => 'Enter Location Code',

        'type' => 'Type',

        'parent_location' => 'Parent Location',
        'parent_location_placeholder' => 'Select Parent Location',
        'parent_helper' => 'District/Khan requires Province/Capital. Commune/Sangkat requires District/Khan. Village requires Commune/Sangkat.',

        'active' => 'Active',

        'metadata' => 'Metadata',
        'key' => 'Key',
        'value' => 'Value',
        'add_metadata' => 'Add Metadata',

        'no_name' => 'No Name',
    ],

    'table' => [
        'no' => 'No',
        'name' => 'Name',
        'code' => 'Code',
        'type' => 'Type',
        'parent' => 'Parent Location',
        'active' => 'Active',
    ],
];
