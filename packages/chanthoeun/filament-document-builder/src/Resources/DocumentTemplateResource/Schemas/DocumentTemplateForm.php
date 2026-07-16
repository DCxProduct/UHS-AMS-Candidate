<?php

namespace Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource\Schemas;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use Filament\Forms;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class DocumentTemplateForm
{
    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Wizard::make([
                    Step::make(__('filament-document-builder::document-builder.labels.template_details'))->schema([
                        Grid::make(4)->schema([
                            Forms\Components\TextInput::make('name_en')
                                ->label(__('filament-document-builder::document-builder.labels.template_name_en'))
                                ->placeholder(__('filament-document-builder::document-builder.placeholder.form_name_en'))
                                ->required()
                                ->afterStateHydrated(function ($component, $record): void {
                                    $form = self::templateCustomForm($record);

                                    if ($form) {
                                        $component->state(self::getLangText($form->name, 'en') . ' Template');
                                        return;
                                    }

                                    $component->state(self::getLangText($record?->name, 'en'));
                                })
                                ->dehydrated(false)
                                ->maxLength(255),

                            Forms\Components\TextInput::make('name_km')
                                ->label(__('filament-document-builder::document-builder.labels.template_name_kh'))
                                ->placeholder(__('filament-document-builder::document-builder.placeholder.form_name_km'))
                                ->required()
                                ->afterStateHydrated(function ($component, $record): void {
                                    $form = self::templateCustomForm($record);

                                    if ($form) {
                                        $component->state(self::getLangText($form->name, 'km') . ' គំរូ');
                                        return;
                                    }

                                    $component->state(self::getLangText($record?->name, 'km'));
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

                            Forms\Components\TextInput::make('type')
                                ->label(__('filament-document-builder::document-builder.labels.template_type'))
                                ->placeholder(__('filament-document-builder::document-builder.labels.type_placeholder'))
                                ->maxLength(255),

                            Forms\Components\Select::make('custom_form_id')
                                ->label(__('filament-document-builder::document-builder.labels.form_field'))
                                ->placeholder(__('filament-document-builder::document-builder.placeholder.form_field'))
                                ->options(function () {
                                    if (! class_exists(\Chanthoeun\FilamentCustomForms\Models\CustomForm::class)) {
                                        return [];
                                    }

                                    return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                                        ->get()
                                        ->mapWithKeys(fn ($form): array => [
                                            $form->id => self::localeText($form->name),
                                        ])
                                        ->toArray();
                                })
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (! $state) {
                                        return;
                                    }

                                    $form = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($state);

                                    if (! $form) {
                                        return;
                                    }

                                    $set('name_en', self::getLangText($form->name, 'en') . ' Template');
                                    $set('name_km', self::getLangText($form->name, 'km') . ' គំរូ');
                                }),
                            Forms\Components\Select::make('model_class')
                                ->label(__('filament-document-builder::document-builder.labels.database_model'))
                                ->options(function () {
                                    $models = [];
                                    $path = app_path('Models');
                                    if (is_dir($path)) {
                                        foreach (scandir($path) as $file) {
                                            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                                                $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
                                                if (class_exists($class)) {
                                                    $models[$class] = class_basename($class);
                                                }
                                            }
                                        }
                                    }
                                    if (class_exists('Chanthoeun\FilamentCustomForms\Models\CustomFormEntry')) {
                                        $models['Chanthoeun\FilamentCustomForms\Models\CustomFormEntry'] = 'Custom Form Entry';
                                    }

                                    return $models;
                                })
                                ->live()
                                ->placeholder(__('filament-document-builder::document-builder.labels.model_placeholder')),
                        ]),
                        Forms\Components\KeyValue::make('page_settings')
                            ->label(__('filament-document-builder::document-builder.labels.page_settings'))
                            ->keyLabel(__('filament-document-builder::document-builder.labels.setting'))
                            ->valueLabel(__('filament-document-builder::document-builder.labels.value'))
                            ->default([
                                'format' => config('filament-document-builder.default_paper_size', 'a4'),
                                'orientation' => config('filament-document-builder.default_orientation', 'portrait'),
                                'default_font' => 'calibri',
                                'margin_left' => config('filament-document-builder.default_margins.left', '15'),
                                'margin_right' => config('filament-document-builder.default_margins.right', '15'),
                                'margin_top' => config('filament-document-builder.default_margins.top', '16'),
                                'margin_bottom' => config('filament-document-builder.default_margins.bottom', '16'),
                                'margin_header' => config('filament-document-builder.default_margins.header', '9'),
                                'margin_footer' => config('filament-document-builder.default_margins.footer', '9'),
                            ])
                            ->live(),
                        Forms\Components\Repeater::make('extra_data_sources')
                            ->label(__('filament-document-builder::document-builder.labels.additional_data_sources'))
                            ->schema([
                                Forms\Components\TextInput::make('variable_name')
                                    ->label(__('filament-document-builder::document-builder.labels.variable_name'))
                                    ->required()
                                    ->placeholder(__('filament-document-builder::document-builder.labels.variable_placeholder')),
                                Forms\Components\Select::make('model_class')
                                    ->label(__('filament-document-builder::document-builder.labels.database_model'))
                                    ->required()
                                    ->options(function () {
                                        $models = [];
                                        $path = app_path('Models');
                                        if (is_dir($path)) {
                                            foreach (scandir($path) as $file) {
                                                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                                                    $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
                                                    if (class_exists($class)) {
                                                        $models[$class] = class_basename($class);
                                                    }
                                                }
                                            }
                                        }
                                        if (class_exists('Chanthoeun\FilamentCustomForms\Models\CustomFormEntry')) {
                                            $models['Chanthoeun\FilamentCustomForms\Models\CustomFormEntry'] = 'Custom Form Entry';
                                        }

                                        return $models;
                                    })
                                    ->searchable(),
                                Forms\Components\Select::make('retrieval_method')
                                    ->label(__('filament-document-builder::document-builder.labels.retrieval_method'))
                                    ->required()
                                    ->options([
                                        'first' => __('filament-document-builder::document-builder.labels.first_record'),
                                        'latest' => __('filament-document-builder::document-builder.labels.latest_record'),
                                    ])
                                    ->default('first'),
                            ])
                            ->columns(3)
                            ->live()
                            ->itemLabel(fn (array $state): ?string => $state['variable_name'] ?? null),
                    ]),
                    Step::make(__('filament-document-builder::document-builder.labels.document_designer'))->schema([
                        TinyEditor::make('content')
                            ->label(__('filament-document-builder::document-builder.labels.document_designer'))
                            ->hiddenLabel()
                            ->required(false)
                            ->dehydrated(true)
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('document-templates')
                            ->profile('full')
                            ->key(fn (Get $get) => 'tinymce-'.md5(
                                    json_encode($get('extra_data_sources')) .
                                    $get('model_class') .
                                    $get('custom_form_id') .
                                    json_encode($get('page_settings'))
                                ))
                            ->setCustomConfigs(function (Get $get) {
                                $vars = [];
                                $modelClass = $get('model_class');

                                if ($modelClass && class_exists($modelClass)) {
                                    $model = new $modelClass;
                                    $vars = array_merge(['id', 'created_at', 'updated_at'], $model->getFillable());

                                    $customFormId = $get('custom_form_id');

                                    if (
                                        $modelClass === 'Chanthoeun\FilamentCustomForms\Models\CustomFormEntry'
                                        && $customFormId
                                    ) {
                                        $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::find($customFormId);

                                        if ($customForm) {
                                            $customFields = [];

                                            $addFieldsFromForm = function ($form) use (&$customFields): void {
                                                foreach ($form->fields()->orderBy('sort')->get() as $field) {
                                                    if (
                                                        ! in_array($field->type, ['section', 'grid', 'fieldset', 'wizard', 'repeater', 'step'], true)
                                                        && ! empty($field->name)
                                                    ) {
                                                        $customFields[] = 'data.' . $field->name;
                                                    }
                                                }
                                            };

                                            // parent fields
                                            $addFieldsFromForm($customForm);

                                            // child sub item fields: Associate, Bachelor, Master, PhD, Exam...
                                            $childForms = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                                                ->where('custom_form_id', $customForm->id)
                                                ->where('menu_placement', 'sub_item')
                                                ->where('is_active', true)
                                                ->get();

                                            foreach ($childForms as $childForm) {
                                                $addFieldsFromForm($childForm);
                                            }

                                            $customFields = array_values(array_unique($customFields));

                                            $vars = array_merge($vars, $customFields);
                                        }
                                    }
                                }

                                $extraSources = $get('extra_data_sources') ?? [];

                                foreach ($extraSources as $source) {
                                    if (! empty($source['variable_name'])) {
                                        $vars[] = $source['variable_name'];

                                        if (! empty($source['model_class']) && class_exists($source['model_class'])) {
                                            $extraModel = new $source['model_class'];
                                            $fields = array_merge(['id', 'created_at', 'updated_at'], $extraModel->getFillable());

                                            foreach ($fields as $field) {
                                                $vars[] = $source['variable_name'] . '.' . $field;
                                            }
                                        }
                                    }
                                }

                                /** @var array<string> $vars */
                                sort($vars);

                                $settings = $get('page_settings') ?? [];
                                $format = $settings['format'] ?? 'a4';
                                $orientation = $settings['orientation'] ?? 'portrait';
                                $marginTop = $settings['margin_top'] ?? '16';
                                $marginBottom = $settings['margin_bottom'] ?? '16';
                                $marginLeft = $settings['margin_left'] ?? '15';
                                $marginRight = $settings['margin_right'] ?? '15';

                                $sizes = [
                                    'a3' => ['width' => 297, 'height' => 420],
                                    'a4' => ['width' => 210, 'height' => 297],
                                    'a5' => ['width' => 148, 'height' => 210],
                                    'letter' => ['width' => 215.9, 'height' => 279.4],
                                    'legal' => ['width' => 215.9, 'height' => 355.6],
                                ];

                                $dimensions = $sizes[strtolower($format)] ?? $sizes['a4'];
                                $width = $orientation === 'landscape' ? $dimensions['height'] : $dimensions['width'];
                                $minHeight = $orientation === 'landscape' ? $dimensions['width'] : $dimensions['height'];

                                $contentStyle = '@import url("https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Moul&family=Siemreap&display=swap"); '.
                                    'html { background: #f3f4f6; padding: 20px 0; } '.
                                    'body { font-family: Calibri, "Battambang", Arial, sans-serif; background: #fff; '.
                                    'width: '.$width.'mm; min-height: '.$minHeight.'mm; '.
                                    'padding: '.$marginTop.'mm '.$marginRight.'mm '.$marginBottom.'mm '.$marginLeft.'mm !important; '.
                                    'margin: 0 auto !important; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; } '.
                                    'p { margin-top: 0; }';

                                return [
                                    'document_variables' => $vars,
                                    'menubar' => 'file edit view insert format tools table help',
                                    'font_family_formats' => 'Arial=arial,helvetica,sans-serif; Calibri=calibri,sans-serif; Times New Roman="times new roman",times,serif; Khmer Battambang=Battambang,sans-serif; Khmer Moul Light="Khmer OS Muol Light",Moul,cursive; Khmer Siemreap=Siemreap,sans-serif;',
                                    'content_style' => $contentStyle,
                                    'plugins' => 'custom_shapes accordion autoresize codesample directionality advlist autolink link image lists charmap anchor pagebreak searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media table emoticons help template',
                                    'toolbar' => 'undo redo removeformat | fontfamily fontsize fontsizeinput font_size_formats styles | bold italic underline | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist outdent indent accordion | forecolor backcolor | blockquote table toc hr | image link anchor media codesample emoticons document_templates_btn insert_variable | visualblocks print wordcount fullscreen help',
                                    'templates' => config('filament-document-builder.templates', self::defaultTemplates()),
                                    'text_patterns' => [
                                        ['start' => '#logo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div>'],
                                        ['start' => '#box', 'replacement' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center; line-height: 80px;">BOX</div>'],
                                        ['start' => '#photo', 'replacement' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center; padding-top: 30px; box-sizing: border-box;">រូបថត<br>៤x៦</div>'],
                                        ['start' => '#checkbox', 'replacement' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>'],
                                        ['start' => '#rounded', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center; line-height: 60px;">TEXT</div>'],
                                        ['start' => '#oval', 'replacement' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 60px;">OVAL</div>'],
                                        ['start' => '#sign', 'replacement' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>'],
                                    ],
                                ];
                            }),
                    ]),
                ])->columnSpanFull()->skippable(),
            ])
            ->columns(1);
    }

    private static function englishText(mixed $value): string
    {
        return self::getLangText($value, 'en');
    }

    private static function defaultTemplates(): array
    {
        return [
            [
                'title' => 'Layout - 1 Column',
                'description' => 'A table with 1 column taking full width',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 100%; padding: 5px; vertical-align: top; border: none;">Column 1</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Layout - 2 Columns',
                'description' => 'A table with 2 equal columns',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 2</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Layout - 3 Columns',
                'description' => 'A table with 3 equal columns',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 3</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Layout - 4 Columns',
                'description' => 'A table with 4 equal columns',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 3</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 4</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Layout - 1/3 Left, 2/3 Right',
                'description' => '2 Columns: smaller left, larger right',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Left Sidebar</td><td style="width: 66.66%; padding: 5px; vertical-align: top; border: none;">Main Content</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Layout - 2/3 Left, 1/3 Right',
                'description' => '2 Columns: larger left, smaller right',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 66.66%; padding: 5px; vertical-align: top; border: none;">Main Content</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Right Sidebar</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Header - Logo & Title',
                'description' => 'A professional document header',
                'content' => '<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; margin-bottom: 20px;"><tbody><tr><td style="width: 20%; padding: 10px; vertical-align: middle; border: none;"><div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div></td><td style="width: 80%; padding: 10px; vertical-align: middle; text-align: right; border: none;"><h2 style="margin: 0;">COMPANY NAME</h2><p style="margin: 0; color: #555;">Company Address &bull; Contact Info &bull; Email</p></td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Component - Invoice/Receipt Table',
                'description' => 'A standardized 5-column item table',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 20px;"><thead><tr style="background-color: #f2f2f2;"><th style="border: 1px solid #000; padding: 8px; text-align: center; width: 5%;">No.</th><th style="border: 1px solid #000; padding: 8px; text-align: left; width: 50%;">Description</th><th style="border: 1px solid #000; padding: 8px; text-align: center; width: 15%;">Qty</th><th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Unit Price</th><th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Total</th></tr></thead><tbody><tr><td style="border: 1px solid #000; padding: 8px; text-align: center;">1</td><td style="border: 1px solid #000; padding: 8px;">Item Description</td><td style="border: 1px solid #000; padding: 8px; text-align: center;">1</td><td style="border: 1px solid #000; padding: 8px; text-align: right;">$0.00</td><td style="border: 1px solid #000; padding: 8px; text-align: right;">$0.00</td></tr><tr><td colspan="4" style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">Grand Total:</td><td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">$0.00</td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Element - Signatures (2 Persons)',
                'description' => 'Signature block for 2 parties',
                'content' => '<table style="width: 100%; border-collapse: collapse; border: none; margin-top: 40px;"><tbody><tr><td style="width: 50%; padding: 5px; text-align: center; vertical-align: bottom; border: none;"><div style="display: inline-block; width: 200px; border-bottom: 1px solid #000; padding-bottom: 5px;">ហត្ថលេខា / Signature 1</div><p style="margin-top: 5px;">Name / Title</p></td><td style="width: 50%; padding: 5px; text-align: center; vertical-align: bottom; border: none;"><div style="display: inline-block; width: 200px; border-bottom: 1px solid #000; padding-bottom: 5px;">ហត្ថលេខា / Signature 2</div><p style="margin-top: 5px;">Name / Title</p></td></tr></tbody></table><p><br></p>',
            ],
            [
                'title' => 'Shape - Circle (Logo)',
                'description' => 'A circular shape for logos or avatars',
                'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center;">LOGO</div>',
            ],
            [
                'title' => 'Shape - Square Box',
                'description' => 'A simple square box',
                'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center;">BOX</div>',
            ],
            [
                'title' => 'Shape - Rectangle Photo Box (4x6)',
                'description' => '4x6 Photo Box for Khmer forms',
                'content' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center;">រូបថត<br>៤x៦</div>',
            ],
            [
                'title' => 'Element - Checkbox (Small Square)',
                'description' => 'Small square for checkboxes',
                'content' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>',
            ],
            [
                'title' => 'Shape - Rounded Rectangle',
                'description' => 'A rectangle with rounded corners',
                'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center;">TEXT</div>',
            ],
            [
                'title' => 'Shape - Oval',
                'description' => 'An oval shape',
                'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center;">OVAL</div>',
            ],
            [
                'title' => 'Element - Signature Area',
                'description' => 'A line for signatures',
                'content' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>',
            ],
        ];
    }

    private static function localeText(mixed $value): string
    {
        return self::getLangText($value, app()->getLocale());
    }

    private static function getLangText(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        $text = (string) $value;

        if (preg_match('/^(\{.*?\})(.*)$/u', $text, $matches)) {
            $decoded = json_decode($matches[1], true);

            if (is_array($decoded)) {
                return trim((
                        $decoded[$locale]
                        ?? $decoded['km']
                        ?? $decoded['kh']
                        ?? $decoded['en']
                        ?? ''
                    ) . ($matches[2] ?? ''));
            }
        }

        return $text;
    }

    private static function templateCustomForm($record): ?\Chanthoeun\FilamentCustomForms\Models\CustomForm
    {
        if (! $record) {
            return null;
        }

        if (is_string($record->type ?? null) && preg_match('/^custom_form_(\d+)$/', $record->type, $matches)) {
            return \Chanthoeun\FilamentCustomForms\Models\CustomForm::find((int) $matches[1]);
        }

        return $record->customForm ?? null;
    }
}
