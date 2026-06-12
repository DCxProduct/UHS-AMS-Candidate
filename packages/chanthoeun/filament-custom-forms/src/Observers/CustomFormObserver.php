<?php

namespace Chanthoeun\FilamentCustomForms\Observers;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class CustomFormObserver
{
    public function created(CustomForm $customForm)
    {
        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::create([
                'name' => $customForm->name . ' Template',
                'type' => 'custom_form_' . $customForm->id,
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
            ]);
        }
    }

    public function deleted(CustomForm $customForm)
    {
        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::where('type', 'custom_form_' . $customForm->id)->delete();
        }
    }
}
