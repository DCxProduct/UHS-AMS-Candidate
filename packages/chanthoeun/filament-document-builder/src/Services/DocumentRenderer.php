<?php

namespace Chanthoeun\FilamentDocumentBuilder\Services;

use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;

class DocumentRenderer
{
    /**
     * Cache for extra data sources during the request lifecycle to prevent N+1 queries across multiple PDFs.
     */
    protected static array $extraDataCache = [];

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

            $value = data_get($data, $key);

            $currentContext = $data;
            while ($value === null && is_array($currentContext) && array_key_exists('_parent', $currentContext)) {
                $currentContext = $currentContext['_parent'];
                $value = data_get($currentContext, $key);
            }

            if ($value === null || $value === '') {
                return ''; // Return empty string instead of debug text in production
            }
            if (is_array($value) || is_object($value)) {
                return ''; // Ignore array printing rather than crashing mPDF
            }

            return $value;
        }, $content);
    }

    protected function processHtmlContent(DocumentTemplate $template, array|object $data = []): string
    {
        $htmlContent = $template->content ?? '';

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

        // mPDF compatibility fixes
        $htmlContent = preg_replace('/display:\s*inline-flex;?/', 'display: inline-block;', $htmlContent);
        $htmlContent = preg_replace('/align-items:\s*center;?/', 'vertical-align: middle;', $htmlContent);
        $htmlContent = preg_replace('/justify-content:\s*center;?/', 'text-align: center;', $htmlContent);

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

        $appUrl = config('app.url');
        if (! str_ends_with($appUrl, '/')) {
            $appUrl .= '/';
        }

        $htmlContent = preg_replace(
            '/src=["\']('.preg_quote($appUrl, '/').')?storage\/(.*?)["\']/i',
            'src="'.public_path('storage/$2').'"',
            $htmlContent
        );

        return $htmlContent;
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
            'autoLangToFont' => true,
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
