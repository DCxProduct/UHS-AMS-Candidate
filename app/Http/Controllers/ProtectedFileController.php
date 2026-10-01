<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Support\CustomFormEntryFiles;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedFileController extends Controller
{
    public function paymentSlip(Payment $payment): StreamedResponse|Response
    {
        $actor = auth()->user();

        if ($this->isCandidate($actor)) {
            abort_unless((int) $payment->users_id === (int) $actor->getKey(), 403);
        } else {
            Gate::authorize('view', $payment);
        }

        return $this->fileResponse(
            (string) $payment->payment_slip_path,
            'payment-receipt'
        );
    }

    public function customFormEntryFile(CustomFormEntry $entry, string $field): StreamedResponse|Response
    {
        $this->authorizeEntryFileAccess($entry);

        $data = is_array($entry->data) ? $entry->data : [];
        $path = data_get($data, $field);

        if (is_array($path)) {
            $path = collect($path)->flatten()->filter()->first();
        }

        return $this->fileResponse((string) $path, 'candidate-document');
    }

    public function customFormEntryDocument(Request $request, CustomFormEntry $entry, int $fileIndex): StreamedResponse|Response
    {
        $this->authorizeEntryFileAccess($entry);

        $file = app(CustomFormEntryFiles::class)->file($entry, $fileIndex);

        abort_if($file === null, 404);

        return $this->fileResponse(
            $file['path'],
            app(CustomFormEntryFiles::class)->filename($file['path']),
            inline: $request->boolean('inline'),
        );
    }

    protected function authorizeEntryFileAccess(CustomFormEntry $entry): void
    {
        $actor = auth()->user();

        if ($this->isCandidate($actor)) {
            abort_unless($this->entryBelongsToUser($entry, (int) $actor->getKey()), 403);

            return;
        }

        Gate::authorize('view', $entry);
    }

    protected function isCandidate(mixed $actor): bool
    {
        return $actor instanceof User
            && ((string) $actor->registration_type === 'student'
                || (method_exists($actor, 'hasEffectiveRole') && $actor->hasEffectiveRole('candidate')));
    }

    protected function entryBelongsToUser(CustomFormEntry $entry, int $userId): bool
    {
        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (array_key_exists($column, $entry->getAttributes()) && (int) $entry->{$column} === $userId) {
                return true;
            }
        }

        return (int) $entry->creator?->getKey() === $userId;
    }

    protected function fileResponse(string $path, string $downloadName, bool $inline = true): StreamedResponse|Response
    {
        $normalizedPath = $this->normalizePath($path);

        abort_if($normalizedPath === null, 404);

        $privateDisk = Storage::disk((string) config('filament-custom-forms.uploads.disk', 'private'));
        $legacyDisk = Storage::disk((string) config('filament-custom-forms.uploads.legacy_disk', 'public'));
        $disk = $privateDisk->exists($normalizedPath) ? $privateDisk : $legacyDisk;

        abort_unless($disk->exists($normalizedPath), 404);

        $mime = $disk->mimeType($normalizedPath) ?: 'application/octet-stream';
        $extension = pathinfo($normalizedPath, PATHINFO_EXTENSION);
        $filename = $downloadName;

        if (pathinfo($filename, PATHINFO_EXTENSION) === '' && $extension !== '') {
            $filename .= '.'.strtolower($extension);
        }

        $stream = function () use ($disk, $normalizedPath): void {
            $stream = $disk->readStream($normalizedPath);

            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        };

        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $inline
            ? response()->stream($stream, 200, $headers)
            : response()->streamDownload($stream, $filename, $headers);
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
