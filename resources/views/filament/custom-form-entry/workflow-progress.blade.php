@php
    $progress = $progress ?? $getState();
    $minimumWidth = $progress['total'] > 0 ? max(280, $progress['total'] * 112) : 120;
    $completedLabel = __('candidate_entrance_statistics.workflow_completed_stages', [
        'completed' => \App\Support\LocalizedNumber::digits($progress['completed']),
        'total' => \App\Support\LocalizedNumber::digits($progress['total']),
    ]);
@endphp

<div
    class="relative px-2 py-1.5 whitespace-normal"
    style="width: {{ $minimumWidth }}px;"
>
    @if ($progress['total'] === 0)
        <span class="text-sm text-gray-400" title="{{ __('candidate_entrance_statistics.no_application_stages') }}">
            <span aria-hidden="true">—</span>
            <span class="sr-only">{{ __('candidate_entrance_statistics.no_application_stages') }}</span>
        </span>
    @else
        <div
            class="flex"
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
                        <span class="absolute top-[11px] right-1/2 h-0.5 w-full bg-gray-200 dark:bg-gray-700"></span>
                    @endunless
                    <span @class([
                        'relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                        'border-green-600 bg-green-600 text-white' => $stage['state'] === 'completed',
                        'border-green-600 bg-white text-green-600 dark:border-green-400 dark:bg-gray-900 dark:text-green-400' => $stage['state'] === 'current',
                        'border-gray-300 bg-gray-300 text-white dark:border-gray-600 dark:bg-gray-600' => $stage['state'] === 'pending',
                    ])>
                        @if ($stage['state'] === 'completed')
                            <svg class="size-3" data-workflow-completed viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m5 12 4 4L19 6" />
                            </svg>
                        @elseif ($stage['state'] === 'current')
                            <svg class="size-3" data-workflow-current viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="9" />
                                <path d="M12 7v5l3 2" />
                            </svg>
                        @else
                            <svg class="size-3" data-workflow-pending viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                                <path d="M7 12h10" />
                            </svg>
                        @endif
                    </span>
                    <span @class([
                        'mt-1.5 min-h-5 w-full px-1 text-center text-[11px] leading-5 whitespace-normal break-words',
                        'font-semibold text-green-700 dark:text-green-400' => $stage['state'] === 'current',
                        'font-medium text-gray-700 dark:text-gray-200' => $stage['state'] === 'completed',
                        'text-gray-500 dark:text-gray-400' => $stage['state'] === 'pending',
                    ]) data-workflow-caption>{{ $stage['name'] }}</span>
                    @if ($stage['state'] === 'completed' && filled($stage['completed_at'] ?? null))
                        <span class="mt-0.5 w-full px-1 text-center text-[10px] leading-4 break-words text-gray-500 dark:text-gray-400" data-workflow-completed-at>{{ $stage['completed_at'] }}</span>
                    @endif
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
