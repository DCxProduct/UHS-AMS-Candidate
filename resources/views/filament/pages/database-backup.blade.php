<x-filament-panels::page>
    <form wire:submit="export" class="space-y-6">
        {{ $this->form }}

        <div class="uhs-backup-actions">
            <x-filament::button
                type="button"
                color="danger"
                icon="heroicon-o-trash"
                wire:click="erase"
                wire:confirm="{{ __('backup.confirmations.erase') }}"
            >
                {{ __('backup.actions.erase') }}
            </x-filament::button>

            <x-filament::button
                type="submit"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
            >
                {{ __('backup.actions.export') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
