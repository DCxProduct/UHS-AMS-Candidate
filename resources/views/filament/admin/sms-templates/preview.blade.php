<div style="display: grid; gap: 1.5rem;">
    @foreach ($previews as $language => $text)
        <div>
            <p style="margin: 0 0 .5rem; font-weight: 600;">{{ $language }}</p>
            <div style="max-width: 360px; padding: .875rem 1rem; border-radius: 1rem; background: #e5e7eb; color: #111827; white-space: pre-wrap; line-height: 1.5;">{{ $text }}</div>
            <p style="margin: .375rem 0 0; font-size: .75rem; opacity: .7;">{{ mb_strlen($text) }} {{ __('sms_templates.preview.characters') }}</p>
        </div>
    @endforeach
</div>
