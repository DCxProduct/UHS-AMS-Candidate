<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomFormEntryFiles
{
    public function fieldNames(CustomFormEntry $entry): array
    {
        return array_map('strval', array_keys($this->fieldLabels($entry)));
    }

    protected function fieldLabels(CustomFormEntry $entry): array
    {
        return CustomFormField::query()
            ->where('custom_form_id', $entry->custom_form_id)
            ->whereIn('type', ['file', 'file_upload', 'fileupload', 'image', 'image_upload'])
            ->get(['name', 'label'])
            ->filter(fn (CustomFormField $field): bool => filled($field->name))
            ->mapWithKeys(fn (CustomFormField $field): array => [(string) $field->name => $field->label])
            ->all();
    }

    public function files(CustomFormEntry $entry): array
    {
        $fieldLabels = $this->fieldLabels($entry);
        $fieldNames = array_map('strval', array_keys($fieldLabels));

        if ($fieldNames === []) {
            return [];
        }

        $data = is_array($entry->data) ? $entry->data : [];
        $paths = [];
        $this->collectNamedFieldPaths($data, $fieldNames, $paths);

        $files = [];
        $seenPaths = [];

        foreach ($paths as $file) {
            $resolved = $this->resolveFile((string) $file['path']);

            if ($resolved === null || isset($seenPaths[$resolved['path']])) {
                continue;
            }

            $seenPaths[$resolved['path']] = true;
            $files[] = [
                'field_name' => $file['field_name'],
                'field_label' => $fieldLabels[$file['field_name']] ?? null,
                ...$resolved,
            ];
        }

        return $files;
    }

    public function file(CustomFormEntry $entry, int $index): ?array
    {
        return $this->files($entry)[$index] ?? null;
    }

    public function previewFiles(CustomFormEntry $entry): array
    {
        $files = [];

        foreach ($this->files($entry) as $index => $file) {
            $extension = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
            $previewType = match ($extension) {
                'pdf' => 'pdf',
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif' => 'image',
                default => null,
            };
            $parameters = ['entry' => $entry, 'fileIndex' => $index];

            $files[] = [
                'label' => $this->fieldLabel($file),
                'name' => $this->downloadFilename($file),
                'download_name' => $this->downloadFilename($file),
                'preview_type' => $previewType,
                'preview_url' => $previewType !== null
                    ? route('protected.custom-form-entry-document', [...$parameters, 'inline' => 1])
                    : null,
                'download_url' => route('protected.custom-form-entry-document', $parameters),
            ];
        }

        return $files;
    }

    public function downloadFilename(array $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F<>:"\/\\\\|?*]/u', '_', $this->fieldLabel($file)) ?? '';
        $name = trim($name, " .\t\n\r\0\x0B");
        $name = $name !== '' ? $name : 'document';
        $extension = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));

        if ($extension !== '' && ! Str::endsWith(strtolower($name), '.'.$extension)) {
            $name .= '.'.$extension;
        }

        return $name;
    }

    protected function fieldLabel(array $file): string
    {
        $label = $file['field_label'] ?? null;

        if (is_string($label) && str_starts_with(trim($label), '{')) {
            $label = json_decode($label, true) ?? $label;
        }

        if (is_array($label)) {
            $label = $label[app()->getLocale()]
                ?? $label['km']
                ?? $label['kh']
                ?? $label['en']
                ?? collect($label)->first();
        }

        return trim((string) $label) ?: $file['field_name'];
    }

    public function protectedUrl(CustomFormEntry $entry, string $path, bool $inline = false): ?string
    {
        $normalizedPath = $this->normalizePath($path);
        $index = collect($this->files($entry))
            ->search(fn (array $file): bool => $file['path'] === $normalizedPath);

        if ($index === false) {
            return null;
        }

        $parameters = [
            'entry' => $entry,
            'fileIndex' => $index,
        ];

        if ($inline) {
            $parameters['inline'] = 1;
        }

        return route('protected.custom-form-entry-document', $parameters);
    }

    public function protectedZipUrl(CustomFormEntry $entry): ?string
    {
        if ($this->files($entry) === []) {
            return null;
        }

        return route('protected.custom-form-entry-files-zip', ['entry' => $entry]);
    }

    public function filename(string $path): string
    {
        $filename = basename($path);

        return preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
    }

    public function archiveFilename(string $fieldName, string $path): string
    {
        $fieldName = preg_replace('/[^A-Za-z0-9._ -]/', '_', trim($fieldName)) ?: 'document';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $fieldName.($extension !== '' ? '.'.strtolower($extension) : '');
    }

    public function displayLabel(mixed $value): string
    {
        $paths = [];
        $this->collectFilePaths($value, $paths);

        if ($paths === []) {
            return '-';
        }

        return collect($paths)
            ->map(fn (string $path): string => $this->typeLabel($path))
            ->countBy()
            ->map(
                fn (int $count, string $label): string => $count > 1
                    ? $label.' x'.$count
                    : $label
            )
            ->values()
            ->implode(', ');
    }

    protected function typeLabel(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $label = match ($extension) {
            'doc', 'docx' => __('candidate_entrance_statistics.file_types.word'),
            'xls', 'xlsx' => __('candidate_entrance_statistics.file_types.excel'),
            'ppt', 'pptx' => __('candidate_entrance_statistics.file_types.powerpoint'),
            'pdf' => __('candidate_entrance_statistics.file_types.pdf'),
            'txt', 'csv' => __('candidate_entrance_statistics.file_types.text'),
            default => __('candidate_entrance_statistics.file_types.document'),
        };

        return $extension === ''
            ? $label
            : $label.' (.'.$extension.')';
    }

    protected function collectFilePaths(mixed $value, array &$paths): void
    {
        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                $this->collectFilePaths($nestedValue, $paths);
            }

            return;
        }

        if (is_string($value) && trim($value) !== '') {
            $paths[] = $value;
        }
    }

    protected function collectNamedFieldPaths(mixed $value, array $fieldNames, array &$paths): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nestedValue) {
            if (in_array((string) $key, $fieldNames, true)) {
                $fieldPaths = [];
                $this->collectFilePaths($nestedValue, $fieldPaths);

                foreach ($fieldPaths as $path) {
                    $paths[] = ['field_name' => (string) $key, 'path' => $path];
                }

                continue;
            }

            $this->collectNamedFieldPaths($nestedValue, $fieldNames, $paths);
        }
    }

    protected function resolveFile(string $path): ?array
    {
        $normalizedPath = $this->normalizePath($path);

        if ($normalizedPath === null) {
            return null;
        }

        $privateDisk = Storage::disk((string) config('filament-custom-forms.uploads.disk', 'private'));

        if ($privateDisk->exists($normalizedPath)) {
            return ['disk' => $privateDisk, 'path' => $normalizedPath];
        }

        $legacyDisk = Storage::disk((string) config('filament-custom-forms.uploads.legacy_disk', 'public'));

        return $legacyDisk->exists($normalizedPath)
            ? ['disk' => $legacyDisk, 'path' => $normalizedPath]
            : null;
    }

    protected function normalizePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || Str::startsWith($path, ['http://', 'https://', '/'])) {
            if (! Str::startsWith($path, ['/storage/', 'storage/'])) {
                return null;
            }

            $path = ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        }

        $path = Str::replaceFirst('storage/', '', $path);
        $path = Str::replaceFirst('public/', '', $path);

        if ($path === '' || str_contains('/'.$path.'/', '/../') || str_contains($path, "\0")) {
            return null;
        }

        return ltrim($path, '/');
    }
}
