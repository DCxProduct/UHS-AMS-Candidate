<span data-workflow-color="{{ $color }}" style="display: inline-flex; align-items: center; gap: 0.625rem;">
    <span aria-hidden="true" style="display: inline-block; width: 1.25rem; height: 1.25rem; flex-shrink: 0; border-radius: 0.375rem; border: 1px solid rgb(128 128 128 / 25%); background-color: {{ \App\Support\WorkflowButtonColors::swatch($color) }};"></span>
    <span>{{ $label }}</span>
</span>
