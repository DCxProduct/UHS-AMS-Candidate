<?php

namespace App\Services;

use App\Models\DocumentRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

class DocumentRequestPdfService
{
    public function generate(DocumentRequest $documentRequest): string
    {
        $documentRequest->refresh();

        $locale = App::getLocale();

        $fileName = 'request-document-' . $documentRequest->id . '-' . $locale . '.pdf';
        $relativePath = 'document-requests/pdf-files/' . $fileName;
        $absolutePath = storage_path('app/public/' . $relativePath);

        Storage::disk('public')->makeDirectory('document-requests/pdf-files');

        $html = view('filament.student.document-requests.pdf-form', [
            'record' => $documentRequest,
            'isPdfExport' => true,
            'locale' => $locale,
        ])->render();

        Browsershot::html($html)
            ->setChromePath('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')
            ->emulateMedia('print')
            ->format('A4')
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->waitUntilNetworkIdle()
            ->setDelay(300)
            ->savePdf($absolutePath);

        return $relativePath;
    }
}
