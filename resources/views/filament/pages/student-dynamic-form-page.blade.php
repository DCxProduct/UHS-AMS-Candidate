<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <br>

        <div class="flex justify-end">
            <x-filament::button
                type="submit"
                color="warning"
                icon="heroicon-o-check-circle"
            >
                {{ __('app.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
