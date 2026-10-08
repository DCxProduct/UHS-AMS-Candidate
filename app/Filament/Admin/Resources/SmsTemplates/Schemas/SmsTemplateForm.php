<?php

namespace App\Filament\Admin\Resources\SmsTemplates\Schemas;

use App\Models\SmsTemplate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
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
                        View::make('filament.admin.partials.template-variables')
                            ->viewData(fn (?SmsTemplate $record): array => [
                                'heading' => __('sms_templates.built_in.heading'),
                                'labels' => __('sms_templates.built_in.columns'),
                                'variables' => collect(SmsTemplate::VARIABLES[$record?->key ?? SmsTemplate::RESET_PASSWORD_OTP] ?? [])
                                    ->map(fn (string $name): array => [
                                        'name' => $name,
                                        'meaning' => __('sms_templates.built_in.meanings.'.$name),
                                        'sample' => __('sms_templates.built_in.samples.'.$name),
                                    ])
                                    ->all(),
                            ]),

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
                                    ->notIn(fn (?SmsTemplate $record): array => SmsTemplate::VARIABLES[$record?->key ?? SmsTemplate::RESET_PASSWORD_OTP] ?? [])
                                    ->distinct()
                                    ->validationMessages([
                                        'regex' => __('sms_templates.validation.variable_name'),
                                        'not_in' => __('sms_templates.validation.variable_reserved'),
                                        'distinct' => __('sms_templates.validation.variable_distinct'),
                                    ])
                                    ->live(onBlur: true),
                                TextInput::make('value_en')
                                    ->label(__('sms_templates.fields.value_en'))
                                    ->required()
                                    ->maxLength(200),
                                TextInput::make('value_km')
                                    ->label(__('sms_templates.fields.value_km'))
                                    ->helperText(__('sms_templates.helpers.value_km'))
                                    ->maxLength(200),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel(__('sms_templates.actions.add_variable'))
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? '{{ '.$state['name'].' }}' : null),
                    ]),

                Section::make(__('sms_templates.sections.content'))
                    ->description(__('sms_templates.sections.content_description'))
                    ->schema([
                        Tabs::make('languages')
                            ->tabs(collect(SmsTemplate::LOCALES)
                                ->map(fn (string $locale): Tab => Tab::make(__('sms_templates.tabs.'.$locale))
                                    ->schema([
                                        Textarea::make("body_{$locale}")
                                            ->id("sms-body-{$locale}")
                                            ->label(__('sms_templates.fields.body'))
                                            // "Insert Variable" dropdown on the label row; it adds at the cursor.
                                            ->hint(fn (?SmsTemplate $record, Get $get): Htmlable => new HtmlString(view('filament.admin.sms-templates.insert-variable', [
                                                'target' => "sms-body-{$locale}",
                                                'label' => __('sms_templates.actions.insert_variable'),
                                                'variables' => collect([
                                                    ...SmsTemplate::VARIABLES[$record?->key ?? SmsTemplate::RESET_PASSWORD_OTP] ?? [],
                                                    ...array_filter(array_column($get('custom_variables') ?? [], 'name')),
                                                ])->unique()->map(fn (string $name): string => '{{ '.$name.' }}')->values()->all(),
                                            ])->render()))
                                            ->helperText(__('sms_templates.helpers.body'))
                                            ->required()
                                            ->rows(4)
                                            ->maxLength(1000),
                                    ]))
                                ->all()),
                    ]),
            ]);
    }
}
