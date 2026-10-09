<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Schemas;

use App\Models\SmsTemplate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class SmsTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // One card holding the template's parts, like the Custom Forms field editor.
                Section::make()
                    ->schema([
                        // Built-in templates keep their system name; new templates need one.
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->visible(fn (?SmsTemplate $record): bool => ! $record?->isBuiltIn())
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('sms_templates.fields.template_name'))
                                    ->placeholder(__('sms_templates.placeholders.template_name'))
                                    ->required()
                                    ->maxLength(255),

                                // Used automatically when a workflow sends this action's SMS.
                                Select::make('action')
                                    ->label(__('sms_templates.fields.action'))
                                    ->placeholder(__('sms_templates.placeholders.action'))
                                    ->helperText(__('sms_templates.helpers.action'))
                                    ->options(fn (): array => SmsTemplate::actionOptions())
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages([
                                        'unique' => __('sms_templates.validation.action_taken'),
                                    ])
                                    ->native(false),
                            ]),

                        Section::make(__('sms_templates.sections.general'))
                            ->schema([
                                TextInput::make('app_name')
                                    ->label(__('sms_templates.fields.app_name'))
                                    ->helperText(__('sms_templates.helpers.app_name'))
                                    ->required()
                                    ->maxLength(50),
                            ]),

                        Section::make(__('sms_templates.sections.custom_variables'))
                            ->description(__('sms_templates.sections.custom_variables_description'))
                            ->collapsible()
                            ->schema([
                                Repeater::make('custom_variables')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('sms_templates.fields.variable_name'))
                                            ->prefix('{{')
                                            ->suffix('}}')
                                            ->placeholder('hotline')
                                            ->required()
                                            ->maxLength(40)
                                            ->regex('/^[a-z][a-z0-9_]*$/')
                                            ->notIn(fn (?SmsTemplate $record): array => SmsTemplate::builtInVariablesFor($record))
                                            ->distinct()
                                            ->validationMessages([
                                                'regex' => __('sms_templates.validation.variable_name'),
                                                'not_in' => __('sms_templates.validation.variable_reserved'),
                                                'distinct' => __('sms_templates.validation.variable_distinct'),
                                            ])
                                            ->live(onBlur: true),
                                        TextInput::make('value')
                                            ->label(__('sms_templates.fields.value'))
                                            ->required()
                                            ->maxLength(200),
                                    ])
                                    ->columns(2)
                                    ->defaultItems(0)
                                    ->addActionLabel(__('sms_templates.actions.add_variable'))
                                    ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? '{{ '.$state['name'].' }}' : null),
                            ]),

                        Section::make(__('sms_templates.sections.content'))
                            ->description(__('sms_templates.sections.content_description'))
                            ->schema([
                                Textarea::make('body')
                                    ->id('sms-body')
                                    ->label(__('sms_templates.fields.body'))
                                    // "Insert Variable" dropdown on the label row; it adds at the cursor.
                                    ->hint(fn (?SmsTemplate $record, Get $get): Htmlable => new HtmlString(view('filament.admin.sms-templates.insert-variable', [
                                        'target' => 'sms-body',
                                        'label' => __('sms_templates.actions.insert_variable'),
                                        'variables' => collect([
                                            ...SmsTemplate::builtInVariablesFor($record),
                                            ...array_filter(array_column($get('custom_variables') ?? [], 'name')),
                                        ])->unique()->map(fn (string $name): string => '{{ '.$name.' }}')->values()->all(),
                                    ])->render()))
                                    ->helperText(__('sms_templates.helpers.body'))
                                    ->required()
                                    ->rows(4)
                                    ->maxLength(1000),
                            ]),
                    ]),
            ]);
    }
}
