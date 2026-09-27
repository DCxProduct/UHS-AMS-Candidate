<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProtectUploadedFiles extends Command
{
    protected $signature = 'files:protect-uploads {--apply : Move public uploads into the private disk}';

    protected $description = 'Audit and optionally protect existing candidate and payment uploads';

    public function handle(): int
    {
        $source = Storage::disk((string) config('filament-custom-forms.uploads.legacy_disk', 'public'));
        $target = Storage::disk((string) config('filament-custom-forms.uploads.disk', 'private'));
        $paths = collect();
        try {
            $uploadFields = Schema::hasTable('custom_form_fields')
                ? DB::table('custom_form_fields')
                    ->whereIn('type', ['file', 'file_upload', 'fileupload', 'image', 'image_upload'])
                    ->pluck('name')
                    ->filter()
                    ->map(fn (mixed $name): string => (string) $name)
                    ->all()
                : [];

            Payment::query()
                ->whereNotNull('payment_slip_path')
                ->pluck('payment_slip_path')
                ->each(fn (mixed $path) => $paths->push($this->normalizePath((string) $path)));

            CustomFormEntry::query()
                ->select(['id', 'data'])
                ->whereNotNull('data')
                ->cursor()
                ->each(function (CustomFormEntry $entry) use ($paths, $uploadFields): void {
                    foreach ((array) $entry->data as $field => $value) {
                        if (! in_array((string) $field, $uploadFields, true)) {
                            continue;
                        }

                        foreach (collect($value)->flatten()->filter() as $filePath) {
                            if (is_string($filePath)) {
                                $paths->push($this->normalizePath($filePath));
                            }
                        }
                    }
                });
        } catch (Throwable $exception) {
            $this->error('Upload audit could not run because the database is unavailable: '.$exception->getMessage());

            return self::FAILURE;
        }

        $paths = $paths->filter()->unique()->values();
        $missing = 0;
        $protected = 0;

        foreach ($paths as $path) {
            if (! $source->exists($path)) {
                if ($target->exists($path)) {
                    $protected++;
                }

                continue;
            }

            $this->line(($this->option('apply') ? 'Protecting: ' : 'Would protect: ').$path);

            if (! $this->option('apply')) {
                continue;
            }

            $stream = $source->readStream($path);

            if (! is_resource($stream) || ! $target->put($path, $stream)) {
                $missing++;

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $this->error('Could not copy: '.$path);

                continue;
            }

            fclose($stream);
            $source->delete($path);
        }

        $this->info(sprintf(
            'Uploads inspected: %d; already private: %d; unresolved: %d.',
            $paths->count(),
            $protected,
            $missing
        ));

        return $missing > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function normalizePath(string $path): ?string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^(storage|public)/#', '', $path) ?: $path;

        return $path === '' || str_contains('/'.$path.'/', '/../') ? null : $path;
    }
}
