<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Models\EmailTemplate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                // Built-in templates keep their system name; new templates need one.
                Section::make(__('email_templates.sections.name'))
                    ->visible(fn (?EmailTemplate $record): bool => ! $record?->isBuiltIn())
                    ->schema([
                        TextInput::make('name')
                            ->label(__('email_templates.fields.template_name'))
                            ->placeholder(__('email_templates.placeholders.template_name'))
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make(__('email_templates.sections.header'))
                    ->description(__('email_templates.sections.header_description'))
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label(__('email_templates.fields.logo'))
                            ->helperText(__('email_templates.helpers.logo'))
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/gif'])
                            ->maxSize(1024)
                            ->disk('public')
                            ->directory('email-templates')
                            ->visibility('public'),

                        TextInput::make('header_title')
                            ->label(__('email_templates.fields.header_title'))
                            ->required()
                            ->maxLength(255),
                    ]),

                Section::make(__('email_templates.sections.custom_variables'))
                    ->description(__('email_templates.sections.custom_variables_description'))
                    ->collapsible()
                    ->schema([
                        View::make('filament.admin.partials.template-variables')
                            ->viewData(fn (?EmailTemplate $record): array => [
                                'heading' => __('email_templates.built_in.heading'),
                                'labels' => __('email_templates.built_in.columns'),
                                'variables' => collect(EmailTemplate::builtInVariablesFor($record))
                                    ->map(fn (string $name): array => [
                                        'name' => $name,
                                        'meaning' => __('email_templates.built_in.meanings.'.$name),
                                        'sample' => __('email_templates.built_in.samples.'.$name),
                                    ])
                                    ->all(),
                            ]),

                        Repeater::make('custom_variables')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('email_templates.fields.variable_name'))
                                    ->prefix('{{')
                                    ->suffix('}}')
                                    ->placeholder('hotline')
                                    ->required()
                                    ->maxLength(40)
                                    ->regex('/^[a-z][a-z0-9_]*$/')
                                    ->notIn(fn (?EmailTemplate $record): array => EmailTemplate::builtInVariablesFor($record))
                                    ->distinct()
                                    ->validationMessages([
                                        'regex' => __('email_templates.validation.variable_name'),
                                        'not_in' => __('email_templates.validation.variable_reserved'),
                                        'distinct' => __('email_templates.validation.variable_distinct'),
                                    ])
                                    ->live(onBlur: true),
                                TextInput::make('value')
                                    ->label(__('email_templates.fields.value'))
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel(__('email_templates.actions.add_variable'))
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? '{{ '.$state['name'].' }}' : null)
                            ->live(),
                    ]),

                Section::make(__('email_templates.sections.content'))
                    ->description(__('email_templates.sections.content_description'))
                    ->schema(self::contentFields()),
            ]);
    }

    private static function contentFields(): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                TextInput::make('subject')
                    ->label(__('email_templates.fields.subject'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('button')
                    ->label(__('email_templates.fields.button'))
                    ->helperText(__('email_templates.helpers.button'))
                    ->required()
                    ->maxLength(255),
            ]),

            // Same editor and "Insert Variable" button as the Document Designer.
            TinyEditor::make('body')
                ->label(__('email_templates.fields.body'))
                ->required()
                ->columnSpanFull()
                ->fileAttachmentsDisk('public')
                ->fileAttachmentsDirectory('email-templates')
                ->profile('full')
                // A new key reloads the editor, so new custom variables show up in "Insert Variable".
                ->key(fn (Get $get): string => 'email-body-'.md5(json_encode(array_column($get('custom_variables') ?? [], 'name'))))
                ->setCustomConfigs(fn (?EmailTemplate $record, Get $get): array => self::editorConfig(
                    EmailTemplate::builtInVariablesFor($record),
                    array_filter(array_column($get('custom_variables') ?? [], 'name')),
                )),
        ];
    }

    private static function editorConfig(array $builtInNames, array $customNames = []): array
    {
        return [
            'document_variables' => array_values(array_unique([...$builtInNames, ...$customNames])),
            'menubar' => 'file edit view insert format tools table help',
            'height' => 560,
            // Images need full URLs to show in email inboxes.
            'relative_urls' => false,
            'remove_script_host' => false,
            'font_family_formats' => 'Arial=arial,helvetica,sans-serif; Khmer Battambang=Battambang,sans-serif; Khmer Siemreap=Siemreap,sans-serif; Times New Roman="times new roman",times,serif;',
            'content_style' => '@import url("https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Siemreap&display=swap"); '
                .'html { background: #f4f4f5; padding: 20px 0; } '
                .'body { max-width: 600px; margin: 0 auto !important; padding: 32px !important; background: #fff; '
                .'border: 1px solid #e4e4e7; border-radius: 10px; font-family: Arial, "Battambang", sans-serif; font-size: 15px; line-height: 1.6; color: #18181b; }',
            'plugins' => 'custom_shapes autoresize directionality advlist autolink link image lists charmap searchreplace visualblocks code fullscreen table emoticons help',
            'toolbar' => 'undo redo removeformat | fontfamily fontsize styles | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | numlist bullist outdent indent | table hr | image link emoticons insert_variable | code fullscreen help',
        ];
    }
}
