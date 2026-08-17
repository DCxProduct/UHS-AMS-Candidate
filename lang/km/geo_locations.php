<?php

return [
    'navigation_label' => 'ទីតាំងភូមិសាស្ត្រ',
    'navigation_group' => 'ការកំណត់',

    'resource_label' => 'ទីតាំងភូមិសាស្ត្រ',
    'resource_plural_label' => 'ទីតាំងភូមិសាស្ត្រ',

    'actions' => [
        'new' => 'បង្កើតថ្មី',
        'edit' => 'កែប្រែ',
        'delete' => 'លុប',
        'actions' => 'សកម្មភាព',
    ],

    'filters' => [
        'search_placeholder' => 'ស្វែងរក...',
        'filter_location' => 'តម្រងទីតាំង',
        'show_by_type' => 'បង្ហាញតាមប្រភេទ',
        'all_types' => 'ប្រភេទទាំងអស់',
        'select_province' => 'ជ្រើសរើសរាជធានី/ខេត្ត',
        'select_district' => 'ជ្រើសរើសស្រុក/ខណ្ឌ',
        'select_commune' => 'ជ្រើសរើសឃុំ/សង្កាត់',
        'select_village' => 'ជ្រើសរើសភូមិ',
    ],

    'types' => [
        'province' => 'រាជធានី / ខេត្ត',
        'district' => 'ស្រុក / ខណ្ឌ',
        'commune' => 'ឃុំ / សង្កាត់',
        'village' => 'ភូមិ',
    ],

    'form' => [
        'section_title' => 'ព័ត៌មានទីតាំងភូមិសាស្ត្រ',
        'section_description' => 'បង្កើតឋានានុក្រម រាជធានី/ខេត្ត ស្រុក/ខណ្ឌ ឃុំ/សង្កាត់ និង ភូមិ។',

        'name_kh' => 'ឈ្មោះជាភាសាខ្មែរ',
        'name_kh_placeholder' => 'បញ្ចូលឈ្មោះជាភាសាខ្មែរ',

        'name_en' => 'ឈ្មោះជាភាសាអង់គ្លេស',
        'name_en_placeholder' => 'បញ្ចូលឈ្មោះជាភាសាអង់គ្លេស',

        'code' => 'កូដ',
        'code_placeholder' => 'បញ្ចូលកូដទីតាំង',

        'type' => 'ប្រភេទ',

        'parent_location' => 'ទីតាំងមេ',
        'parent_location_placeholder' => 'ជ្រើសរើសទីតាំងមេ',
        'parent_helper' => 'ស្រុក/ខណ្ឌ ត្រូវជ្រើសរើស រាជធានី/ខេត្ត។ ឃុំ/សង្កាត់ ត្រូវជ្រើសរើស ស្រុក/ខណ្ឌ។ ភូមិ ត្រូវជ្រើសរើស ឃុំ/សង្កាត់។',

        'active' => 'ដំណើរការ',

        'metadata' => 'ព័ត៌មានបន្ថែម',
        'key' => 'ពាក្យគន្លឹះ',
        'value' => 'តម្លៃ',
        'add_metadata' => 'បន្ថែមព័ត៌មាន',

        'no_name' => 'គ្មានឈ្មោះ',
    ],

    'table' => [
        'no' => 'ល.រ',
        'name' => 'ឈ្មោះ',
        'code' => 'កូដ',
        'type' => 'ប្រភេទ',
        'parent' => 'ទីតាំងមេ',
        'active' => 'ដំណើរការ',
    ],
];
