{{-- "Insert Variable" for a plain textarea: puts the chosen variable at the cursor. --}}
<div
    x-data="{
        insert(value) {
            const field = document.getElementById(@js($target));

            if (! field) {
                return;
            }

            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? field.value.length;

            field.setRangeText(value, start, end, 'end');
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.focus();
        },
    }"
    data-insert-variable="{{ $target }}"
>
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <x-filament::button
                type="button"
                color="gray"
                size="sm"
                outlined
                icon="heroicon-m-code-bracket"
                icon-position="before"
            >
                {{ $label }}
            </x-filament::button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($variables as $variable)
                <x-filament::dropdown.list.item
                    tag="button"
                    x-on:click="insert({{ \Illuminate\Support\Js::from($variable) }}); close()"
                >
                    {{ $variable }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
