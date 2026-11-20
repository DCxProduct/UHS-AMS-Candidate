<div class="uhs-custom-workflow-actions" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
    @foreach ($customStageButtons as $button)
        @php
            $buttonAction = $action([
                'stage_index' => (int) $button['_stage_index'],
                'button_id' => (int) $button['id'],
            ])->link();
        @endphp

        @if ($buttonAction->isVisible())
            <x-filament::link
                tag="button"
                type="button"
                size="sm"
                :icon="$buttonAction->getIcon()"
                :color="$buttonAction->getColor()"
                :disabled="$buttonAction->isDisabled()"
                wire:click="{{ $buttonAction->getLivewireClickHandler() }}"
                wire:key="custom-workflow-{{ $action->getLivewire()->getId() }}-{{ $action->getRecord()->getKey() }}-{{ $button['_stage_index'] }}-{{ $button['id'] }}"
            >
                {{ $buttonAction->getLabel() }}
            </x-filament::link>
        @endif
    @endforeach
</div>
