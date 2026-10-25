@php
    use Filament\Notifications\View\NotificationsIconAlias;
    use Filament\Support\Enums\Alignment;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag as FilamentComponentAttributeBag;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\Contracts\Pagination\Paginator;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
@endphp

<div class="fi-no-database">
    <x-filament::modal
        :alignment="$hasNotifications ? null : Alignment::Center"
        aria-labelledby="database-notifications.heading"
        close-button
        :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')"
        :extra-modal-window-attribute-bag="
            new FilamentComponentAttributeBag([
                'autofocus' => true,
                'tabindex' => '-1',
            ])
        "
        :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
        :icon="$hasNotifications ? null : Heroicon::OutlinedBellSlash"
        :icon-alias="$hasNotifications ? null : NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE"
        :icon-color="$hasNotifications ? null : 'gray'"
        id="database-notifications"
        slide-over
        :sticky-header="$hasNotifications"
        teleport="body"
        width="md"
        class="fi-no-database"
        :attributes="
            new FilamentComponentAttributeBag([
                'wire:poll.' . $pollingInterval => $pollingInterval ? '' : false,
            ])
        "
    >
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        @if ($hasNotifications)
            <x-slot name="header">
                <div>
                    <h2 id="database-notifications.heading" class="fi-modal-heading">
                        {{ __('filament-notifications::database.modal.heading') }}
                        @if ($unreadNotificationsCount)
                            <span {{ (new FilamentComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class(['fi-badge fi-size-xs']) }}>
                                {{ $unreadNotificationsCount }}
                            </span>
                        @endif
                    </h2>

                    <div class="fi-ac">
                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                            {{ $this->markAllNotificationsAsReadAction }}
                        @endif
                        @if ($this->clearNotificationsAction?->isVisible())
                            {{ $this->clearNotificationsAction }}
                        @endif
                    </div>
                </div>
            </x-slot>

            <div aria-label="{{ __('filament-notifications::database.modal.heading') }}" role="list" class="fi-no-notifications">
                @foreach ($notifications as $notification)
                    @php
                        $resubmissionEntryId = data_get($notification->data, 'viewData.resubmission_entry_id');
                        $isResubmission = filled($resubmissionEntryId);
                    @endphp
                    <div
                        role="listitem"
                        wire:key="{{ $notification->getKey() }}.database-notifications.ctn"
                        @class([
                            'fi-no-notification-read-ctn' => ! $notification->unread(),
                            'fi-no-notification-unread-ctn' => $notification->unread(),
                            'cursor-pointer' => $isResubmission,
                        ])
                        @if ($isResubmission)
                            tabindex="0"
                            x-on:click="if (! $event.target.closest('.fi-no-notification-close-btn')) $wire.openResubmissionReview(@js((string) $notification->getKey()))"
                            x-on:keydown.enter.prevent="$wire.openResubmissionReview(@js((string) $notification->getKey()))"
                            x-on:keydown.space.prevent="$wire.openResubmissionReview(@js((string) $notification->getKey()))"
                        @endif
                    >
                        @if ($notification->unread())
                            <span class="fi-sr-only">
                                {{ __('filament-notifications::database.modal.unread_label') }}
                            </span>
                        @endif
                        {{ $this->getNotification($notification)->inline() }}
                    </div>
                @endforeach
            </div>

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                    <script>
                        window.addEventListener('EchoLoaded', () => {
                            window.Echo.private(@js($broadcastChannel)).listen(
                                '.database-notifications.sent',
                                () => setTimeout(() => $wire.call('$refresh'), 500),
                            )
                        })

                        if (window.Echo) {
                            window.dispatchEvent(new CustomEvent('EchoLoaded'))
                        }
                    </script>
                @endscript
            @endif

            @if ($isPaginated)
                <x-slot name="footer">
                    <x-filament::pagination :paginator="$notifications" />
                </x-slot>
            @endif
        @endif
    </x-filament::modal>

    <x-filament::modal
        id="resubmission-review-note-modal"
        :close-button="false"
        :close-by-clicking-away="false"
        :close-by-escaping="false"
        width="2xl"
    >
        <x-slot name="header">
            <div style="position: relative; width: 100%; min-height: 2.5rem;">
                <h2 class="fi-modal-heading">
                    {{ __('candidate_entrance_statistics.review_note') }}
                </h2>

                <x-filament::icon-button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedXMark"
                    icon-size="lg"
                    :label="__('filament::components/modal.actions.close.label')"
                    style="position: absolute; top: 0; right: 0;"
                    x-on:click="$dispatch('close-modal', { id: 'resubmission-review-note-modal' })"
                />
            </div>
        </x-slot>

        @include('filament.notifications.resubmission-review-note', [
            'reviewNote' => $this->resubmissionReviewNote,
        ])

        <x-slot name="footer">
            <x-filament::button wire:click="confirmResubmissionReview">
                {{ __('candidate_entrance_statistics.actions.ok') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament-actions::modals />
</div>
