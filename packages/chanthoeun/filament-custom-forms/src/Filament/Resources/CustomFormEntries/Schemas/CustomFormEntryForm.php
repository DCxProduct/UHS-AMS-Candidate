<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas;

use Chanthoeun\FilamentCustomForms\CustomFormPlugin;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step as WizardStep;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CustomFormEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        $livewire = $schema->getLivewire();

        $preselectedFormId = property_exists($livewire, 'form_id') && $livewire->form_id
            ? $livewire->form_id
            : (request()->query('form_id') ?? request()->input('tableFilters.custom_form_id.value'));

        return $schema
            ->components([
                Select::make('custom_form_id')
                    ->label(__('filament-custom-forms::fcf.form.single'))
                    ->options(CustomForm::where('is_active', true)->whereNotNull('name')->pluck('name', 'id'))
                    ->required()
                    ->default($preselectedFormId)
                    ->hidden(fn () => ! empty($preselectedFormId))
                    ->live()
                    ->columnSpanFull(),

                Grid::make()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(function (Get $get, ?Model $record) use ($preselectedFormId, $livewire) {
                        $formId = $get('custom_form_id') ?? $record?->custom_form_id;

                        if (! $formId && $preselectedFormId) {
                            $formId = $preselectedFormId;
                        }

                        if (! $formId) {
                            return [];
                        }

                        $customForm = CustomForm::find($formId);

                        if (! $customForm) {
                            return [];
                        }

                        $rootFields = $customForm->fields()->roots()->get();

                        $isLocked = method_exists($livewire, 'isLockedForEditing')
                            && $livewire->isLockedForEditing();

                        return self::getFields($rootFields, $isLocked);
                    })
                    ->columns(2),
            ]);
    }

    protected static function getFields($fields, bool $isLocked = false): array
    {
        $components = [];

        foreach ($fields as $fieldModel) {
            $type = $fieldModel->type;
            $options = $fieldModel->options ?? [];
            $isHiddenLabel = $options['is_hidden_label'] ?? false;
            $component = null;

            if ($type === 'section') {
                $component = Section::make($isHiddenLabel ? null : $fieldModel->label)
                    ->schema(self::getFields($fieldModel->children, $isLocked))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'grid') {
                $component = Grid::make($options['columns'] ?? 2)
                    ->schema(self::getFields($fieldModel->children, $isLocked));
            } elseif ($type === 'fieldset') {
                $component = Fieldset::make($isHiddenLabel ? null : $fieldModel->label)
                    ->schema(self::getFields($fieldModel->children, $isLocked))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'wizard') {
                $steps = [];

                $hasContainers = $fieldModel->children->contains(function ($child) {
                    return in_array($child->type, ['section', 'fieldset', 'grid'], true);
                });

                if ($hasContainers) {
                    foreach ($fieldModel->children as $child) {
                        $steps[] = WizardStep::make($child->label)
                            ->schema(self::getFields(collect([$child]), $isLocked));
                    }
                } else {
                    $step = WizardStep::make($fieldModel->label)
                        ->schema(self::getFields($fieldModel->children, $isLocked));

                    $wizardOpts = $fieldModel->options ?? [];

                    if (! empty($wizardOpts['columns'])) {
                        $step->columns($wizardOpts['columns']);
                    }

                    $steps[] = $step;
                }

                $component = Wizard::make()->schema($steps);
            } elseif ($type === 'repeater') {
                $component = \Filament\Forms\Components\Repeater::make("data.{$fieldModel->name}")
                    ->label($fieldModel->label);

                if (! empty($options['is_table'])) {
                    $headers = [];

                    foreach ($fieldModel->children as $child) {
                        $headers[] = \Filament\Forms\Components\Repeater\TableColumn::make($child->label ?? $child->name);
                    }

                    $component->table($headers);

                    $fields = self::getFields($fieldModel->children, $isLocked);

                    foreach ($fields as $field) {
                        $field->hiddenLabel();
                    }

                    $component->schema($fields);
                } else {
                    $component->schema(self::getFields($fieldModel->children, $isLocked))
                        ->columns($options['columns'] ?? 1);
                }

                if (! empty($options['is_compact'])) {
                    $component->compact();
                }

                if ($fieldModel->required) {
                    $component->required();
                }

                if ($isLocked && method_exists($component, 'disabled')) {
                    $component->disabled();
                }
            } else {
                $name = $fieldModel->name;
                $label = $fieldModel->label;
                $required = $fieldModel->required;

                switch ($type) {
                    case 'text':
                    case 'text_input':
                        $component = TextInput::make("data.{$name}");
                        break;

                    case 'textarea':
                        $component = Textarea::make("data.{$name}");
                        break;

                    case 'number':
                    case 'number_input':
                        $isDecimal = $options['is_decimal'] ?? true;
                        $component = TextInput::make("data.{$name}")
                            ->numeric()
                            ->inputMode($isDecimal ? 'decimal' : 'numeric');
                        break;

                    case 'money':
                        $currency = $options['currency'] ?? 'usd';
                        $symbol = match ($currency) {
                            'khr' => '៛',
                            'usd' => '$',
                            default => '$',
                        };

                        $component = TextInput::make("data.{$name}")
                            ->numeric()
                            ->prefix($symbol)
                            ->inputMode('decimal');
                        break;

                    case 'date_picker':
                        $component = DatePicker::make("data.{$name}");
                        break;

                    case 'time_picker':
                        $component = TimePicker::make("data.{$name}")
                            ->seconds(false);
                        break;

                    case 'email':
                        $component = TextInput::make("data.{$name}")->email();
                        break;

                    case 'phone':
                        if (class_exists(\Ysfkaya\FilamentPhoneInput\Forms\PhoneInput::class)) {
                            $component = \Ysfkaya\FilamentPhoneInput\Forms\PhoneInput::make("data.{$name}");
                        } else {
                            $component = TextInput::make("data.{$name}")->tel();
                        }
                        break;

                    case 'password':
                        $component = TextInput::make("data.{$name}")->password();
                        break;

                    case 'boolean':
                        $component = Toggle::make("data.{$name}");

                        if ($options['default'] ?? false) {
                            $component->default(true);
                        }
                        break;

                    case 'image':
                    case 'file_upload':
                        $component = FileUpload::make("data.{$name}")
                            ->disk(CustomFormPlugin::get()->getUploadDisk())
                            ->directory(CustomFormPlugin::get()->getUploadDirectory())
                            ->visibility(CustomFormPlugin::get()->getUploadVisibility());
                        break;

                    case 'select':
                    case 'select_dropdown':
                        $component = Select::make("data.{$name}")
                            ->options($options['choices'] ?? []);
                        break;
                }

                if ($component) {
                    $component->label($label);

                    $placeholder = self::resolvePlaceholder($fieldModel, $options);

                    if (filled($placeholder) && method_exists($component, 'placeholder')) {
                        $component->placeholder($placeholder);
                    }

                    if ($required) {
                        $component->required();
                    }

                    if ($isLocked && method_exists($component, 'disabled')) {
                        $component->disabled();
                    }

                    if ($isHiddenLabel) {
                        $component->hiddenLabel();
                    }

                    if (($options['is_hidden_on_view'] ?? false)) {
                        $component->hiddenOn('view');
                    }

                    if (! empty($options['is_revealable']) && method_exists($component, 'revealable')) {
                        $component->revealable();
                    }

                    if (! empty($options['image_editor']) && method_exists($component, 'imageEditor')) {
                        $component->imageEditor();
                    }

                    if (! empty($options['is_copyable']) && method_exists($component, 'copyable')) {
                        $component->copyable();
                    }
                }
            }

            if ($component) {
                if ($options['column_span_full'] ?? false) {
                    $component->columnSpanFull();
                } elseif (! empty($options['column_span'])) {
                    $component->columnSpan($options['column_span']);
                }

                $components[] = $component;
            }
        }

        return $components;
    }

    protected static function resolvePlaceholder(object $fieldModel, array $options): ?string
    {
        $locale = strtolower((string) app()->getLocale());

        $placeholder = in_array($locale, ['km', 'kh'], true)
            ? ($options['placeholder_km'] ?? $options['placeholder'] ?? $fieldModel->placeholder ?? null)
            : ($options['placeholder_en'] ?? $options['placeholder'] ?? $fieldModel->placeholder ?? null);

        if (! is_string($placeholder)) {
            return null;
        }

        $placeholder = trim($placeholder);

        return $placeholder !== '' ? $placeholder : null;
    }
}
