<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Schemas;

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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Chanthoeun\FilamentCustomForms\CustomFormPlugin;

class CustomFormEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        // Attempt to get pre-selected ID from the parent resource URL query (tableFilters) if available
        $preselectedFormId = request()->input('tableFilters.custom_form_id.value');

        return $schema
            ->components([
                Select::make('custom_form_id')
                    ->label(__('filament-custom-forms::fcf.form.single'))
                    ->options(CustomForm::where('is_active', true)->whereNotNull('name')->pluck('name', 'id'))
                    ->required()
                    ->default($preselectedFormId)
                    ->hidden(fn() => !empty($preselectedFormId))
                    ->live()
                    ->columnSpanFull(),
                Grid::make()
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema(function (Get $get, ?Model $record) use ($preselectedFormId) {
                        $formId = $get('custom_form_id') ?? $record?->custom_form_id;

                        // Fallback to pre-selected ID if not set in state (e.g. initial load)
                        if (!$formId && $preselectedFormId) {
                            $formId = $preselectedFormId;
                        }

                        if (!$formId) {
                            return [];
                        }

                        $customForm = CustomForm::find($formId);

                        if (!$customForm) {
                            return [];
                        }

                        // Fetch only root fields, subsequent recursion will lazy load children or we can eager load if needed.
                        // Ideally we should eager load 'children' recursively but simplified for now:
            
                        $rootFields = $customForm->fields()->roots()->get();

                        return self::getFields($rootFields);
                    })
                    ->columns(2)
            ]);
    }

    protected static function getFields($fields): array
    {
        $components = [];

        foreach ($fields as $fieldModel) {
            $type = $fieldModel->type;
            $options = $fieldModel->options ?? [];

            // Handle Hidden Label
            $isHiddenLabel = $options['is_hidden_label'] ?? false;

            $component = null;

            // Handle Layouts
            if ($type === 'section') {
                $component = Section::make($isHiddenLabel ? null : $fieldModel->label) // Use label as heading
                    ->schema(self::getFields($fieldModel->children))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'grid') {
                $component = Grid::make($options['columns'] ?? 2)
                    ->schema(self::getFields($fieldModel->children));
            } elseif ($type === 'fieldset') {
                $component = Fieldset::make($isHiddenLabel ? null : $fieldModel->label)
                    ->schema(self::getFields($fieldModel->children))
                    ->columns($options['columns'] ?? 2);
            } elseif ($type === 'wizard') {
                // Convert children into wizard steps
                // If children are sections, each section becomes a step
                // If children are fields, group them all into a single step
                $steps = [];
                
                // Check if children are sections/containers or actual fields
                $hasContainers = $fieldModel->children->contains(function ($child) {
                    return in_array($child->type, ['section', 'fieldset', 'grid']);
                });
                
                if ($hasContainers) {
                    // Children are sections/containers - each becomes a step
                    foreach ($fieldModel->children as $child) {
                        $stepFields = self::getFields(collect([$child]));
                        $steps[] = WizardStep::make($child->label)
                            ->schema($stepFields);
                    }
                } else {
                    // Children are fields - put them all in a single step
                    $stepFields = self::getFields($fieldModel->children);
                    $step = WizardStep::make($fieldModel->label)
                        ->schema($stepFields);
                    
                    // Apply columns from wizard options
                    $wizardOpts = $fieldModel->options ?? [];
                    if (!empty($wizardOpts['columns'])) {
                        $step->columns($wizardOpts['columns']);
                    }
                    
                    $steps[] = $step;
                }
                
                $component = Wizard::make()
                    ->schema($steps);
            } elseif ($type === 'repeater') {
                $component = \Filament\Forms\Components\Repeater::make("data.{$fieldModel->name}")
                    ->label($fieldModel->label);

                if (!empty($options['is_table'])) {
                    // Table Layout: Headers + Hidden Label Fields
                    $headers = [];
                    foreach ($fieldModel->children as $child) {
                        $label = $child->label ?? $child->name;
                        // Use Fully Qualified Name if importing is ambiguous, assuming it matches ContractForm usage
                        $headers[] = \Filament\Forms\Components\Repeater\TableColumn::make($label);
                    }

                    $component->table($headers);

                    // Fields must hide labels in table mode
                    $fields = self::getFields($fieldModel->children);
                    foreach ($fields as $field) {
                        $field->hiddenLabel();
                    }
                    $component->schema($fields);
                } else {
                    $component->schema(self::getFields($fieldModel->children))
                        ->columns($options['columns'] ?? 1);
                }

                if (!empty($options['is_compact'])) {
                    $component->compact();
                }

                if ($fieldModel->required) {
                    $component->required();
                }
            } else {
                // Handle Fields
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
                        // Handle Enum backed value which is lowercase 'usd'/'khr'
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
                        // Use PhoneInput if available, falling back to TextInput
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
                        $component = FileUpload::make("data.{$name}")
                            ->image() // Enforce image types
                            ->disk(CustomFormPlugin::get()->getUploadDisk())
                            ->directory(CustomFormPlugin::get()->getUploadDirectory())
                            ->visibility(CustomFormPlugin::get()->getUploadVisibility());
                        break;
                    case 'select':
                        $selectOptions = $options['choices'] ?? [];
                        $component = Select::make("data.{$name}")->options($selectOptions);
                        break;
                }

                if ($component) {
                    $component->label($label);

                    if ($required)
                        $component->required();

                    if ($isHiddenLabel) {
                        $component->hiddenLabel();
                    }

                    if (($options['is_hidden_on_view'] ?? false)) {
                        $component->hiddenOn('view');
                    }

                    // Safe Option Application
                    if (!empty($options['is_revealable']) && method_exists($component, 'revealable')) {
                        $component->revealable();
                    }

                    if (!empty($options['image_editor']) && method_exists($component, 'imageEditor')) {
                        $component->imageEditor();
                    }

                    if (!empty($options['is_copyable']) && method_exists($component, 'copyable')) {
                        $component->copyable();
                    }
                }
            }

            if ($component) {
                // Common Layout Options (Applied to BOTH Fields and Layouts)

                // Column Span
                if ($options['column_span_full'] ?? false) {
                    $component->columnSpanFull();
                } elseif (!empty($options['column_span'])) {
                    $component->columnSpan($options['column_span']);
                }

                $components[] = $component;
            }
        }

        return $components;
    }
}
