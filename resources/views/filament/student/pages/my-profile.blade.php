<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <br/>

        <div class="flex justify-end">
            <x-filament::button
                type="submit"
                icon="heroicon-o-check-circle"
                color="warning"
            >
                {{ __('student_profile.save_changes') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
