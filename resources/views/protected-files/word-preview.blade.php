@php
    $scriptSources = "'self'";
    $connectSources = "'self'";

    if (\Illuminate\Support\Facades\Vite::isRunningHot()) {
        $hotUrl = trim((string) file_get_contents(\Illuminate\Support\Facades\Vite::hotFile()));
        $parts = parse_url($hotUrl);

        if (is_array($parts) && in_array($parts['scheme'] ?? '', ['http', 'https'], true) && isset($parts['host'])) {
            $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $scriptSources .= ' '.$origin;
            $connectSources .= ' '.$origin.' '.($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; script-src {{ $scriptSources }}; style-src 'self' 'unsafe-inline'; img-src data: blob:; font-src 'self' data: blob:; connect-src {{ $connectSources }}; frame-src 'none'; object-src 'none'; base-uri 'none'; form-action 'none'">
    <meta name="referrer" content="no-referrer">
    <title>{{ $filename }}</title>
    @vite('resources/js/word-document-preview.js')
</head>
<body>
    <main
        class="word-preview"
        data-word-preview
        data-document-url="{{ $documentUrl }}"
        data-error-message="{{ __('candidate_entrance_statistics.file_preview.error') }}"
        aria-label="{{ $filename }}"
        aria-busy="true"
    >
        <div class="word-preview-state" data-preview-loading role="status">
            <span class="word-preview-spinner" aria-hidden="true"></span>
            <p>{{ __('candidate_entrance_statistics.file_preview.loading') }}</p>
        </div>
        <div class="word-preview-state" data-preview-error role="alert" hidden>
            <p data-preview-error-message>{{ __('candidate_entrance_statistics.file_preview.error') }}</p>
            <div class="word-preview-actions">
                <button class="word-preview-button" data-preview-retry type="button">
                    {{ __('candidate_entrance_statistics.file_preview.retry') }}
                </button>
                <a class="word-preview-button" href="{{ $downloadUrl }}" download="{{ $filename }}">
                    {{ __('candidate_entrance_statistics.file_preview.download') }}
                </a>
            </div>
        </div>
        <div class="word-preview-content" data-preview-content tabindex="0" aria-label="{{ $filename }}" hidden></div>
        <noscript>
            <p>{{ __('candidate_entrance_statistics.file_preview.error') }}</p>
            <a href="{{ $downloadUrl }}" download="{{ $filename }}">{{ __('candidate_entrance_statistics.file_preview.download') }}</a>
        </noscript>
    </main>
</body>
</html>
