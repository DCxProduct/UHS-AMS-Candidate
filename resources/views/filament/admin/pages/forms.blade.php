<x-filament-panels::page>
    <div class="space-y-6 pt-10">
        {{ $this->form }}

        @php
            $selectedFormView = $this->getSelectedFormView();
            $selectedFormTitle = $this->getSelectedFormTitle();
        @endphp

        @if ($selectedFormView)
            <div style="margin-top: 20px;">
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $selectedFormTitle }}
                    </x-slot>

                    @include($selectedFormView)
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
