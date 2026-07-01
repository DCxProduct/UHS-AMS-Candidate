<?php

namespace Chanthoeun\FilamentCustomForms\Observers;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class CustomFormObserver
{
    public function created(CustomForm $customForm): void
    {
        $this->syncDocumentTemplate($customForm);
    }

    public function updated(CustomForm $customForm): void
    {
        $this->syncDocumentTemplate($customForm);
    }

    public function deleted(CustomForm $customForm): void
    {
        if (! class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return;
        }

        \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::where('type', 'custom_form_' . $customForm->id)
            ->delete();
    }

    private function syncDocumentTemplate(CustomForm $customForm): void
    {
        if (! class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return;
        }

        $linkedFormId = $customForm->custom_form_id ?? $customForm->id;

        \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::updateOrCreate(
            [
                'type' => 'custom_form_' . $customForm->id,
            ],
            [
                'name' => $this->templateName($customForm->name),
                'custom_form_id' => $linkedFormId,
                'model_class' => CustomFormEntry::class,
                'content' => '',
                'page_settings' => [
                    'format' => 'a4',
                    'orientation' => 'portrait',
                    'margin_left' => 15,
                    'margin_right' => 15,
                    'margin_top' => 15,
                    'margin_bottom' => 15,
                ],
                'extra_data_sources' => [],
            ]
        );
    }

    private function templateName(mixed $formName): string
    {
        $name = is_string($formName) && str_starts_with(trim($formName), '{')
            ? json_decode($formName, true)
            : $formName;

        $en = is_array($name)
            ? ($name['en'] ?? $name['km'] ?? $name['kh'] ?? '')
            : (string) $name;

        $km = is_array($name)
            ? ($name['km'] ?? $name['kh'] ?? $name['en'] ?? '')
            : (string) $name;

        return json_encode([
            'en' => trim($en . ' Template'),
            'km' => trim($km . ' គំរូ'),
            'kh' => trim($km . ' គំរូ'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
