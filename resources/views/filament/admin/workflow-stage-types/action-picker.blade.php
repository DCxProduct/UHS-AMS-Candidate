@php
    $options = $getOptions();
    $statePath = $getStatePath();
    $state = $getState() ?? [];
    $disabled = $isDisabled();
    $hasError = $field->hasErrorForPath($statePath);
    $groups = [
        'candidate' => ['icon' => 'heroicon-o-user', 'keys' => ['submitted']],
        'system' => ['icon' => 'heroicon-o-computer-desktop', 'keys' => array_diff(array_keys($options), ['submitted'])],
    ];
    $gridAttributes = (new \Filament\Support\View\ComponentAttributeBag)
        ->grid($getColumns(), $getGridDirection())
        ->class(['fi-fo-checkbox-list-options']);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field" label-tag="div">
    <div
        role="group"
        aria-labelledby="{{ $getId() }}-label"
        {{ $getExtraAttributeBag()->class(['fi-fo-checkbox-list space-y-4']) }}
    >
        @foreach ($groups as $group => $details)
            <fieldset
                data-stage-type-button-group="{{ $group }}"
                class="min-w-0 rounded-lg border border-gray-200 px-4 pb-4 dark:border-gray-700"
            >
                <legend class="px-2 text-sm font-medium text-gray-950 dark:text-white">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon :icon="$details['icon']" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                        {{ __('workflow_stage_types.picker_groups.' . $group) }}
                    </span>
                </legend>

                <div class="mt-2">
                    <div {{ $gridAttributes }}>
                        @foreach ($details['keys'] as $value)
                            @if (array_key_exists($value, $options))
                                <div
                                    class="fi-fo-checkbox-list-option-ctn"
                                    wire:key="{{ $getLivewireKey() }}.options.{{ $value }}"
                                >
                                    <label class="fi-fo-checkbox-list-option">
                                        <input
                                            {{ $getExtraInputAttributeBag()->merge([
                                                'id' => $getId() . '-' . $value,
                                                'type' => 'checkbox',
                                                'value' => $value,
                                                'checked' => in_array($value, $state, true),
                                                'disabled' => $disabled || $isOptionDisabled($value, $options[$value]),
                                                'aria-invalid' => $hasError ? 'true' : null,
                                                'wire:loading.attr' => 'disabled',
                                                $applyStateBindingModifiers('wire:model') => $statePath,
                                            ])->class([
                                                'fi-checkbox-input',
                                                'fi-valid' => ! $hasError,
                                                'fi-invalid' => $hasError,
                                            ]) }}
                                        />
                                        <div class="fi-fo-checkbox-list-option-text">
                                            <span class="fi-fo-checkbox-list-option-label">{{ $options[$value] }}</span>
                                        </div>
                                    </label>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </fieldset>
        @endforeach
    </div>
</x-dynamic-component>
