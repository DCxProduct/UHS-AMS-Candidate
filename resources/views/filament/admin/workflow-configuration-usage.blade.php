@php
    $items = $getState() ?? [];
    $isStage = $kind === 'stage';
    $resource = $isStage
        ? \App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource::class
        : \App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource::class;
    $canOpen = $items !== [] && \App\Support\WorkflowConfigurationUsage::canOpenReference($kind);
@endphp

<div class="fi-ta-text" style="min-width: 10rem; padding: 0.75rem 1rem;">
    @forelse ($items as $item)
        <div style="margin-block: 0.25rem;">
            @if ($canOpen)
                <x-filament::link
                    :href="$resource::getUrl($isStage ? 'edit' : 'view', ['record' => $item['id']])"
                    :icon="$isStage ? 'heroicon-o-cog-8-tooth' : 'heroicon-o-bell-alert'"
                    size="sm"
                >
                    {{ $item['name'] }}
                </x-filament::link>
            @else
                <span class="text-sm text-gray-950 dark:text-white">{{ $item['name'] }}</span>
            @endif
        </div>
    @empty
        <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('workflow_buttons.table.not_used') }}</span>
    @endforelse
</div>
