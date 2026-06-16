<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            {{ __('dashboard.quick_actions') }}
        </x-slot>

        <x-slot name="description">
            {{ __('dashboard.quick_actions_description') }}
        </x-slot>

        @if (count($actions) > 0)
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                @foreach ($actions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="
                            group flex items-center gap-4 rounded-2xl
                            border border-gray-200 bg-white p-5
                            transition duration-200
                            hover:-translate-y-0.5
                            hover:border-primary-500
                            hover:shadow-lg
                            dark:border-white/10
                            dark:bg-gray-900
                            dark:hover:border-primary-500
                        "
                    >
                        <div
                            @class([
                                'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                                'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'
                                    => $action['completed'],
                                'bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400'
                                    => $action['expired'],
                                'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400'
                                    => ! $action['completed'] && ! $action['expired'],
                            ])
                        >
                            <x-filament::icon
                                :icon="$action['icon']"
                                class="h-7 w-7"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <h3
                                class="
                                    truncate text-base font-bold text-gray-950
                                    dark:text-white
                                "
                            >
                                {{ $action['name'] }}
                            </h3>

                            <p
                                @class([
                                    'mt-1 text-sm font-medium',
                                    'text-success-600 dark:text-success-400'
                                        => $action['completed'],
                                    'text-danger-600 dark:text-danger-400'
                                        => $action['expired'],
                                    'text-gray-500 dark:text-gray-400'
                                        => ! $action['completed'] && ! $action['expired'],
                                ])
                            >
                                @if ($action['completed'])
                                    {{ __('dashboard.completed') }}
                                @elseif ($action['expired'])
                                    {{ __('dashboard.expired_contact') }}
                                @else
                                    {{ __('dashboard.open_form') }}
                                @endif
                            </p>
                        </div>

                        <x-filament::icon
                            icon="heroicon-m-chevron-right"
                            class="
                                h-5 w-5 text-gray-400 transition
                                group-hover:translate-x-1
                                group-hover:text-primary-500
                            "
                        />
                    </a>
                @endforeach
            </div>
        @else
            <div
                class="
                    rounded-2xl border border-dashed border-gray-300
                    px-6 py-10 text-center
                    dark:border-white/15
                "
            >
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="
                        mx-auto h-10 w-10 text-gray-400
                    "
                />

                <p
                    class="
                        mt-3 text-sm font-medium text-gray-500
                        dark:text-gray-400
                    "
                >
                    {{ __('dashboard.no_available_forms') }}
                </p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
