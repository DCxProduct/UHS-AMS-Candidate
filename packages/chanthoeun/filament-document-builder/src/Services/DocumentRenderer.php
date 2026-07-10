<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class DocumentRenderer
{
    /**
     * Cache for extra data sources during the request lifecycle to prevent N+1 queries across multiple PDFs.
     */
    protected static array $extraDataCache = [];

    protected ?string $renderLocale = null;

    /**
     * Eager load relations used in the template to prevent N+1 queries.
     */
    protected function preloadRelations(?string $content, array|object $data): void
    {
        if (empty($content) || ! ($data instanceof Model)) {
            return;
        }

        // Find all {{ variable.nested }} or {{#foreach variable.nested as ...}}
        preg_match_all('/{{\s*(?:#foreach\s+)?([a-zA-Z0-9_\.]+)/', $content, $matches);

        $relationsToLoad = [];
        foreach ($matches[1] as $match) {
            $parts = explode('.', $match);
            // If there's a dot, the preceding parts are likely relations
            if (count($parts) > 1) {
                array_pop($parts); // Remove the field name
                $relation = implode('.', $parts);
                $relationsToLoad[] = $relation;
            }
        }

        if (! empty($relationsToLoad)) {
            // Only load relations that actually exist on the model to avoid exceptions
            $validRelations = [];
            foreach (array_unique($relationsToLoad) as $rel) {
                if (method_exists($data, explode('.', $rel)[0])) {
                    $validRelations[] = $rel;
                }
            }
            if (! empty($validRelations)) {
                $data->loadMissing($validRelations);
            }
        }
    }

    /**
     * Replaces loop blocks like {{#foreach items as item}} ... {{/foreach}}
     */
    protected function replaceLoops(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Use precise regex to prevent catastrophic backtracking and avoid swallowing preceding tags
        return preg_replace_callback(
            '/{{\s*#foreach\s+([a-zA-Z0-9_\.]+)\s+as\s+([a-zA-Z0-9_]+)\s*}}(.*?){{\s*\/foreach\s*}}/is',
            function ($matches) use ($data) {
                $arrayPath = trim(strip_tags($matches[1]));
                $arrayPath = html_entity_decode(str_replace('&nbsp;', '', $arrayPath));

                $itemName = trim(strip_tags($matches[2]));
                $itemName = html_entity_decode(str_replace('&nbsp;', '', $itemName));

                $blockContent = $matches[3];

                $items = data_get($data, $arrayPath);

                // Fallback for parent scopes
                $currentContext = $data;
                while ($items === null && is_array($currentContext) && array_key_exists('_parent', $currentContext)) {
                    $currentContext = $currentContext['_parent'];
                    $items = data_get($currentContext, $arrayPath);
                }

                if (! is_iterable($items)) {
                    return '';
                }

                $result = '';

                foreach ($items as $item) {
                    $loopData = [
                        '_parent' => $data,
                        $itemName => $item,
                    ];

                    $result .= $this->replaceVariables($blockContent, $loopData);
                }

                return $result;
            },
            $content
        );
    }

    /**
     * Replaces string variables like {{ variable.name }} with actual data.
     */
    protected function replaceVariables(?string $content, array|object $data): ?string
    {
        if (empty($content)) {
            return $content;
        }

        // Basic replacement logic for {{ variable }} or {{ variable.key }}
        return preg_replace_callback('/{{(.*?)}}/s', function ($matches) use ($data) {
            $key = trim(strip_tags($matches[1]));
            $key = html_entity_decode(str_replace('&nbsp;', '', $key));
            $key = trim($key);

            if (str_starts_with($key, '#foreach') || str_starts_with($key, '/foreach')) {
                return $matches[0];
            }

            $valueContext = $data;
            $value = data_get($data, $key);

            $currentContext = $data;
            while ($value === null && is_array($currentContext) && array_key_exists('_parent', $currentContext)) {
                $currentContext = $currentContext['_parent'];
                $value = data_get($currentContext, $key);
                $valueContext = $currentContext;
            }

            if ($value === null && $data instanceof Model && is_array($data->getAttribute('data'))) {
                $value = data_get($data->getAttribute('data'), $key);
                $valueContext = $data;
            }

            if ($value === null && $valueContext instanceof Model && is_array($valueContext->getAttribute('data'))) {
                $value = data_get($valueContext->getAttribute('data'), $key);
            }

            if ($value === null || $value === '') {
                return ''; // Return empty string instead of debug text in production
            }
            if (is_array($value) || is_object($value)) {
                return ''; // Ignore array printing rather than crashing mPDF
            }

            if ($imageHtml = $this->imageHtmlFromValue((string) $value)) {
                return $imageHtml;
            }

            return $this->displayValueForKey($key, $value, $valueContext);
        }, $content);
    }

    protected function displayValueForKey(string $key, mixed $value, array|object $data): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $choiceLabel = $this->choiceLabelForKey($key, (string) $value, $data);

        return $choiceLabel
            ?? $this->fallbackChoiceLabel($key, (string) $value)
            ?? (string) $value;
    }

    protected function fallbackChoiceLabel(string $key, string $value): ?string
    {
        if (! in_array(strtolower($key), ['gender', 'sibling_gender'], true)) {
            return null;
        }

        $locale = $this->renderLocale ?? app()->getLocale();

        return match (strtolower($value)) {
            'male' => $locale === 'km' ? 'ប្រុស' : 'Male',
            'female' => $locale === 'km' ? 'ស្រី' : 'Female',
            default => null,
        };
    }

    protected function choiceLabelForKey(string $key, string $value, array|object $data): ?string
    {
        if (! $data instanceof Model || ! Schema::hasTable('custom_form_fields')) {
            return null;
        }

        $formId = $data->getAttribute('custom_form_id');

        if (! $formId) {
            return null;
        }

        $field = DB::table('custom_form_fields')
            ->whereIn('custom_form_id', $this->formIdsForEntry((int) $formId))
            ->where('name', $key)
            ->whereNotNull('options')
            ->orderBy('sort')
            ->first();

        $label = $field ? $this->choiceLabelFromField($field, $value) : null;

        if ($label !== null) {
            return $label;
        }

        $fallbackFields = DB::table('custom_form_fields')
            ->where('name', $key)
            ->whereNotNull('options')
            ->orderBy('custom_form_id')
            ->orderBy('sort')
            ->get();

        foreach ($fallbackFields as $fallbackField) {
            $label = $this->choiceLabelFromField($fallbackField, $value);

            if ($label !== null) {
                return $label;
            }
        }

        return null;
    }

    protected function choiceLabelFromField(object $field, string $value): ?string
    {
        $options = $this->normalizeOptions($field->options ?? []);
        $choices = $this->normalizeChoices($options['choices'] ?? []);

        return array_key_exists($value, $choices)
            ? $this->localizedText($choices[$value])
            : null;
    }

    protected function formIdsForEntry(int $formId): array
    {
        $ids = [$formId];

        if (Schema::hasTable('custom_forms') && Schema::hasColumn('custom_forms', 'custom_form_id')) {
            $childIds = DB::table('custom_forms')
                ->where('custom_form_id', $formId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $ids = array_merge($ids, $childIds);
        }

        return array_values(array_unique($ids));
    }

    protected function normalizeOptions(mixed $options): array
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

    protected function normalizeChoices(mixed $choices): array
    {
        if (! is_array($choices)) {
            return [];
        }

        $normalized = [];

        foreach ($choices as $key => $label) {
            if (is_array($label) && array_key_exists('value', $label)) {
                $normalized[(string) $label['value']] = $label['label'] ?? $label['value'];

                continue;
            }

            $normalized[(string) $key] = $label;
        }

        return $normalized;
    }

    protected function imageHtmlFromValue(string $value): ?string
    {
        $localPath = $this->localImagePathFromValue($value);

        if (! $localPath) {
            return null;
        }

        return '<img src="' . e($localPath) . '" style="max-width: 180px; max-height: 120px; object-fit: contain;" />';
    }

    protected function localImagePathFromValue(string $value): ?string
    {
        $path = trim($value);

        if ($path === '' || ! preg_match('/\.(jpe?g|png|webp|gif)$/i', $path)) {
            return null;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            $publicPath = parse_url($path, PHP_URL_PATH);

            if (is_string($publicPath) && str_starts_with($publicPath, '/storage/')) {
                $path = substr($publicPath, strlen('/storage/'));
            }
        }

        $disk = config('filament-custom-forms.uploads.disk', 'public');

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return Storage::disk($disk)->path($path);
    }

    protected function attachmentImagesHtml(array|object $data): string
    {
        if (! $data instanceof Model || ! is_array($data->getAttribute('data'))) {
            return '';
        }

        $images = $this->collectAttachmentImages(
            $data->getAttribute('data'),
            $this->attachmentImageLabels($data)
        );

        if (empty($images)) {
            return '';
        }

        $html = '<div style="page-break-before: always; padding: 6mm; background-color: #f3f4f6; border: 1px solid #d1d5db; min-height: 270mm;">';

        foreach ($images as $label => $path) {
            $html .= '<div style="margin-bottom: 6mm; padding: 4mm; background-color: #ffffff; border: 1px solid #cbd5e1;">'
                . '<img src="' . e($path) . '" style="width: 100%; max-height: 258mm; object-fit: contain; border: 1px solid #e5e7eb;" />'
                . '</div>';
        }

        return $html . '</div>';
    }

    protected function collectAttachmentImages(array $data, array $labels = [], string $prefix = ''): array
    {
        $images = [];

        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $images += $this->collectAttachmentImages($value, $labels, $label);

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $path = $this->localImagePathFromValue($value);

            if ($path) {
                $images[$labels[(string) $key] ?? $label] = $path;
            }
        }

        return $images;
    }

    protected function attachmentImageLabels(Model $entry): array
    {
        if (! class_exists(\Chanthoeun\FilamentCustomForms\Models\CustomFormField::class)) {
            return [];
        }

        $formIds = array_filter([(int) ($entry->custom_form_id ?? 0)]);
        $selection = strtolower((string) data_get($entry->data, 'form_selection'));

        if (
            filled($selection)
            && class_exists(\Chanthoeun\FilamentCustomForms\Models\CustomForm::class)
        ) {
            $subFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $entry->custom_form_id)
                ->where('menu_placement', 'sub_item')
                ->whereRaw('LOWER(sub_item_type) = ?', [$selection])
                ->value('id');

            if ($subFormId) {
                $formIds[] = (int) $subFormId;
            }
        }

        return \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
            ->whereIn('custom_form_id', array_unique($formIds))
            ->whereIn('type', ['image', 'image_upload'])
            ->get(['name', 'label'])
            ->mapWithKeys(fn ($field): array => [
                (string) $field->name => $this->localizedText($field->label) ?: (string) $field->name,
            ])
            ->toArray();
    }

    protected function localizedText(mixed $value): string
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
            $locale = $this->renderLocale ?? app()->getLocale();

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

    protected function processHtmlContent(DocumentTemplate $template, array|object $data = []): string
    {
        $htmlContent = $template->content ?? '';
        $this->renderLocale = $this->detectRenderLocale($htmlContent);
        $recordData = $data;

        // Eager load relations to prevent N+1 performance bottlenecks
        $this->preloadRelations($htmlContent, $data);

        // Fetch extra data sources defined in the template (Cached statically)
        $extraData = [];
        if (! empty($template->extra_data_sources)) {
            foreach ($template->extra_data_sources as $source) {
                if (! empty($source['variable_name']) && ! empty($source['model_class']) && class_exists($source['model_class'])) {
                    $method = $source['retrieval_method'] ?? 'first';
                    $cacheKey = md5($source['model_class'].'_'.$method);

                    if (! isset(self::$extraDataCache[$cacheKey])) {
                        if ($method === 'latest') {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::latest()->first();
                        } else {
                            self::$extraDataCache[$cacheKey] = $source['model_class']::first();
                        }
                    }

                    $extraData[$source['variable_name']] = self::$extraDataCache[$cacheKey];
                }
            }
        }

        if (! empty($extraData)) {
            $data = array_merge(['_parent' => $data], $extraData);
        }

        $htmlContent = $this->replaceLoops($htmlContent, $data);
        $htmlContent = $this->replaceVariables($htmlContent, $data);
        $htmlContent = $this->localizeFallbackChoiceWords($htmlContent);
        $htmlContent .= $this->attachmentImagesHtml($recordData);

        // mPDF compatibility fixes
        $htmlContent = preg_replace('/display:\s*inline-flex;?/', 'display: inline-block;', $htmlContent);
        $htmlContent = preg_replace('/align-items:\s*center;?/', 'vertical-align: middle;', $htmlContent);
        $htmlContent = preg_replace('/justify-content:\s*center;?/', 'text-align: center;', $htmlContent);

        // Fix Zero Width Space (ZWSP) causing empty rectangle boxes in mPDF Khmer rendering
        $htmlContent = str_replace("\xE2\x80\x8B", '', $htmlContent); // ZWSP
        $htmlContent = str_replace("\xE2\x80\x8C", '', $htmlContent); // ZWNJ
        $htmlContent = str_replace("\xE2\x80\x8D", '', $htmlContent); // ZWJ
        $htmlContent = str_replace("\xEF\xBB\xBF", '', $htmlContent); // BOM
        $htmlContent = str_replace('&#8203;', '', $htmlContent);
        $htmlContent = str_replace('&#8204;', '', $htmlContent);
        $htmlContent = str_replace('&#8205;', '', $htmlContent);
        $htmlContent = str_replace('<wbr>', '', $htmlContent);
        $htmlContent = str_replace('<wbr/>', '', $htmlContent);

        $htmlContent = preg_replace_callback(
            '/<div[^>]*style="([^"]*height:\s*(\d+px)[^"]*)"[^>]*>/i',
            function ($matches) {
                $style = $matches[1];
                $height = $matches[2];
                if (strpos($style, 'line-height') === false) {
                    $newStyle = $style.' line-height: '.$height.';';

                    return str_replace($style, $newStyle, $matches[0]);
                }

                return $matches[0];
            },
            $htmlContent
        );

        // Strip remote Google Fonts @import rules to prevent massive mPDF network delays
        $htmlContent = preg_replace('/@import\s+url\([\'"]?https:\/\/fonts\.googleapis\.com.*?[\'"]?\);?/i', '', $htmlContent);

        // Allow font-family inline styles since we now register the custom fonts in mPDF config

        $appUrl = config('app.url');
        if (! str_ends_with($appUrl, '/')) {
            $appUrl .= '/';
        }

        $htmlContent = preg_replace(
            '/src=["\']('.preg_quote($appUrl, '/').')?\/?storage\/(.*?)["\']/i',
            'src="'.public_path('storage/$2').'"',
            $htmlContent
        );

        return $htmlContent;
    }

    protected function detectRenderLocale(string $content): string
    {
        if (preg_match('/[\x{1780}-\x{17FF}]/u', $content) === 1) {
            return 'km';
        }

        $locale = strtolower((string) app()->getLocale());

        return in_array($locale, ['en', 'km'], true) ? $locale : 'km';
    }

    protected function localizeFallbackChoiceWords(string $htmlContent): string
    {
        if (($this->renderLocale ?? app()->getLocale()) !== 'km') {
            return $htmlContent;
        }

        $htmlContent = preg_replace('/(?<![A-Za-z])female(?![A-Za-z])/i', 'ស្រី', $htmlContent) ?? $htmlContent;

        return preg_replace('/(?<![A-Za-z])male(?![A-Za-z])/i', 'ប្រុស', $htmlContent) ?? $htmlContent;
    }

    protected function generatePdfFromHtml(DocumentTemplate $template, string $htmlContent)
    {
        /** @var view-string $viewName */
        $viewName = 'filament-document-builder::document';
        $settings = $template->getAttribute('page_settings') ?? [];

        $html = view($viewName, [
            'htmlContent' => $htmlContent,
            'settings' => $settings,
        ])->render();

        $format = strtoupper(data_get($settings, 'format', 'a4'));
        $orientation = data_get($settings, 'orientation', 'portrait');

        $pdfConfig = [
            'format' => $format,
            'orientation' => $orientation === 'landscape' ? 'L' : 'P',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'default_font' => 'khmerbattambang',
            'custom_font_dir' => realpath(__DIR__.'/../../resources/fonts').'/',
            'custom_font_data' => [
                'khmerbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosbattambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'battambang' => [
                    'R' => 'KhmerOSbattambang.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmermoullight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerosmuollight' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'moul' => [
                    'R' => 'KhmerOSmuollight.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmersiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'khmerossiemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'siemreap' => [
                    'R' => 'KhmerOSsiemreap.ttf',
                    'useOTL' => 0xFF,
                ],
                'calibri' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'arial' => [
                    'R' => 'FreeSans.ttf',
                    'B' => 'FreeSansBold.ttf',
                    'I' => 'FreeSansOblique.ttf',
                    'BI' => 'FreeSansBoldOblique.ttf',
                ],
                'timesnewroman' => [
                    'R' => 'FreeSerif.ttf',
                    'B' => 'FreeSerifBold.ttf',
                    'I' => 'FreeSerifItalic.ttf',
                    'BI' => 'FreeSerifBoldItalic.ttf',
                ],
            ],
        ];

        if (is_array($settings)) {
            foreach ($settings as $key => $value) {
                if (! in_array($key, ['format', 'orientation']) && $value !== null && $value !== '') {
                    $pdfConfig[$key] = is_numeric($value) ? (float) $value : $value;
                }
            }
        }

        return Pdf::loadHTML($html, $pdfConfig);
    }

    public function render(DocumentTemplate $template, array|object $data = [])
    {
        $htmlContent = $this->processHtmlContent($template, $data);

        return $this->generatePdfFromHtml($template, $htmlContent);
    }

    public function renderMultiple(DocumentTemplate $template, iterable $records)
    {
        $htmlContent = $template->content ?? '';

        // Convert to Eloquent Collection to enable bulk relation loading
        $eloquentCollection = new Collection($records);
        $firstRecord = $eloquentCollection->first();

        // Bulk load relations for the entire collection to prevent N+1 queries
        if ($firstRecord instanceof Model) {
            preg_match_all('/{{\s*(?:#foreach\s+)?([a-zA-Z0-9_\.]+)/', $htmlContent, $matches);

            $relationsToLoad = [];
            foreach ($matches[1] as $match) {
                $parts = explode('.', $match);
                if (count($parts) > 1) {
                    array_pop($parts);
                    $relationsToLoad[] = implode('.', $parts);
                }
            }

            if (! empty($relationsToLoad)) {
                $validRelations = [];
                foreach (array_unique($relationsToLoad) as $rel) {
                    if (method_exists($firstRecord, explode('.', $rel)[0])) {
                        $validRelations[] = $rel;
                    }
                }
                if (! empty($validRelations)) {
                    $eloquentCollection->loadMissing($validRelations);
                }
            }
        }

        $htmlContents = [];
        foreach ($eloquentCollection as $record) {
            $htmlContents[] = $this->processHtmlContent($template, $record);
        }

        // Join multiple records with a pagebreak
        $combinedHtml = implode('<pagebreak />', $htmlContents);

        return $this->generatePdfFromHtml($template, $combinedHtml);
    }
}
