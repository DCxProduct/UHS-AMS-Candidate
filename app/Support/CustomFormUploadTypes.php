<?php

namespace App\Support;

class CustomFormUploadTypes
{
    private const MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'word' => [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    public static function options(): array
    {
        return [
            'pdf' => 'PDF (.pdf)',
            'word' => __('filament-custom-forms::fcf.admin.word_document_type'),
        ];
    }

    public static function acceptedMimeTypes(array $options): array
    {
        $types = $options['accepted_file_types'] ?? [];
        $types = is_array($types) ? $types : [$types];
        $mimeTypes = [];

        foreach ($types as $type) {
            if (is_string($type)) {
                $mimeTypes = array_merge($mimeTypes, self::MIME_TYPES[$type] ?? []);
            }
        }

        // Unconfigured fields keep the application's existing upload defaults.
        return $mimeTypes !== []
            ? array_values(array_unique($mimeTypes))
            : (array) config('filament-custom-forms.uploads.accepted_mime_types', ['application/pdf']);
    }
}
