<x-filament-panels::page>
    <div
        x-data="{ confirmSubmitOpen: false }"
        @keydown.escape.window="confirmSubmitOpen = false"
        class="relative"
    >
        <form wire:submit="save" x-ref="dynamicForm">
            {{ $this->form }}

            <br>

            <div class="flex justify-end">
                <x-filament::button
                    type="button"
                    color="warning"
                    icon="heroicon-o-check-circle"
                    x-on:click="confirmSubmitOpen = true"
                >
                    {{ __('app.save') }}
                </x-filament::button>
            </div>
        </form>

        <div
            x-cloak
            x-show="confirmSubmitOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-gray-950/40"
        ></div>

        <div
            x-cloak
            x-show="confirmSubmitOpen"
            x-transition
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-gray-200">
                <div class="flex justify-end px-4 pt-4">
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700"
                        x-on:click="confirmSubmitOpen = false"
                    >
                        <x-filament::icon
                            alias="panels::topbar.global-search.close-button"
                            icon="heroicon-m-x-mark"
                            class="h-5 w-5"
                        />
                    </button>
                </div>

                <div class="px-6 pb-6 pt-2 text-center">
                    <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <x-filament::icon
                            icon="heroicon-o-exclamation-triangle"
                            class="h-7 w-7"
                        />
                    </div>

                    <h2 class="text-lg font-semibold text-gray-900">
                        {{ $this->submitPopupTitle() }}
                    </h2>

                    <p class="mt-2 text-sm leading-6 text-gray-500">
                        {{ $this->submitPopupDescription() }}
                    </p>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <x-filament::button
                            type="button"
                            color="gray"
                            outlined
                            x-on:click="confirmSubmitOpen = false"
                        >
                            {{ $this->submitPopupCancelLabel() }}
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="primary"
                            x-on:click="confirmSubmitOpen = false; $refs.dynamicForm.requestSubmit()"
                        >
                            {{ $this->submitPopupConfirmLabel() }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
