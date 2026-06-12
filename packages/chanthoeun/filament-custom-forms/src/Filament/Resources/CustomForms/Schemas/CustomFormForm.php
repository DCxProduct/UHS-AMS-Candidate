<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('filament-custom-forms::fcf.form.name'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($set, $state) => $set('slug', \Illuminate\Support\Str::slug($state)))
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->label(__('filament-custom-forms::fcf.form.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->label(__('filament-custom-forms::fcf.form.is_active'))
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
                        ->blocks(self::getFormBlocks(includeLayouts: false)) // Prevent infinite nesting for simplicity
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
                        ->blocks(self::getFormBlocks(includeLayouts: false))
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
                        ->blocks(self::getFormBlocks(includeLayouts: false))
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
                        ->blocks(self::getFormBlocks(includeLayouts: false))
                ]);
        }

        return $blocks;
    }
}
