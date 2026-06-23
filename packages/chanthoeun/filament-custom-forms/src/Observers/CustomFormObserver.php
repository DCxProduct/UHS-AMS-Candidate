<?php

namespace Chanthoeun\FilamentCustomForms\Observers;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

class CustomFormObserver
{
    public function created(CustomForm $customForm): void
    {
        if (!class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return;
        }

        // Use parent custom_form_id if set, otherwise use own id
        $linkedFormId = $customForm->custom_form_id ?? $customForm->id;

        \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::create([
            'name'           => $customForm->name . ' Template',
            'type'           => 'custom_form_' . $customForm->id,
            'custom_form_id' => $linkedFormId,
            'model_class'    => CustomFormEntry::class,
            'content'        => '',
            'page_settings'  => [
                'format'        => 'a4',
                'orientation'   => 'portrait',
                'margin_left'   => 15,
                'margin_right'  => 15,
                'margin_top'    => 15,
                'margin_bottom' => 15,
            ],
            'extra_data_sources' => [],
        ]);
    }

    public function updated(CustomForm $customForm): void
    {
        if (!class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return;
        }

        $linkedFormId = $customForm->custom_form_id ?? $customForm->id;

        \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::where('type', 'custom_form_' . $customForm->id)
            ->update([
                'name'           => $customForm->name . ' Template',
                'custom_form_id' => $linkedFormId,
            ]);
    }

    public function deleted(CustomForm $customForm): void
    {
        if (!class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return;
        }

        \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::where('type', 'custom_form_' . $customForm->id)
            ->delete();
    }
}
