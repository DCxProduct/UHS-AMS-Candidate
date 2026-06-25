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
                            ->label('Parent Container')
                            ->options(function ($livewire) {
                                return $livewire->getOwnerRecord()->fields()
                                    ->whereIn('type', self::CONTAINER_TYPES)
                                    ->orderBy('sort')
                                    ->get()
                                    ->mapWithKeys(fn ($field) => [
                                        $field->id => self::englishText($field->label ?? $field->name),
                                    ]);
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
                            ->label('Required')
                            ->default(false)
                            ->visible(fn ($get): bool => ! in_array((string) $get('type'), self::CONTAINER_TYPES, true)),

                        \Filament\Schemas\Components\Section::make('Field Label')
                            ->columns(2)
                            ->columnSpanFull()
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('label_en')
                                    ->label('English Label')
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'en'));
                                    }),

                                \Filament\Forms\Components\TextInput::make('label_km')
                                    ->label('Khmer Label')
                                    ->afterStateHydrated(function ($component, $record): void {
                                        $component->state(self::getLangValue($record?->label, 'km'));
                                    }),
                            ]),

                        \Filament\Schemas\Components\Section::make('Dynamic Form Type Field')
                            ->columnSpanFull()
                            ->columns(1)
                            ->visible(function ($livewire): bool {
                                return $livewire->getOwnerRecord()?->menu_placement === 'sidebar';
                            })
                            ->components([
                                \Filament\Forms\Components\Select::make('options.visible_when.value')
                                    ->label('Form Type')
                                    ->placeholder('Select form type')
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

                                        return self::englishOptions(is_array($choices) ? $choices : []);
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

                        \Filament\Schemas\Components\Section::make('Configuration')
                            ->columnSpanFull()
                            ->components([
                                \Filament\Forms\Components\Select::make('options.columns')
                                    ->label('Columns')
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), self::CONTAINER_TYPES, true))
                                    ->options([
                                        '1' => '1 column',
                                        '2' => '2 columns',
                                        '3' => '3 columns',
                                        '4' => '4 columns',
                                    ])
                                    ->default('2')
                                    ->native(false),

                                \Filament\Forms\Components\Repeater::make('options.choice_rows')
                                    ->label('Select Options')
                                    ->visible(function ($get): bool {
                                        return in_array((string) $get('type'), self::CHOICE_TYPES, true)
                                            && blank($get('options.geo_location_type'))
                                            && blank($get('options.data_source_table'));
                                    })
                                    ->columns(3)
                                    ->schema([
                                        \Filament\Forms\Components\TextInput::make('value')
                                            ->label('Value')
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_en')
                                            ->label('English')
                                            ->required(),

                                        \Filament\Forms\Components\TextInput::make('label_km')
                                            ->label('Khmer')
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
                                    ->helperText('Value is saved to database. English and Khmer are display labels.'),

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
                                    ->label('Column Span Responsive')
                                    ->helperText('Key: Breakpoint default, sm, md, lg, xl, 2xl. Value: Columns 1-12 or full.')
                                    ->keyLabel('Breakpoint')
                                    ->valueLabel('Columns')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? $state : (empty($state) ? [] : ['default' => $state])),

                                \Filament\Forms\Components\Toggle::make('options.column_span_full')
                                    ->label('Full Width')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.image_editor')
                                    ->label('Enable Image Editor')
                                    ->visible(fn ($get): bool => $get('type') === 'image'),

                                \Filament\Forms\Components\Toggle::make('options.is_revealable')
                                    ->label('Allow Password Reveal')
                                    ->visible(fn ($get): bool => $get('type') === 'password'),

                                \Filament\Forms\Components\Toggle::make('options.is_copyable')
                                    ->label('Allow Copy')
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['text_input', 'email', 'number_input', 'phone'], true)),

                                \Filament\Forms\Components\Toggle::make('options.is_decimal')
                                    ->label('Allow Decimals')
                                    ->visible(fn ($get): bool => in_array((string) $get('type'), ['number_input', 'number', 'money'], true))
                                    ->default(true),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_label')
                                    ->label('Hide Label')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_hidden_on_view')
                                    ->label('Hide In View')
                                    ->default(false),

                                \Filament\Forms\Components\Toggle::make('options.is_table')
                                    ->label('Use Table Layout')
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
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('label_en')
                    ->label('Label (EN)')
                    ->state(fn ($record): string => self::getLangValue($record->label, 'en'))
                    ->searchable(),

                TextColumn::make('label_km')
                    ->label('Label (KM)')
                    ->state(fn ($record): string => self::getLangValue($record->label, 'km'))
                    ->searchable(),


                ToggleColumn::make('required')
                    ->label('Required')
                    ->onColor('success')
                    ->offColor('gray')
                    ->disabled(fn ($record) => in_array(
                        $record->type,
                        self::CONTAINER_TYPES,
                        true
                    )),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'step', 'section', 'grid', 'fieldset', 'repeater', 'wizard' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('options.visible_when.value')
                    ->label('Form Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst((string) $state) : 'All')
                    ->color(fn ($state): string => match ($state) {
                        'associate' => 'gray',
                        'bachelor' => 'info',
                        'master' => 'warning',
                        'phd' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('parent.name')
                    ->label('Parent Container')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => self::englishText($state)),
            ])
            ->reorderable('sort')
            ->defaultSort('sort', 'asc')
            ->headerActions([
                CreateAction::make()
                    ->modalHeading('Create Custom Form Field')
                    ->using(function (array $data) {
                        return \Chanthoeun\FilamentCustomForms\Models\CustomFormField::create(
                            $this->prepareFieldData($data)
                        );
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Edit Custom Form Field')
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
                $value = trim((string) ($row['value'] ?? ''));

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

        $selectedType = data_get($data, 'options.visible_when.value');

        if (blank($selectedType) && filled($ownerForm->sub_item_type)) {
            $selectedType = $ownerForm->sub_item_type;

            data_set($data, 'options.visible_when.field', 'form_selection');
            data_set($data, 'options.visible_when.operator', '=');
            data_set($data, 'options.visible_when.value', $selectedType);
        }

        $targetFormId = $ownerForm->id;

        if (filled($selectedType)) {
            $rootFormId = $ownerForm->id;

            if ($ownerForm->menu_placement === 'sub_item' && filled($ownerForm->custom_form_id)) {
                $rootFormId = $ownerForm->custom_form_id;
            }

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
                ?? $value['en']
                ?? $value['km']
                ?? $value['kh']
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
                    'value' => (string) $label['value'],
                    'label_en' => self::getLangValue($label['label'] ?? '', 'en'),
                    'label_km' => self::getLangValue($label['label'] ?? '', 'km'),
                ];

                continue;
            }

            $rows[] = [
                'value' => (string) $value,
                'label_en' => self::getLangValue($label, 'en'),
                'label_km' => self::getLangValue($label, 'km'),
            ];
        }

        return $rows;
    }
}
