<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';
    protected static bool $isLazy = false;

    private const CONTAINER_TYPES = [
        'step',
        'section',
        'grid',
        'fieldset',
        'repeater',
        'wizard',
    ];

    private const CHOICE_TYPES = [
        'select',
        'select_dropdown',
        'radio',
        'checkbox_list',
        'multi_select',
    ];

    private const PARENT_FORM_TYPE = '__parent_form';
    private const FORM_TARGET_PREFIX = 'form:';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        \Filament\Forms\Components\ToggleButtons::make('creation_mode')
                            ->hiddenLabel()
                            ->options([
                                'creating' => __('filament-custom-forms::fcf.admin.creating_tab'),
                                'selection' => __('filament-custom-forms::fcf.admin.selection_tab'),
                            ])
                            ->default('creating')
                            ->inline()
                            ->grouped()
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->hidden(fn (?object $record = null): bool => filled($record)),

                        \Filament\Forms\Components\Select::make('parent_id')
                            ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                            ->options(function ($livewire) {
                                return $livewire->getOwnerRecord()->fields()
                                    ->whereIn('type', self::CONTAINER_TYPES)
                                    ->orderBy('sort')
                                    ->get()
                                    ->mapWithKeys(fn ($field) => [
                                        $field->id => self::localeText($field->label ?? $field->name),
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Forms\Components\TextInput::make('name')
                            ->label(__('filament-custom-forms::fcf.field.name'))
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, $livewire) => $rule->where('custom_form_id', $livewire->getOwnerRecord()->id)
                            )
                            ->helperText(__('filament-custom-forms::fcf.builder.fields.name_help'))
                            ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Forms\Components\Select::make('type')
                            ->label(__('filament-custom-forms::fcf.field.type'))
                            ->options([
                                'Container' => [
                                    'step' => 'Step',
                                    'section' => __('filament-custom-forms::fcf.builder.blocks.section'),
                                    'grid' => __('filament-custom-forms::fcf.builder.blocks.grid'),
                                    'fieldset' => __('filament-custom-forms::fcf.builder.blocks.fieldset'),
                                    'repeater' => __('filament-custom-forms::fcf.builder.blocks.repeater'),
                                    'wizard' => __('filament-custom-forms::fcf.builder.blocks.wizard'),
                                ],
                                'Fields' => [
                                    'text_input' => __('filament-custom-forms::fcf.builder.blocks.text_input'),
                                    'textarea' => __('filament-custom-forms::fcf.builder.blocks.textarea'),
                                    'email' => __('filament-custom-forms::fcf.builder.blocks.email'),
                                    'number_input' => __('filament-custom-forms::fcf.builder.blocks.number_input'),
                                    'money' => __('filament-custom-forms::fcf.builder.blocks.money'),
                                    'date_picker' => __('filament-custom-forms::fcf.builder.blocks.date_picker'),
                                    'time_picker' => __('filament-custom-forms::fcf.builder.blocks.time_picker'),
                                    'boolean' => __('filament-custom-forms::fcf.builder.blocks.boolean'),
                                    'select_dropdown' => 'Select Dropdown',
                                    'radio' => 'Radio',
                                    'checkbox' => 'Checkbox',
                                    'checkbox_list' => 'Checkbox List',
                                    'multi_select' => 'Multi Select',
                                    'info' => 'Info',
                                    'image' => __('filament-custom-forms::fcf.builder.blocks.image'),
                                    'password' => __('filament-custom-forms::fcf.builder.blocks.password'),
                                    'phone' => __('filament-custom-forms::fcf.builder.blocks.phone'),
                                ],
                            ])
                            ->default('text_input')
                            ->native()
                            ->columnSpan(2)
                            ->live()
                            ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get)),

                        \Filament\Forms\Components\Toggle::make('required')
                            ->label(__('filament-custom-forms::fcf.field.is_required'))
                            ->default(false)
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)),

                        \Filament\Forms\Components\Toggle::make('options.is_field_input_enabled')
                            ->label(__('filament-custom-forms::fcf.admin.field_input'))
                            ->default(true)
                            ->afterStateHydrated(function ($component, $state, $record): void {
                                if (! filled($record)) {
                                    return;
                                }

                                $component->state(self::isFieldInputEnabled($record->options ?? []));
                            })
                            ->visible(fn ($get, ?object $record = null): bool => (filled($record) || self::isCreatingMode($get))
                                && ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)
                                && (string) $get('type') !== 'info'),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.field_label_section'))
                            ->columns(2)
                            ->columnSpanFull()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('label_en')
                                    ->label(__('filament-custom-forms::fcf.admin.label_english'))
                                    ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'en'));
                                    }),

                                \Filament\Forms\Components\TextInput::make('label_km')
                                    ->label(__('filament-custom-forms::fcf.admin.label_khmer'))
                                    ->required(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'km'));
                                    }),

                                \Filament\Forms\Components\Select::make('options.text_format')
                                    ->label(__('filament-custom-forms::fcf.admin.text_format'))
                                    ->options(self::textFormatOptions())
                                    ->default('normal')
                                    ->native(false),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.dynamic_form_type_field'))
                            ->columnSpanFull()
                            ->columns(1)
                            ->visible(function ($livewire): bool {
                                $owner = $livewire->getOwnerRecord();
                                return $owner && in_array($owner->menu_placement, ['sidebar', 'sub_item'], true);
                            })
                            ->components([
                                \Filament\Forms\Components\CheckboxList::make('options.visible_when.values')
                                    ->label(__('filament-custom-forms::fcf.field.form_type'))
                                    ->options(fn (): array => self::dynamicFormTargetOptions())
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $oldValue = data_get($record?->options, 'visible_when.value');

                                        if (is_string($oldValue) && str_starts_with($oldValue, self::FORM_TARGET_PREFIX)) {
                                            $component->state([$oldValue]);

                                            return;
                                        }

                                        $ownerForm = $record?->form;

                                        if ($ownerForm && $ownerForm->menu_placement === 'sidebar') {
                                            $component->state([self::formTargetValue($ownerForm->id)]);

                                            return;
                                        }

                                        if (
                                            $ownerForm
                                            && $ownerForm->menu_placement === 'sub_item'
                                            && filled($ownerForm->sub_item_type)
                                        ) {
                                            $component->state([self::formTargetValue($ownerForm->id)]);
                                        }
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        if (filled($state)) {
                                            $set('options.visible_when.field', 'form_selection');
                                            $set('options.visible_when.operator', '=');
                                        } else {
                                            $set('options.visible_when.field', null);
                                            $set('options.visible_when.operator', null);
                                        }
                                    }),

                                \Filament\Forms\Components\Hidden::make('options.visible_when.field')
                                    ->default('form_selection'),

                                \Filament\Forms\Components\Hidden::make('options.visible_when.operator')
                                    ->default('='),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.multiple_creating_selection_field'))
                            ->columnSpanFull()
                            ->columns(2)
                            ->visible(fn ($get, ?object $record = null): bool => blank($record) && self::isSelectionMode($get))
                            ->components([
                                \Filament\Forms\Components\Select::make('options.visible_when.fields')
                                    ->label(__('filament-custom-forms::fcf.admin.select_field'))
                                    ->options(function ($livewire): array {
                                        $ownerForm = $livewire->getOwnerRecord();

                                        $existingNames = $ownerForm
                                            ? $ownerForm->fields()->pluck('name')->filter()->toArray()
                                            : [];

                                        return \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                                            ->whereNotIn('type', self::CONTAINER_TYPES)
                                            ->whereNotIn('name', $existingNames)
                                            ->whereNotNull('name')
                                            ->where('name', '!=', '')
                                            ->orderBy('custom_form_id')
                                            ->orderBy('sort')
                                            ->get()
                                            ->unique('name')
                                            ->mapWithKeys(fn ($field) => [
                                                $field->name => $field->name . ' - ' . self::localeText($field->label ?? $field->name),
                                            ])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->multiple()
                                    ->native(false)
                                    ->live()
                                    ->columnSpanFull()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        $set('options.visible_when.operator', 'in');
                                    }),
                            ]),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.configuration'))
                            ->columnSpanFull()
                            ->visible(fn ($get, ?object $record = null): bool => filled($record) || self::isCreatingMode($get))
                            ->components([
                                \Filament\Forms\Components\Select::make('options.columns')
                                    ->label(__('filament-custom-forms::fcf.admin.columns'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CONTAINER_TYPES, true))
                                    ->options([
                                        '1' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                                        '2' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                                        '3' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                                        '4' => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                                    ])
                                    ->default('2')
                                    ->native(false),

                                \Filament\Forms\Components\Repeater::make('options.choice_rows')
                                    ->label(__('filament-custom-forms::fcf.admin.select_options'))
                                    ->visible(function ($get): bool {
                                        return in_array((string) $get('type'), self::CHOICE_TYPES, true)
                                            && blank($get('options.geo_location_type'))
                                            && blank($get('options.data_source_table'));
                                    })
                                    ->columns(3)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('key')
                                            ->label(__('filament-custom-forms::fcf.admin.key'))
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_en')
                                            ->label(__('filament-custom-forms::fcf.admin.label_english'))
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_km')
                                            ->label(__('filament-custom-forms::fcf.admin.label_khmer'))
                                            ->required(),
                                    ])
                                    ->afterStateHydrated(function ($component, $state, $record): void {
                                        if (filled($state)) {
                                            return;
                                        }

                                        $options = self::normalizeOptions($record?->options ?? []);
                                        $choices = $options['choices'] ?? [];

                                        $component->state(self::choicesToRows(is_array($choices) ? $choices : []));
                                    })
                                    ->dehydrated(true)
                                    ->helperText(__('filament-custom-forms::fcf.admin.choice_rows_help')),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_type')
                                    ->default(''),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_value_column')
                                    ->default('id'),

                                \Filament\Forms\Components\Select::make('options.data_source_table')
                                    ->label(__('filament-custom-forms::fcf.admin.data_source_table'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.select_an_option'))
                                    ->options(self::dataSourceTableOptions())
                                    ->default('')
                                    ->native(false)
                                    ->searchable()
                                    ->live()
                                    ->afterStateUpdated(function ($state, $set): void {
                                        $geoType = match ((string) $state) {
                                            'geo_province' => 'province',
                                            'geo_district' => 'district',
                                            'geo_commune' => 'commune',
                                            'geo_village' => 'village',
                                            default => '',
                                        };

                                        $set('options.geo_location_type', $geoType);

                                        if (filled($geoType)) {
                                            $set('options.geo_location_value_column', 'id');
                                            $set('options.data_source_label_column', '');
                                        } else {
                                            $set('options.geo_location_parent_field', null);
                                            $set('options.geo_location_child_fields', null);
                                            $set('options.geo_location_value_column', 'id');
                                        }
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.data_source_helper'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CHOICE_TYPES, true)),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_parent_field')
                                    ->label(__('filament-custom-forms::fcf.admin.parent_location_field_name'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.parent_location_field_placeholder'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return in_array($source, ['geo_district', 'geo_commune', 'geo_village'], true)
                                            || in_array((string) $get('options.geo_location_type'), ['district', 'commune', 'village'], true);
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.parent_location_field_helper')),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_child_fields')
                                    ->label(__('filament-custom-forms::fcf.admin.child_location_field_names'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.child_location_field_placeholder'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return str_starts_with($source, 'geo_')
                                            || filled($get('options.geo_location_type'));
                                    })
                                    ->helperText(__('filament-custom-forms::fcf.admin.child_location_field_helper')),

                                \Filament\Forms\Components\Select::make('options.data_source_label_column')
                                    ->label(__('filament-custom-forms::fcf.admin.data_source_label_column'))
                                    ->placeholder(__('filament-custom-forms::fcf.admin.select_an_option'))
                                    ->options([
                                        '' => __('filament-custom-forms::fcf.admin.auto_label'),
                                        'name_kh' => 'name_kh',
                                        'name_en' => 'name_en',
                                        'name_khmer' => 'name_khmer',
                                        'name_latin' => 'name_latin',
                                        'title_kh' => 'title_kh',
                                        'title_en' => 'title_en',
                                        'code' => 'code',
                                    ])
                                    ->default('')
                                    ->native(false)
                                    ->searchable()
                                    ->helperText(__('filament-custom-forms::fcf.admin.data_source_label_helper'))
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return filled($source) && ! str_starts_with($source, 'geo_');
                                    }),

                                \Filament\Forms\Components\KeyValue::make('options.column_span')
                                    ->label(__('filament-custom-forms::fcf.admin.column_span_responsive'))
                                    ->helperText(__('filament-custom-forms::fcf.admin.column_span_responsive_helper'))
                                    ->keyLabel(__('filament-custom-forms::fcf.admin.breakpoint'))
                                    ->valueLabel(__('filament-custom-forms::fcf.admin.columns_value'))
                                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : (empty($state) ? [] : ['default' => $state])),

                                \Filament\Forms\Components\Toggle::make('options.column_span_full')
                                    ->label(__('filament-custom-forms::fcf.admin.full_width'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.image_editor')
                                    ->label(__('filament-custom-forms::fcf.admin.enable_image_editor'))
                                    ->visible(fn ($get): bool => $get('type') === 'image'),

                                \Filament\Forms\Components\Toggle::make('options.is_revealable')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_password_reveal'))
                                    ->visible(fn ($get): bool => $get('type') === 'password'),

                                \Filament\Forms\Components\Toggle::make('options.is_copyable')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_copy'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['text_input', 'email', 'number_input', 'phone'], true)),

                                \Filament\Forms\Components\Toggle::make('options.is_decimal')
                                    ->label(__('filament-custom-forms::fcf.admin.allow_decimals'))
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['number_input', 'number', 'money'], true))
                                    ->default(true),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_label')
                                    ->label(__('filament-custom-forms::fcf.admin.hide_label'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_on_view')
                                    ->label(__('filament-custom-forms::fcf.admin.hide_in_view'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_table')
                                    ->label(__('filament-custom-forms::fcf.admin.use_table_layout'))
                                    ->visible(fn ($get): bool => $get('type') === 'repeater')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_compact')
                                    ->label(__('filament-custom-forms::fcf.admin.compact_mode'))
                                    ->visible(fn ($get): bool => $get('type') === 'repeater')
                                    ->default(false),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-custom-forms::fcf.field.name'))
                    ->searchable(),

                TextColumn::make('label_en')
                    ->label(app()->getLocale() === 'km' ? 'ស្លាក (EN)' : 'Label (EN)')
                    ->state(fn ($record): string => self::getLangValue($record->label, 'en'))
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where('label', 'ilike', "%{$search}%");
                    }),

                TextColumn::make('label_km')
                    ->label(app()->getLocale() === 'km' ? 'ស្លាក (KM)' : 'Label (KM)')
                    ->state(fn ($record): string => self::getLangValue($record->label, 'km'))
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder {
                        return $query->where('label', 'ilike', "%{$search}%");
                    }),


                ToggleColumn::make('required')
                    ->label(__('filament-custom-forms::fcf.field.is_required'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    )),

                ToggleColumn::make('field_input')
                    ->label(__('filament-custom-forms::fcf.admin.field_input'))
                    ->state(fn ($record): bool => self::isFieldInputEnabled($record->options ?? []))
                    ->updateStateUsing(function ($record, $state): bool {
                        $options = self::normalizeOptions($record->options ?? []);
                        $options['is_field_input_enabled'] = (bool) $state;

                        $record->update(['options' => $options]);

                        return (bool) $state;
                    })
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    ) || (string) $record->type === 'info'),

                TextColumn::make('type')
                    ->label(__('filament-custom-forms::fcf.field.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'step' => app()->getLocale() === 'km' ? 'ជំហាន' : 'Step',
                        'section' => app()->getLocale() === 'km' ? 'ផ្នែក' : 'Section',
                        'grid' => app()->getLocale() === 'km' ? 'ក្រឡាចត្រង្គ' : 'Grid',
                        'fieldset' => 'Fieldset',
                        'repeater' => app()->getLocale() === 'km' ? 'ឧបករណ៍ផ្ទួន' : 'Repeater',
                        'wizard' => app()->getLocale() === 'km' ? 'អ្នកជំនួយការ' : 'Wizard',
                        'text_input' => app()->getLocale() === 'km' ? 'ប្រអប់បញ្ចូលអត្ថបទ' : 'Text Input',
                        'textarea' => app()->getLocale() === 'km' ? 'តំបន់អត្ថបទ' : 'Textarea',
                        'email' => app()->getLocale() === 'km' ? 'អ៊ីមែល' : 'Email',
                        'number_input' => app()->getLocale() === 'km' ? 'ប្រអប់បញ្ចូលលេខ' : 'Number Input',
                        'money' => app()->getLocale() === 'km' ? 'ប្រអប់បញ្ចូលរូបិយប័ណ្ណ' : 'Money',
                        'date_picker' => app()->getLocale() === 'km' ? 'ការរើសថ្ងៃ' : 'Date Picker',
                        'time_picker' => app()->getLocale() === 'km' ? 'ការរើសម៉ោង' : 'Time Picker',
                        'boolean' => app()->getLocale() === 'km' ? 'ប៊ូតុងបិទបើក' : 'Toggle',
                        'select_dropdown' => app()->getLocale() === 'km' ? 'បញ្ជីរើស (Dropdown)' : 'Select Dropdown',
                        'radio' => app()->getLocale() === 'km' ? 'ប៊ូតុងរើស (Radio)' : 'Radio',
                        'checkbox' => app()->getLocale() === 'km' ? 'ប្រអប់ជ្រើសរើស' : 'Checkbox',
                        'checkbox_list' => app()->getLocale() === 'km' ? 'បញ្ជីប្រអប់ជ្រើសរើស' : 'Checkbox List',
                        'multi_select' => app()->getLocale() === 'km' ? 'ជ្រើសរើសច្រើន' : 'Multi Select',
                        'info' => app()->getLocale() === 'km' ? 'ព័ត៌មាន' : 'Info',
                        'image' => app()->getLocale() === 'km' ? 'រូបភាព' : 'Image',
                        'password' => app()->getLocale() === 'km' ? 'ពាក្យសម្ងាត់' : 'Password',
                        'phone' => app()->getLocale() === 'km' ? 'ទូរស័ព្ទ' : 'Phone',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'step', 'section', 'grid', 'fieldset', 'repeater', 'wizard' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('options.visible_when.value')
                    ->label(app()->getLocale() === 'km' ? 'ទម្រង់ប្រភេទ' : 'Form Type')
                    ->state(function ($record): mixed {
                        $state = data_get($record->options, 'visible_when.value');

                        if (blank($state) || $state === false || $state === 'false') {
                            $ownerForm = $record->form;

                            if ($ownerForm && $ownerForm->menu_placement === 'sub_item' && filled($ownerForm->sub_item_type)) {
                                return $ownerForm->sub_item_type;
                            }

                            if ($ownerForm && $ownerForm->menu_placement === 'sidebar') {
                                return self::PARENT_FORM_TYPE;
                            }
                        }

                        return $state;
                    })
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        if (blank($state) || $state === false || $state === 'false') {
                            return app()->getLocale() === 'km' ? '—' : '—';
                        }

                        $stateString = (string) $state;

                        if ($stateString === self::PARENT_FORM_TYPE) {
                            return app()->getLocale() === 'km' ? 'ទម្រង់មេ' : 'Parent Form';
                        }

                        $ownerForm = $record->form;

                        if ($ownerForm) {
                            $rootFormId = $ownerForm->id;
                            if ($ownerForm->menu_placement === 'sub_item' && filled($ownerForm->custom_form_id)) {
                                $rootFormId = $ownerForm->custom_form_id;
                            }

                            $selectionField = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                                ->where('custom_form_id', $rootFormId)
                                ->where('name', 'form_selection')
                                ->first();

                            if ($selectionField && !blank($selectionField->options)) {
                                $config = is_string($selectionField->options)
                                    ? json_decode($selectionField->options, true)
                                    : $selectionField->options;

                                $choices = $config['choices'] ?? [];
                                if (is_array($choices)) {
                                    foreach ($choices as $value => $label) {
                                        if (is_array($label) && isset($label['value']) && (string)$label['value'] === $stateString) {
                                            return self::localeText($label['label'] ?? $label['value']);
                                        }
                                        if ((string)$value === $stateString) {
                                            return self::localeText($label);
                                        }
                                    }
                                }
                            }
                        }

                        return match ($stateString) {
                            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
                            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
                            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
                            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
                            'exam' => app()->getLocale() === 'km' ? 'ការប្រឡង' : 'Exam',
                            'national_candidate' => app()->getLocale() === 'km' ? 'បេក្ខជនថ្នាក់ជាតិ' : 'National Candidate',
                            'general_candidate' => app()->getLocale() === 'km' ? 'បេក្ខជនទូទៅ' : 'General Candidate',
                            'continuing_candidate' => app()->getLocale() === 'km' ? 'បេក្ខជនបន្តសិក្សា' : 'Continuing Candidate',
                            'master_candidate' => app()->getLocale() === 'km' ? 'បេក្ខជនថ្នាក់អនុបណ្ឌិត' : 'Master Candidate',
                            default => ucfirst($stateString),
                        };
                    })
                    ->color(fn ($state): string => match ($state) {
                        'associate' => 'gray',
                        'bachelor' => 'info',
                        'master' => 'warning',
                        'phd' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('parent.label')
                    ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                    ->badge()
                    ->default('—')
                    ->formatStateUsing(fn ($state): string => self::localeText($state)),
            ])
            ->reorderable('sort')
            ->defaultSort('sort', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->modalHeading(__('filament-custom-forms::fcf.admin.create_custom_form_field'))
                    ->using(function (array $data) {
                        $createdRecord = null;

                        foreach ($this->prepareFieldDataList($data) as $fieldData) {
                            $record = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::create($fieldData);

                            if (! $createdRecord) {
                                $createdRecord = $record;
                            }
                        }

                        return $createdRecord;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(__('filament-custom-forms::fcf.admin.edit_custom_form_field'))
                    ->using(function ($record, array $data) {
                        $record->update($this->prepareFieldData($data));

                        return $record;
                    }),

                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function prepareFieldData(array $data): array
    {
        $ownerForm = $this->getOwnerRecord();

        $labelEn = trim((string) ($data['label_en'] ?? ''));
        $labelKm = trim((string) ($data['label_km'] ?? ''));

        $data['label'] = json_encode([
            'en' => $labelEn !== '' ? $labelEn : $labelKm,
            'km' => $labelKm !== '' ? $labelKm : $labelEn,
        ], JSON_UNESCAPED_UNICODE);

        unset($data['label_en'], $data['label_km']);

        $choiceRows = data_get($data, 'options.choice_rows');

        if (is_array($choiceRows)) {
            $choices = [];

            foreach ($choiceRows as $row) {
                $value = trim((string) ($row['key'] ?? ''));

                if ($value === '') {
                    continue;
                }

                $en = trim((string) ($row['label_en'] ?? ''));
                $km = trim((string) ($row['label_km'] ?? ''));

                $choices[$value] = [
                    'en' => $en !== '' ? $en : $km,
                    'km' => $km !== '' ? $km : $en,
                ];
            }

            data_set($data, 'options.choices', $choices);
            data_forget($data, 'options.choice_rows');
        }

        $conditionalField = data_get($data, 'options.conditional_when.field');
        $conditionalValues = data_get($data, 'options.conditional_when.values', []);

        if (! is_array($conditionalValues)) {
            $conditionalValues = filled($conditionalValues) ? [$conditionalValues] : [];
        }

        $conditionalValues = array_values(array_filter($conditionalValues, fn ($value): bool => filled($value)));

        if (filled($conditionalField) && ! empty($conditionalValues)) {
            data_set($data, 'options.visible_when.field', $conditionalField);
            data_set($data, 'options.visible_when.operator', 'in');
            data_set($data, 'options.visible_when.value', $conditionalValues);
            data_forget($data, 'options.visible_when.values');
            data_forget($data, 'options.conditional_when');

            $data['custom_form_id'] = $ownerForm->id;

            return $data;
        }

        data_forget($data, 'options.conditional_when');

        $selectedValues = data_get($data, 'options.visible_when.values');
        if (is_array($selectedValues)) {
            $selectedTypes = array_values(array_filter(
                $selectedValues,
                fn ($val) => filled($val) && $val !== false && $val !== 'false'
            ));

            $selectedType = collect($selectedTypes)
                ->first(function ($selectedType) use ($ownerForm): bool {
                    $targetForm = self::formTargetFromValue($selectedType);

                    return $targetForm && (string) $targetForm->id !== (string) $ownerForm->id;
                }) ?? head($selectedTypes);

            data_forget($data, 'options.visible_when.values');
        } else {
            $selectedType = data_get($data, 'options.visible_when.value');
        }

        $rootFormId = $ownerForm->id;

        if ($ownerForm->menu_placement === 'sub_item' && filled($ownerForm->custom_form_id)) {
            $rootFormId = $ownerForm->custom_form_id;
        }

        $targetForm = self::formTargetFromValue($selectedType);

        if ($targetForm) {
            $data['custom_form_id'] = $targetForm->id;

            if ((string) $targetForm->id !== (string) $ownerForm->id) {
                $data['parent_id'] = null;
            }

            if ($targetForm->menu_placement === 'sub_item' && filled($targetForm->sub_item_type)) {
                data_set($data, 'options.visible_when.field', 'form_selection');
                data_set($data, 'options.visible_when.operator', '=');
                data_set($data, 'options.visible_when.value', $targetForm->sub_item_type);
            } else {
                data_forget($data, 'options.visible_when');
            }

            return $data;
        }

        if ($selectedType === self::PARENT_FORM_TYPE) {
            data_forget($data, 'options.visible_when');
            $data['custom_form_id'] = $rootFormId;

            if ((string) $rootFormId !== (string) $ownerForm->id) {
                $data['parent_id'] = null;
            }

            return $data;
        }

        if (blank($selectedType) || $selectedType === false || $selectedType === 'false') {
            if (filled($ownerForm->sub_item_type)) {
                $selectedType = $ownerForm->sub_item_type;

                data_set($data, 'options.visible_when.field', 'form_selection');
                data_set($data, 'options.visible_when.operator', '=');
                data_set($data, 'options.visible_when.value', $selectedType);
            } else {
                data_forget($data, 'options.visible_when');
            }
        } else {
            data_set($data, 'options.visible_when.field', 'form_selection');
            data_set($data, 'options.visible_when.operator', '=');
            data_set($data, 'options.visible_when.value', $selectedType);
        }

        $targetFormId = $ownerForm->id;

        if (filled($selectedType)) {
            $targetForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $rootFormId)
                ->whereRaw('LOWER(sub_item_type) = ?', [
                    strtolower(trim((string) $selectedType)),
                ])
                ->first();

            if ($targetForm) {
                $targetFormId = $targetForm->id;
                $data['parent_id'] = null;
            }
        }

        $data['custom_form_id'] = $targetFormId;

        return $data;
    }

    private static function textFormatOptions(): array
    {
        return [
            'normal' => __('filament-custom-forms::fcf.admin.text_format_options.normal'),
            'h1' => 'H1',
            'h2' => 'H2',
            'h3' => 'H3',
            'h4' => 'H4',
            'h5' => 'H5',
            'h6' => 'H6',
            'h7' => 'H7',
        ];
    }

    private static function dataSourceTableOptions(): array
    {
        return [
            '' => __('filament-custom-forms::fcf.admin.not_data_source_field'),
            'geo_province' => __('filament-custom-forms::fcf.admin.data_source_options.geo_province'),
            'geo_district' => __('filament-custom-forms::fcf.admin.data_source_options.geo_district'),
            'geo_commune' => __('filament-custom-forms::fcf.admin.data_source_options.geo_commune'),
            'geo_village' => __('filament-custom-forms::fcf.admin.data_source_options.geo_village'),
            'academic_levels' => __('filament-custom-forms::fcf.admin.data_source_options.academic_levels'),
            'academic_years' => __('filament-custom-forms::fcf.admin.data_source_options.academic_years'),
            'countries' => __('filament-custom-forms::fcf.admin.data_source_options.countries'),
            'faculties' => __('filament-custom-forms::fcf.admin.data_source_options.faculties'),
            'fiscal_years' => __('filament-custom-forms::fcf.admin.data_source_options.fiscal_years'),
            'programs' => __('filament-custom-forms::fcf.admin.data_source_options.programs'),
        ];
    }

    private static function normalizeOptions(mixed $options): array
    {
        if (is_array($options)) {
            return $options;
        }

        if (is_string($options) && $options !== '') {
            $decoded = json_decode($options, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($options)) {
            return json_decode(json_encode($options), true) ?: [];
        }

        return [];
    }

    private static function isCreatingMode(callable $get): bool
    {
        return ($get('creation_mode') ?? 'creating') !== 'selection';
    }

    private static function isSelectionMode(callable $get): bool
    {
        return ($get('creation_mode') ?? 'creating') === 'selection';
    }

    private static function isFieldInputEnabled(mixed $options): bool
    {
        $options = self::normalizeOptions($options);

        if (! array_key_exists('is_field_input_enabled', $options)) {
            return true;
        }

        return ! in_array($options['is_field_input_enabled'], [false, 'false', 0, '0'], true);
    }

    private static function getLangValue(mixed $value, string $locale): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }

    private static function englishText(mixed $value): string
    {
        return self::getLangValue($value, 'en');
    }

    private static function englishOptions(array $choices): array
    {
        return collect($choices)
            ->mapWithKeys(function ($label, $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::englishText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $key => self::englishText($label),
                ];
            })
            ->toArray();
    }

    private static function choicesToRows(array $choices): array
    {
        $rows = [];

        foreach ($choices as $value => $label) {
            if (is_array($label) && array_key_exists('value', $label)) {
                $rows[] = [
                    'key' => (string) $label['value'],
                    'label_en' => self::getLangValue($label['label'] ?? '', 'en'),
                    'label_km' => self::getLangValue($label['label'] ?? '', 'km'),
                ];

                continue;
            }

            $rows[] = [
                'key' => (string) $value,
                'label_en' => self::getLangValue($label, 'en'),
                'label_km' => self::getLangValue($label, 'km'),
            ];
        }

        return $rows;
    }

    private static function formTargetValue(int|string $formId): string
    {
        return self::FORM_TARGET_PREFIX . $formId;
    }

    private static function formTargetFromValue(mixed $value): ?\Chanthoeun\FilamentCustomForms\Models\CustomForm
    {
        if (! is_string($value) || ! str_starts_with($value, self::FORM_TARGET_PREFIX)) {
            return null;
        }

        $formId = (int) substr($value, strlen(self::FORM_TARGET_PREFIX));

        if ($formId <= 0) {
            return null;
        }

        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('is_active', true)
            ->find($formId);
    }

    private static function dynamicFormTargetOptions(): array
    {
        $forms = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('is_active', true)
            ->whereIn('menu_placement', ['sidebar', 'sub_item'])
            ->orderBy('custom_form_id')
            ->orderBy('id')
            ->get(['id', 'name', 'menu_placement', 'custom_form_id', 'sub_item_type']);

        return $forms
            ->mapWithKeys(fn ($form): array => [
                self::formTargetValue($form->id) => self::localeText($form->name),
            ])
            ->toArray();
    }

    private function prepareFieldDataList(array $data): array
    {
        $selectedFields = data_get($data, 'options.visible_when.fields', []);

        if (! is_array($selectedFields)) {
            $selectedFields = filled($selectedFields) ? [$selectedFields] : [];
        }

        $selectedFields = array_values(array_filter($selectedFields, fn ($value): bool => filled($value)));

        $selectedTypes = data_get($data, 'options.visible_when.values', []);

        if (! is_array($selectedTypes)) {
            $selectedTypes = filled($selectedTypes) ? [$selectedTypes] : [];
        }

        $selectedTypes = array_values(array_filter(
            $selectedTypes,
            fn ($value): bool => filled($value) && $value !== false && $value !== 'false'
        ));

        if (! empty($selectedFields) && blank($data['name'] ?? null)) {
            $ownerForm = $this->getOwnerRecord();
            $sourceFields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                ->whereIn('name', $selectedFields)
                ->whereNotIn('type', self::CONTAINER_TYPES)
                ->orderBy('sort')
                ->get()
                ->unique('name')
                ->keyBy('name');

            $targetForms = empty($selectedTypes)
                ? collect([$ownerForm])
                : collect($selectedTypes)
                    ->map(fn ($selectedType) => self::formTargetFromValue($selectedType))
                    ->filter();

            $items = [];

            foreach ($targetForms as $targetForm) {
                $existingNames = $targetForm->fields()
                    ->pluck('name')
                    ->filter()
                    ->toArray();

                foreach ($selectedFields as $selectedField) {
                    if (in_array($selectedField, $existingNames, true)) {
                        continue;
                    }

                    $field = $sourceFields->get($selectedField);

                    if (! $field) {
                        continue;
                    }

                    $copy = $field->toArray();

                    unset($copy['id'], $copy['created_at'], $copy['updated_at']);

                    $copy['custom_form_id'] = $targetForm->id;
                    $copy['parent_id'] = null;

                    if ($targetForm->menu_placement === 'sub_item' && filled($targetForm->sub_item_type)) {
                        data_set($copy, 'options.visible_when.field', 'form_selection');
                        data_set($copy, 'options.visible_when.operator', '=');
                        data_set($copy, 'options.visible_when.value', $targetForm->sub_item_type);
                    } else {
                        data_forget($copy, 'options.visible_when');
                    }

                    $items[] = $copy;
                    $existingNames[] = $selectedField;
                }
            }

            return $items;
        }

        if (empty($selectedTypes)) {
            return [
                $this->prepareFieldData($data),
            ];
        }

        $items = [];

        foreach ($selectedTypes as $selectedType) {
            $copy = $data;

            data_set($copy, 'options.visible_when.field', 'form_selection');
            data_set($copy, 'options.visible_when.operator', '=');
            data_set($copy, 'options.visible_when.value', $selectedType);
            data_forget($copy, 'options.visible_when.values');

            $items[] = $this->prepareFieldData($copy);
        }

        return $items;
    }

    private static function localeText(mixed $value): string
    {
        return self::getLangValue($value, app()->getLocale());
    }
}
