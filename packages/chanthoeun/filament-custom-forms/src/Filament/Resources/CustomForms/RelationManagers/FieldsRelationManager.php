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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        \Filament\Forms\Components\Select::make('parent_id')
                            ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                            ->options(function ($livewire) {
                                return $livewire->getOwnerRecord()->fields()
                                    ->whereIn('type', self::CONTAINER_TYPES)
                                    ->orderBy('sort')
                                    ->get()
                                    ->mapWithKeys(fn ($field) => [$field->id => $field->label ?? $field->name]);
                            })
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        \Filament\Forms\Components\TextInput::make('name')
                            ->label(__('filament-custom-forms::fcf.field.name'))
                            ->required()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (\Illuminate\Validation\Rules\Unique $rule, $livewire) => $rule->where('custom_form_id', $livewire->getOwnerRecord()->id)
                            )
                            ->helperText(__('filament-custom-forms::fcf.builder.fields.name_help')),

                        \Filament\Forms\Components\TextInput::make('label')
                            ->label(__('filament-custom-forms::fcf.field.label')),

                        \Filament\Forms\Components\Select::make('type')
                            ->label(__('filament-custom-forms::fcf.field.type'))
                            ->required()
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
                            ->live(),

                        \Filament\Forms\Components\Toggle::make('required')
                            ->label(__('filament-custom-forms::fcf.field.is_required'))
                            ->default(false)
                            ->visible(fn ($get): bool => ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)),

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.field.dynamic_form_type_field'))
                            ->columnSpanFull()
                            ->columns(1)
                            ->components([
                                \Filament\Forms\Components\Select::make('options.visible_when.value')
                                    ->label(__('filament-custom-forms::fcf.field.form_type'))
                                    ->placeholder(__('filament-custom-forms::fcf.field.select_form_type'))
                                    ->options(function ($livewire): array {
                                        $customForm = $livewire->getOwnerRecord();

                                        if (! $customForm) {
                                            return [];
                                        }

                                        $selectionField = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                                            ->where('custom_form_id', $customForm->id)
                                            ->where('name', 'form_selection')
                                            ->first();

                                        if (! $selectionField) {
                                            return [];
                                        }

                                        $choices = data_get($selectionField->options, 'choices') ?? [];

                                        return is_array($choices) ? $choices : [];
                                    })
                                    ->native(false)
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

                        \Filament\Schemas\Components\Section::make(__('filament-custom-forms::fcf.admin.configuration'))
                            ->columnSpanFull()
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

                                \Filament\Forms\Components\KeyValue::make('options.choices')
                                    ->label(__('filament-custom-forms::fcf.admin.select_options'))
                                    ->visible(function ($get): bool {
                                        return in_array((string) $get('type'), self::CHOICE_TYPES, true)
                                            && blank($get('options.geo_location_type'))
                                            && blank($get('options.data_source_table'));
                                    })
                                    ->keyLabel('Key')
                                    ->valueLabel('Value')
                                    ->helperText('Key corresponds to value, Label is displayed text.'),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_type')
                                    ->default(''),

                                \Filament\Forms\Components\Hidden::make('options.geo_location_value_column')
                                    ->default('id'),

                                \Filament\Forms\Components\Select::make('options.data_source_table')
                                    ->label('Data Source Table')
                                    ->placeholder('Select an option')
                                    ->options([
                                        '' => 'Not a data source field',
                                        'geo_province' => 'Geo: Province / Capital',
                                        'geo_district' => 'Geo: District / Khan',
                                        'geo_commune' => 'Geo: Commune / Sangkat',
                                        'geo_village' => 'Geo: Village',
                                        'academic_levels' => 'Academic Levels',
                                        'academic_years' => 'Academic Years',
                                        'countries' => 'Countries',
                                        'faculties' => 'Faculties',
                                        'fiscal_years' => 'Fiscal Years',
                                        'programs' => 'Programs',
                                    ])
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
                                    ->helperText('Use Geo or another master table to load options automatically.')
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CHOICE_TYPES, true)),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_parent_field')
                                    ->label('Parent Location Field Name')
                                    ->placeholder('Example: pob_province_id')
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return in_array($source, ['geo_district', 'geo_commune', 'geo_village'], true)
                                            || in_array((string) $get('options.geo_location_type'), ['district', 'commune', 'village'], true);
                                    })
                                    ->helperText('Example: district parent is province, commune parent is district, village parent is commune.'),

                                \Filament\Forms\Components\TextInput::make('options.geo_location_child_fields')
                                    ->label('Child Location Field Names')
                                    ->placeholder('Example: pob_district_id,pob_commune_id,pob_village_id')
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return str_starts_with($source, 'geo_')
                                            || filled($get('options.geo_location_type'));
                                    })
                                    ->helperText('Optional. When parent changes, these child fields can be reset.'),

                                \Filament\Forms\Components\Select::make('options.data_source_label_column')
                                    ->label('Data Source Label Column')
                                    ->placeholder('Select an option')
                                    ->options([
                                        '' => 'Auto Label',
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
                                    ->helperText('Leave Auto Label to use Khmer first, then English.')
                                    ->visible(function ($get): bool {
                                        $source = (string) $get('options.data_source_table');

                                        return filled($source) && ! str_starts_with($source, 'geo_');
                                    }),

                                \Filament\Forms\Components\KeyValue::make('options.column_span')
                                    ->label('Column Span (Responsive)')
                                    ->helperText('Key: Breakpoint (default, sm, md, lg, xl, 2xl). Value: Columns (1-12, full).')
                                    ->keyLabel('Breakpoint')
                                    ->valueLabel('Columns')
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
                                    ->label('Allow Decimals')
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['number_input', 'number', 'money'], true))
                                    ->default(true),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_label')
                                    ->label('Hide Label')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_on_view')
                                    ->label(__('filament-custom-forms::fcf.admin.hide_in_view'))
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_table')
                                    ->label('Use Table Layout (Simple)')
                                    ->visible(fn ($get): bool => $get('type') === 'repeater')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_compact')
                                    ->label('Compact Mode')
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

                TextColumn::make('label')
                    ->label(__('filament-custom-forms::fcf.field.label'))
                    ->searchable(),

                ToggleColumn::make('required')
                    ->label(__('filament-custom-forms::fcf.field.is_required'))
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    )),

                TextColumn::make('type')
                    ->label(__('filament-custom-forms::fcf.field.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'step', 'section', 'grid', 'fieldset', 'repeater', 'wizard' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('parent.name')
                    ->label(__('filament-custom-forms::fcf.admin.parent_container'))
                    ->badge(),
            ])
            ->reorderable('sort')
            ->defaultSort('sort', 'asc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
