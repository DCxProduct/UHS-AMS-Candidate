<div style="display: grid; gap: 1.5rem;">
    @foreach ($previews as $language => $html)
        <div>
            <p style="margin: 0 0 .5rem; font-weight: 600;">{{ $language }}</p>
            <iframe
                srcdoc="{{ $html }}"
                title="{{ $language }}"
                sandbox=""
                style="width: 100%; height: 640px; border: 1px solid rgba(0, 0, 0, .1); border-radius: .75rem; background: #fff;"
            ></iframe>
        </div>
    @endforeach
</div>
