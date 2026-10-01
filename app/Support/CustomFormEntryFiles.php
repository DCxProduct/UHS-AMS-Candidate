<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomFormEntryFiles
{
    public function files(CustomFormEntry $entry): array
    {
        $fieldNames = CustomFormField::query()
            ->where('custom_form_id', $entry->custom_form_id)
            ->whereIn('type', ['file', 'file_upload', 'fileupload'])
            ->pluck('name')
            ->filter()
            ->map(fn ($name): string => (string) $name)
            ->values()
            ->all();

        if ($fieldNames === []) {
            return [];
        }

        $data = is_array($entry->data) ? $entry->data : [];
        $paths = [];

        foreach ($fieldNames as $fieldName) {
            $this->collectFilePaths(data_get($data, $fieldName), $paths);
        }

        $this->collectNestedFieldPaths($data, $fieldNames, $paths);

        $files = [];
        $seenPaths = [];

        foreach ($paths as $path) {
            $resolved = $this->resolveFile((string) $path);

            if ($resolved === null || isset($seenPaths[$resolved['path']])) {
                continue;
            }

            $seenPaths[$resolved['path']] = true;
            $files[] = $resolved;
        }

        return $files;
    }

    public function file(CustomFormEntry $entry, int $index): ?array
    {
        return $this->files($entry)[$index] ?? null;
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

    public function filename(string $path): string
    {
        $filename = basename($path);

        return preg_replace('/[^A-Za-z0-9._ -]/', '_', $filename) ?: 'document';
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
            'doc', 'docx' => __('review_applications.file_types.word'),
            'xls', 'xlsx' => __('review_applications.file_types.excel'),
            'ppt', 'pptx' => __('review_applications.file_types.powerpoint'),
            'pdf' => __('review_applications.file_types.pdf'),
            'txt', 'csv' => __('review_applications.file_types.text'),
            default => __('review_applications.file_types.document'),
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

    protected function collectNestedFieldPaths(mixed $value, array $fieldNames, array &$paths): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nestedValue) {
            if (in_array((string) $key, $fieldNames, true)) {
                $this->collectFilePaths($nestedValue, $paths);
                continue;
            }

            $this->collectNestedFieldPaths($nestedValue, $fieldNames, $paths);
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
