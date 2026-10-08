<?php

namespace App\Filament\Admin\Resources\NotificationTemplates\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class NotificationTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('notification_templates.sections.general_info'))
                    ->description(__('notification_templates.sections.general_info_description'))
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 3,
                        ])
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('notification_templates.fields.name'))
                                    ->placeholder(__('notification_templates.placeholders.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $operation, ?string $state, callable $set, callable $get): void {
                                        if ($operation !== 'create' || filled($get('code'))) {
                                            return;
                                        }

                                        $set('code', Str::of((string) $state)->slug('_')->upper()->toString());
                                    }),

                                TextInput::make('code')
                                    ->label(__('notification_templates.fields.code'))
                                    ->placeholder(__('notification_templates.placeholders.code'))
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(100)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : Str::of((string) $state)->slug('_')->upper()->toString())
                                    ->helperText(__('notification_templates.helpers.code')),

                                Select::make('type')
                                    ->label(__('notification_templates.fields.type'))
                                    ->options([
                                        'announcement' => __('notification_templates.types.announcement'),
                                        'request' => __('notification_templates.types.request'),
                                    ])
                                    ->default('announcement')
                                    ->required(),
                            ]),

                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])
                            ->schema([
                                CheckboxList::make('channels')
                                    ->label(__('notification_templates.fields.channels'))
                                    ->options([
                                        'database' => __('notification_templates.channels.database'),
                                        'mail' => __('notification_templates.channels.mail'),
                                    ])
                                    ->default(['database', 'mail'])
                                    ->columns(2)
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label(__('notification_templates.fields.is_active'))
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ]),

                Section::make(__('notification_templates.sections.variables_info'))
                    ->description(__('notification_templates.sections.variables_info_description'))
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Placeholder::make('variables_hint')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-2 text-sm">'.
                                '<div class="p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono text-primary-600 dark:text-primary-400"><code>{candidate_name}</code><p class="text-xs text-gray-500 font-sans mt-1">'.e(__('notification_templates.variables.candidate_name')).'</p></div>'.
                                '<div class="p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono text-primary-600 dark:text-primary-400"><code>{stage_name}</code><p class="text-xs text-gray-500 font-sans mt-1">'.e(__('notification_templates.variables.stage_name')).'</p></div>'.
                                '<div class="p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono text-primary-600 dark:text-primary-400"><code>{notes}</code><p class="text-xs text-gray-500 font-sans mt-1">'.e(__('notification_templates.variables.notes')).'</p></div>'.
                                '<div class="p-2 bg-gray-50 dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 font-mono text-primary-600 dark:text-primary-400"><code>{form_name}</code><p class="text-xs text-gray-500 font-sans mt-1">'.e(__('notification_templates.variables.form_name')).'</p></div>'.
                                '</div>'
                            )),
                    ]),

                Grid::make([
                    'default' => 1,
                    'lg' => 2,
                ])
                    ->schema([
                        Section::make(__('notification_templates.sections.content_en'))
                            ->schema([
                                TextInput::make('subject_en')
                                    ->label(__('notification_templates.fields.subject_en'))
                                    ->placeholder(__('notification_templates.placeholders.subject_en'))
                                    ->required()
                                    ->maxLength(255),

                                Textarea::make('body_en')
                                    ->label(__('notification_templates.fields.body_en'))
                                    ->placeholder(__('notification_templates.placeholders.body_en'))
                                    ->rows(6)
                                    ->required(),
                            ]),

                        Section::make(__('notification_templates.sections.content_km'))
                            ->schema([
                                TextInput::make('subject_km')
                                    ->label(__('notification_templates.fields.subject_km'))
                                    ->placeholder(__('notification_templates.placeholders.subject_km'))
                                    ->maxLength(255),

                                Textarea::make('body_km')
                                    ->label(__('notification_templates.fields.body_km'))
                                    ->placeholder(__('notification_templates.placeholders.body_km'))
                                    ->rows(6),
                            ]),
                    ]),
            ]);
    }
}
