<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('candidate_entrance_statistics.stage_count', ['count' => count($stages)]) }}
    </p>

    @forelse ($stages as $stage)
        @php
            $stateClasses = match ($stage['state']) {
                'completed' => 'bg-success-50 text-success-700 dark:bg-success-950 dark:text-success-300',
                'current' => 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300',
                default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
            };
        @endphp

        <article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-start gap-5">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                    {{ $stage['number'] }}
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-gray-950 dark:text-white">{{ $stage['name'] }}</h3>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $stage['type_label'] }}
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $stateClasses }}">
                            {{ $stage['state_label'] }}
                        </span>
                    </div>

                    @if (filled($stage['completed_at']))
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ __('candidate_entrance_statistics.stage_completed_at') }}: {{ $stage['completed_at'] }}
                        </p>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            {{ __('candidate_entrance_statistics.no_application_stages') }}
        </p>
    @endforelse
</div>
