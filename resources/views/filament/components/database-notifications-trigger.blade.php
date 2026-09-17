@php
    $databaseNotificationsLabel = $unreadNotificationsCount
        ? trans_choice('filament-panels::layout.actions.open_database_notifications.label_with_unread_count', $unreadNotificationsCount, ['count' => \Illuminate\Support\Number::format($unreadNotificationsCount, locale: app()->getLocale())])
        : __('filament-panels::layout.actions.open_database_notifications.label');
@endphp

@if ($isTopbar)
    <x-filament::icon-button
        :badge="$unreadNotificationsCount ?: null"
        color="gray"
        :icon="\Filament\Support\Icons\Heroicon::OutlinedBell"
        :icon-alias="\Filament\View\PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON"
        icon-size="lg"
        :label="$databaseNotificationsLabel"
        class="fi-topbar-database-notifications-btn"
        x-on:click="$wire.markAllNotificationsAsRead()"
    />
@else
    @php
        $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    @endphp

    <button
        type="button"
        @if ($isSidebarCollapsibleOnDesktop)
            x-bind:aria-label="$store.sidebar.isOpen ? null : @js($databaseNotificationsLabel)"
        @endif
        class="fi-sidebar-database-notifications-btn"
        x-on:click="$wire.markAllNotificationsAsRead()"
    >
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedBell, alias: \Filament\View\PanelsIconAlias::SIDEBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON, size: \Filament\Support\Enums\IconSize::Large) }}

        <span
            @if ($isSidebarCollapsibleOnDesktop)
                x-show="$store.sidebar.isOpen"
                x-transition:enter="fi-transition-enter"
                x-transition:enter-start="fi-transition-enter-start"
                x-transition:enter-end="fi-transition-enter-end"
            @endif
            class="fi-sidebar-database-notifications-btn-label"
        >
            {{ __('filament-panels::layout.actions.open_database_notifications.label') }}
        </span>

        @if ($unreadNotificationsCount)
            <span
                @if ($isSidebarCollapsibleOnDesktop)
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="fi-transition-enter"
                    x-transition:enter-start="fi-transition-enter-start"
                    x-transition:enter-end="fi-transition-enter-end"
                @endif
                class="fi-sidebar-database-notifications-btn-badge-ctn"
            >
                <x-filament::badge>{{ $unreadNotificationsCount }}</x-filament::badge>
            </span>
        @endif
    </button>
@endif
