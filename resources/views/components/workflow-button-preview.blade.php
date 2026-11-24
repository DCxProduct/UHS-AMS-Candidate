@props(['buttons' => []])

@php
    $previewButtons = array_values(array_filter(is_array($buttons) ? $buttons : [], 'is_array'));
    $useKhmer = in_array(app()->getLocale(), ['km', 'kh'], true);
@endphp

@if ($previewButtons !== [])
    <div class="uhs-workflow-button-preview" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
        @foreach ($previewButtons as $button)
            @php
                $label = $useKhmer
                    ? (($button['label_km'] ?? null) ?: ($button['label_en'] ?? ''))
                    : (($button['label_en'] ?? null) ?: ($button['label_km'] ?? ''));
            @endphp

            <x-filament::link
                tag="button"
                type="button"
                size="sm"
                :icon="$button['icon'] ?? null"
                :color="\App\Support\WorkflowButtonColors::forDefinition($button)"
                :loading-indicator="false"
                disabled
            >
                {{ $label }}
            </x-filament::link>
        @endforeach
    </div>
@endif
