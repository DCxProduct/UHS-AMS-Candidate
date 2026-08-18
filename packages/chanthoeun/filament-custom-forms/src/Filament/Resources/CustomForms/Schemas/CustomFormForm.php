<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Schemas;

use App\Support\PassedResultMenuOptions;
use App\Support\UserTypeOptions;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms;
use Filament\Forms\Components\Builder as FormBuilder;

class CustomFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-custom-forms::fcf.form.details'))
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('filament-custom-forms::fcf.form.label_english'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.form_name_en'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateHydrated(function ($component, $record): void {
                                $component->state(self::getNameLang($record?->name, 'en'));
                            })
                            ->afterStateUpdated(fn ($set, $state) => $set('slug', \Illuminate\Support\Str::slug($state)))
                            ->dehydrated(false)
                            ->maxLength(255),

                        TextInput::make('name_km')
                            ->label(__('filament-custom-forms::fcf.form.label_khmer'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.form_name_km'))
                            ->required()
                            ->afterStateHydrated(function ($component, $record): void {
                                $component->state(self::getNameLang($record?->name, 'km'));
                            })
                            ->dehydrated(false)
                            ->maxLength(255),

                        Forms\Components\Hidden::make('name')
                            ->dehydrateStateUsing(function ($state, $get): string {
                                return json_encode([
                                    'en' => $get('name_en'),
                                    'km' => $get('name_km'),
                                    'kh' => $get('name_km'),
                                ], JSON_UNESCAPED_UNICODE);
                            }),

                        TextInput::make('slug')
                            ->label(__('filament-custom-forms::fcf.form.slug'))
                            ->placeholder(__('filament-custom-forms::fcf.placeholder.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('menu_placement')
                            ->label(__('filament-custom-forms::fcf.form.menu_placement'))
                            ->options([
                                'sidebar' => __('filament-custom-forms::fcf.menu.sidebar'),
                                'sub_item' => __('filament-custom-forms::fcf.menu.sub_item'),
                            ])
                            ->live()
                            ->afterStateUpdated(function ($set) {
                                $set('parent_sidebar', null);
                                $set('sub_item_type', null);
                                $set('custom_form_id', null);
                            })
                            ->required()
                            ->native(false),

                        Forms\Components\Hidden::make('custom_form_id'),

                        Forms\Components\Select::make('parent_sidebar')
                            ->label(__('filament-custom-forms::fcf.menu.parent_sidebar'))
                            ->options(fn () => CustomForm::query()
                                ->where('menu_placement', 'sidebar')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (CustomForm $form): array => [
                                    self::englishText($form->name) => self::localeText($form->name),
                                ])
                                ->toArray()
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, $set): void {
                                $set('sub_item_type', null);

                                if (blank($state)) {
                                    $set('custom_form_id', null);
                                    return;
                                }

                                $parentForm = CustomForm::query()
                                    ->where(function ($query) use ($state): void {
                                        $query->where('name', $state)
                                            ->orWhere('name', 'like', '%"en":"' . $state . '"%')
                                            ->orWhere('name', 'like', '%"km":"' . $state . '"%')
                                            ->orWhere('name', 'like', '%"kh":"' . $state . '"%');
                                    })
                                    ->first();

                                $set('custom_form_id', $parentForm?->id);
                            })
                            ->visible(fn (Get $get): bool => $get('menu_placement') === 'sub_item')
                            ->required(fn (Get $get): bool => $get('menu_placement') === 'sub_item')
                            ->native(false),

                        Forms\Components\Select::make('sub_item_type')
                            ->label(__('filament-custom-forms::fcf.form.sub_form'))
                            ->options(function (Get $get): array {
                                $parentSidebarName = $get('parent_sidebar');

                                if (blank($parentSidebarName)) {
                                    return [];
                                }

                                $parentForm = CustomForm::query()
                                    ->where(function ($query) use ($parentSidebarName): void {
                                        $query->where('name', $parentSidebarName)
                                            ->orWhere('name', 'like', '%"en":"' . $parentSidebarName . '"%')
                                            ->orWhere('name', 'like', '%"km":"' . $parentSidebarName . '"%')
                                            ->orWhere('name', 'like', '%"kh":"' . $parentSidebarName . '"%');
                                    })
                                    ->first();

                                if (! $parentForm) {
                                    return [];
                                }

                                $field = CustomFormField::query()
                                    ->where('custom_form_id', $parentForm->id)
                                    ->where('name', 'form_selection')
                                    ->where('type', 'select_dropdown')
                                    ->first();

                                if (! $field || blank($field->options)) {
                                    return [];
                                }

                                $config = is_string($field->options)
                                    ? json_decode($field->options, true)
                                    : $field->options;

                                if (! is_array($config)) {
                                    return [];
                                }

                                $choices = $config['choices'] ?? [];

                                if (! is_array($choices)) {
                                    return [];
                                }

                                return self::localeOptions($choices);
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->native(false)
                            ->visible(fn (Get $get): bool =>
                                $get('menu_placement') === 'sub_item'
                                && filled($get('parent_sidebar'))
                            )
                            ->required(fn (Get $get): bool =>
                                $get('menu_placement') === 'sub_item'
                                && filled($get('parent_sidebar'))
                            ),

                        Forms\Components\Select::make('passed_result_menu')
                            ->label(__('filament-custom-forms::fcf.form.passed_result_menu'))
                            ->options(PassedResultMenuOptions::options())
                            ->default(PassedResultMenuOptions::default())
                            ->native(false)
                            ->required()
                            ->visible(fn (Get $get): bool => in_array($get('menu_placement'), ['sidebar', 'sub_item'], true)),

                        Forms\Components\CheckboxList::make('allowed_roles')
                            ->label(__('filament-custom-forms::fcf.form.allowed_roles'))
                            ->options(fn (): array => UserTypeOptions::options())
                            ->columns(2)
                            ->gridDirection('row')
                            ->bulkToggleable()
                            ->columnSpanFull()
                            ->afterStateHydrated(function ($component, $record): void {
                                $availableRoles = array_keys(UserTypeOptions::options());
                                $roles = $record?->allowed_roles;

                                if (is_string($roles)) {
                                    $decoded = json_decode($roles, true);
                                    $roles = is_array($decoded) ? $decoded : [$roles];
                                }

                                $component->state(
                                    collect(is_array($roles) ? $roles : [])
                                        ->map(function ($role) use ($availableRoles): ?string {
                                            $normalized = strtolower(trim((string) $role));

                                            if ($normalized === 'student' && in_array('candidate', $availableRoles, true)) {
                                                return 'candidate';
                                            }

                                            /** @var string|null $matchedRole */
                                            $matchedRole = collect($availableRoles)->first(
                                                fn (string $availableRole): bool => strcasecmp($availableRole, $normalized) === 0
                                            );

                                            return $matchedRole;
                                        })
                                        ->filter(fn (?string $role): bool => filled($role))
                                        ->unique()
                                        ->values()
                                        ->all()
                                );
                            })
                            ->dehydrateStateUsing(fn ($state): array => collect(is_array($state) ? $state : [])
                                ->map(function ($role): ?string {
                                    $normalized = strtolower(trim((string) $role));

                                    if (in_array($normalized, ['student', 'candidate'], true)) {
                                        return null;
                                    }

                                    return filled($normalized) ? $normalized : null;
                                })
                                ->filter(fn ($role): bool => filled($role))
                                ->unique()
                                ->values()
                                ->all()
                            ),

                         Toggle::make('is_active')
                            ->label(__('filament-custom-forms::fcf.form.is_active'))
                            ->default(true)
                            ->required(),

                        Toggle::make('requires_payment')
                            ->label(__('filament-custom-forms::fcf.form.requires_payment'))
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }

    public static function getFormBlocks(bool $includeLayouts = true): array
    {
        $blocks = [
            FormBuilder\Block::make('text_input')
                ->label(__('filament-custom-forms::fcf.builder.blocks.text_input'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required()->helperText(__('filament-custom-forms::fcf.builder.fields.name_help')),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('textarea')
                ->label(__('filament-custom-forms::fcf.builder.blocks.textarea'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('number_input')
                ->label(__('filament-custom-forms::fcf.builder.blocks.number_input'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('money')
                ->label(__('filament-custom-forms::fcf.builder.blocks.money'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('email')
                ->label(__('filament-custom-forms::fcf.builder.blocks.email'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('phone')
                ->label(__('filament-custom-forms::fcf.builder.blocks.phone'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('password')
                ->label(__('filament-custom-forms::fcf.builder.blocks.password'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('date_picker')
                ->label(__('filament-custom-forms::fcf.builder.blocks.date_picker'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('time_picker')
                ->label(__('filament-custom-forms::fcf.builder.blocks.time_picker'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('boolean')
                ->label(__('filament-custom-forms::fcf.builder.blocks.boolean'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('default')->label(__('filament-custom-forms::fcf.builder.fields.default')),
                ]),

            FormBuilder\Block::make('image')
                ->label(__('filament-custom-forms::fcf.builder.blocks.image'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),

            FormBuilder\Block::make('select')
                ->label(__('filament-custom-forms::fcf.builder.blocks.select'))
                ->schema([
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required(),
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    Forms\Components\Repeater::make('options')
                        ->label(__('filament-custom-forms::fcf.builder.fields.choices'))
                        ->schema([
                            Forms\Components\TextInput::make('value')->label(__('filament-custom-forms::fcf.builder.fields.value'))->required(),
                            Forms\Components\TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                        ]),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                ]),
        ];

        if ($includeLayouts) {
            $blocks[] = FormBuilder\Block::make('section')
                ->label(__('filament-custom-forms::fcf.builder.blocks.section'))
                ->schema([
                    TextInput::make('heading')->label(__('filament-custom-forms::fcf.builder.fields.heading')),
                    Forms\Components\Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                            4 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.section_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);

            $blocks[] = FormBuilder\Block::make('grid')
                ->label(__('filament-custom-forms::fcf.builder.blocks.grid'))
                ->schema([
                    Forms\Components\Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                            4 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 4),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.grid_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);

            $blocks[] = FormBuilder\Block::make('fieldset')
                ->label(__('filament-custom-forms::fcf.builder.blocks.fieldset'))
                ->schema([
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.legend'))->required(),
                    Forms\Components\Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                            3 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 3),
                        ])
                        ->default(2),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.fieldset_content'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);

            $blocks[] = FormBuilder\Block::make('repeater')
                ->label(__('filament-custom-forms::fcf.builder.blocks.repeater'))
                ->schema([
                    TextInput::make('label')->label(__('filament-custom-forms::fcf.builder.fields.label'))->required(),
                    TextInput::make('name')->label(__('filament-custom-forms::fcf.builder.fields.name'))->required()->helperText(__('filament-custom-forms::fcf.builder.fields.name_help')),
                    Forms\Components\Select::make('columns')
                        ->label(__('filament-custom-forms::fcf.builder.fields.columns'))
                        ->options([
                            1 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 1),
                            2 => trans_choice('filament-custom-forms::fcf.builder.fields.columns_help', 2),
                        ])
                        ->default(1),
                    Toggle::make('required')->label(__('filament-custom-forms::fcf.builder.fields.required')),
                    FormBuilder::make('schema')
                        ->label(__('filament-custom-forms::fcf.builder.fields.repeater_fields'))
                        ->blocks(self::getFormBlocks(includeLayouts: false)),
                ]);
        }

        return $blocks;
    }

    private static function getNameLang(mixed $value, string $locale): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['en']
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? ''
                );
            }
        }

        return (string) $value;
    }

    private static function localeText(mixed $value): string
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
            $locale = app()->getLocale();

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
            return (string) ($value['en'] ?? $value['km'] ?? $value['kh'] ?? collect($value)->first() ?? '');
        }

        return (string) $value;
    }

    private static function localeOptions(array $choices): array
    {
        return collect($choices)
            ->mapWithKeys(function ($label, $value): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::localeText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $value => self::localeText($label),
                ];
            })
            ->toArray();
    }
}
