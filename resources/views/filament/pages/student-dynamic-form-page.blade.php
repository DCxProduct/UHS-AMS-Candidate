<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <br>

        <div class="flex justify-end gap-3">
            <x-filament::button
                tag="a"
                href="{{ $this->listUrl() }}"
                color="gray"
            >
                {{ __('app.cancel') }}
            </x-filament::button>

            @if ($this->activeSectionIndex > 0)
                <x-filament::button
                    type="button"
                    color="gray"
                    wire:click="previousSection"
                >
                    {{ __('app.previous') }}
                </x-filament::button>
            @endif

            @if (count($this->sections) > 1 && ! $this->isLastSection())
                <x-filament::button
                    type="button"
                    color="warning"
                    wire:click="nextSection"
                >
                    {{ __('app.next') }}
                </x-filament::button>
            @else
                <x-filament::button
                    type="submit"
                    color="warning"
                    icon="heroicon-o-check-circle"
                >
                    {{ __('app.save') }}
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
