@php
    $progress = $progress ?? $getState();
    $completedLabel = __('candidate_entrance_statistics.workflow_completed_stages', [
        'completed' => \App\Support\LocalizedNumber::digits($progress['completed']),
        'total' => \App\Support\LocalizedNumber::digits($progress['total']),
    ]);
@endphp

<div class="relative px-3 py-2 whitespace-normal" style="width: {{ $progress['total'] > 0 ? max(320, $progress['total'] * 112) : 120 }}px;">
    @if ($progress['total'] === 0)
        <span class="text-sm text-gray-400" title="{{ __('candidate_entrance_statistics.no_application_stages') }}">
            <span aria-hidden="true">—</span>
            <span class="sr-only">{{ __('candidate_entrance_statistics.no_application_stages') }}</span>
        </span>
    @else
        <div
            class="flex pt-3"
            role="progressbar"
            aria-label="{{ __('candidate_entrance_statistics.workflow_progress') }}"
            aria-valuemin="0"
            aria-valuemax="{{ $progress['total'] }}"
            aria-valuenow="{{ $progress['completed'] }}"
            aria-valuetext="{{ $completedLabel }} — {{ $progress['label'] }}"
        >
            @foreach ($progress['stages'] as $stage)
                <div
                    class="relative flex min-w-0 flex-1 flex-col items-center"
                    data-workflow-stage="{{ $stage['state'] }}"
                    title="{{ $stage['name'] }} — {{ $stage['state_label'] }}"
                    aria-hidden="true"
                >
                    @unless ($loop->first)
                        <span class="absolute top-1 right-1/2 h-px w-full bg-gray-200 dark:bg-gray-600"></span>
                    @endunless
                    <span class="relative z-10 flex size-2 items-center justify-center">
                        @if ($stage['state'] === 'current')
                            <span
                                class="absolute -top-2.5 left-1/2 h-0 w-0 -translate-x-1/2 border-x-4 border-t-[5px] border-x-transparent border-t-amber-500"
                                data-workflow-current
                            ></span>
                        @endif
                        <span @class([
                            'block size-2 rounded-full border',
                            'border-amber-500 bg-amber-400' => in_array($stage['state'], ['completed', 'current'], true),
                            'border-gray-300 bg-gray-300 dark:border-gray-600 dark:bg-gray-600' => $stage['state'] === 'pending',
                        ])></span>
                    </span>
                    <span @class([
                        'mt-1.5 min-h-10 w-full px-1 text-center text-[11px] leading-5 whitespace-normal break-words',
                        'font-semibold text-amber-700 dark:text-amber-400' => $stage['state'] === 'current',
                        'text-gray-500 dark:text-gray-400' => $stage['state'] !== 'current',
                    ]) data-workflow-caption>{{ $stage['name'] }}</span>
                </div>
            @endforeach
        </div>
        @if (in_array($progress['status'], ['draft', 'rejected', 'failed'], true))
            <p @class([
                'mt-2 text-xs',
                'text-danger-600 dark:text-danger-400' => in_array($progress['status'], ['rejected', 'failed'], true),
                'text-gray-500 dark:text-gray-400' => $progress['status'] === 'draft',
            ])>{{ $progress['label'] }}</p>
        @endif
        <ol class="sr-only">
            @foreach ($progress['stages'] as $stage)
                <li>{{ $stage['name'] }}: {{ $stage['state_label'] }}</li>
            @endforeach
        </ol>
    @endif
</div>
