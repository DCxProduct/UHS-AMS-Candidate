<div
    x-data="{
        files: @js($files),
        selectedIndex: 0,
        get selected() { return this.files[this.selectedIndex] ?? null },
    }"
    class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10"
>
    @if ($files === [])
        <p class="p-6 text-sm text-gray-500 dark:text-gray-400">{{ __('candidate_entrance_statistics.no_data') }}</p>
    @else
        <div class="grid md:grid-cols-[16rem_minmax(0,1fr)]">
            <nav
                class="overflow-y-auto border-b border-gray-200 p-3 md:border-r md:border-b-0 dark:border-white/10"
                style="max-height: 65vh;"
                aria-label="{{ __('candidate_entrance_statistics.uploaded_files') }}"
            >
                <div class="flex gap-2 md:flex-col">
                    <template x-for="(file, index) in files" :key="file.download_url">
                        <button
                            type="button"
                            class="min-w-48 rounded-lg border p-3 text-left transition focus-visible:outline-2 focus-visible:outline-primary-500 md:min-w-0"
                            :class="selectedIndex === index
                                ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-500 dark:bg-primary-500/10 dark:text-primary-400'
                                : 'border-transparent text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5'"
                            :aria-pressed="selectedIndex === index"
                            @click="selectedIndex = index"
                        >
                            <span class="block text-sm font-semibold break-words" x-text="file.name"></span>
                        </button>
                    </template>
                </div>
            </nav>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-3 dark:border-white/10">
                    <p class="min-w-0 text-sm font-semibold break-words" x-text="selected.name"></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <template x-if="selected.preview_url">
                            <x-filament::button
                                tag="a"
                                color="gray"
                                size="sm"
                                x-bind:href="selected.preview_url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ __('candidate_entrance_statistics.file_preview.open_in_new_tab') }}
                            </x-filament::button>
                        </template>
                        <x-filament::button
                            tag="a"
                            color="gray"
                            size="sm"
                            icon="heroicon-o-arrow-down-tray"
                            x-bind:href="selected.download_url"
                            x-bind:download="selected.download_name"
                        >
                            {{ __('candidate_entrance_statistics.file_preview.download') }}
                        </x-filament::button>
                    </div>
                </div>

                <div class="overflow-auto bg-gray-50 dark:bg-gray-950" style="height: 55vh; min-height: 16rem;">
                    <template x-if="selected.preview_type === 'pdf'">
                        <iframe
                            x-bind:src="selected.preview_url"
                            x-bind:title="selected.name"
                            class="h-full w-full border-0 bg-white"
                        ></iframe>
                    </template>
                    <template x-if="selected.preview_type === 'image'">
                        <div class="flex h-full items-center justify-center p-4">
                            <img
                                x-bind:src="selected.preview_url"
                                x-bind:alt="selected.name"
                                class="max-h-full max-w-full object-contain"
                            >
                        </div>
                    </template>
                    <template x-if="! selected.preview_type">
                        <div class="flex h-full flex-col items-center justify-center gap-4 p-6 text-center">
                            <x-filament::icon icon="heroicon-o-document-text" class="size-12 text-gray-400" />
                            <p class="text-sm font-medium break-all" x-text="selected.name"></p>
                            <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                                {{ __('candidate_entrance_statistics.file_preview.unavailable') }}
                            </p>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-gray-200 p-3 dark:border-white/10">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        x-bind:disabled="selectedIndex === 0"
                        x-on:click="selectedIndex--"
                    >
                        {{ __('candidate_entrance_statistics.file_preview.previous') }}
                    </x-filament::button>
                    <span class="text-sm text-gray-500 dark:text-gray-400" aria-live="polite">
                        <span x-text="selectedIndex + 1"></span> / <span x-text="files.length"></span>
                    </span>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        x-bind:disabled="selectedIndex === files.length - 1"
                        x-on:click="selectedIndex++"
                    >
                        {{ __('candidate_entrance_statistics.file_preview.next') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</div>
