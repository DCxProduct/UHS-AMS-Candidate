<div style="display: grid; gap: 1rem; font-size: .875rem;">
    <div>
        <p style="margin: 0 0 .25rem; font-weight: 600;">{{ __('sms_logs.fields.content') }}</p>
        <div style="padding: .875rem 1rem; border-radius: 1rem; background: #e5e7eb; color: #111827; white-space: pre-wrap; line-height: 1.5;">{{ $log->content }}</div>
    </div>

    <div style="display: grid; grid-template-columns: max-content 1fr; gap: .375rem 1rem;">
        <span style="opacity: .7;">{{ __('sms_logs.fields.created_at') }}</span><span>{{ $log->created_at?->format('d-m-Y H:i:s') }}</span>
        <span style="opacity: .7;">{{ __('sms_logs.fields.user') }}</span><span>{{ $log->user?->name ?? '—' }}</span>
        <span style="opacity: .7;">{{ __('sms_logs.fields.phone') }}</span><span>{{ $log->phone ?? '—' }}</span>
        <span style="opacity: .7;">{{ __('sms_logs.fields.sent_to_label') }}</span><span>{{ $log->sent_to ?? '—' }}</span>
        <span style="opacity: .7;">{{ __('sms_logs.fields.source') }}</span><span>{{ filled($log->source) ? __('sms_logs.sources.'.$log->source) : '—' }}</span>
        <span style="opacity: .7;">{{ __('sms_logs.fields.status') }}</span><span>{{ __('sms_logs.statuses.'.$log->status) }}</span>
    </div>

    <div>
        <p style="margin: 0 0 .25rem; font-weight: 600;">{{ __('sms_logs.fields.response') }}</p>
        <pre style="margin: 0; padding: .75rem; border-radius: .5rem; background: rgba(127, 127, 127, .1); white-space: pre-wrap; word-break: break-all; font-size: .75rem;">{{ $log->response ?: '—' }}</pre>
    </div>

    <p style="margin: 0; font-size: .75rem; opacity: .7;">{{ __('sms_logs.hint') }}</p>
</div>
