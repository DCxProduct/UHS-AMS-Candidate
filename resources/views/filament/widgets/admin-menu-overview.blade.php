<x-filament-widgets::widget>
    <section class="uhs-admin-overview">
        <div class="uhs-admin-overview__hero">
            <div class="uhs-admin-overview__hero-main">
                <span class="uhs-admin-overview__eyebrow">{{ $eyebrow ?? __('dashboard.quick_access') }}</span>
                <h2 class="uhs-admin-overview__title">{{ $title ?? __('dashboard.management_overview') }}</h2>
                <p class="uhs-admin-overview__description">
                    {{ $description ?? __('dashboard.management_overview_description') }}
                </p>
            </div>

            @if (! empty($highlights))
                <div class="uhs-admin-overview__highlights {{ count($highlights) <= 2 ? 'uhs-admin-overview__highlights--compact' : '' }}">
                    @foreach ($highlights as $highlight)
                        <div class="uhs-admin-overview__highlight">
                            <span class="uhs-admin-overview__highlight-label">{{ $highlight['label'] }}</span>
                            <span class="uhs-admin-overview__highlight-value">{{ $highlight['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if (! empty($items))
            <div class="uhs-admin-overview__grid">
                @foreach ($items as $item)
                    <a
                        href="{{ $item['url'] }}"
                        class="uhs-admin-overview__card uhs-admin-overview__card--{{ $item['tone'] }}"
                    >
                        <div class="uhs-admin-overview__card-top">
                            <span class="uhs-admin-overview__icon-wrap">
                                <x-filament::icon
                                    :icon="$item['icon']"
                                    class="uhs-admin-overview__icon"
                                />
                            </span>

                            <span class="uhs-admin-overview__action">
                                {{ $item['action_label'] }}
                            </span>
                        </div>

                        <div class="uhs-admin-overview__metric">{{ $item['display_count'] }}</div>

                        <div class="uhs-admin-overview__copy">
                            <h3 class="uhs-admin-overview__card-title">{{ $item['label'] }}</h3>
                            <p class="uhs-admin-overview__card-description">{{ $item['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
