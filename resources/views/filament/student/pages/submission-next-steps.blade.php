<x-filament-panels::page>
    <x-filament::section>
        <div class="mx-auto max-w-2xl px-4 py-8 text-center sm:px-6 sm:py-10">
            <div class="space-y-3">
                <h2 class="text-xl font-semibold leading-relaxed text-gray-950 dark:text-white">{{ $nextSteps['title'] ?? '' }}</h2>
                @if (filled($nextSteps['message'] ?? null))
                    <p class="whitespace-pre-wrap break-words text-center leading-8 text-gray-700 dark:text-gray-300">{{ $nextSteps['message'] }}</p>
                @endif
            </div>
            <div class="mt-8 flex justify-center">
                <x-filament::button tag="a" :href="$continueUrl">
                    {{ $nextSteps['continue_label'] ?? __('submission_next_steps.continue') }}
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
