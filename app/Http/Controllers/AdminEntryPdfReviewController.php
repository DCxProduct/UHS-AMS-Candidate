<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Services\DocumentRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEntryPdfReviewController extends Controller
{
    public function show(CustomFormEntry $entry)
    {
        abort_unless(auth()->user()?->registration_type === 'admin', 403);

        return view('admin-entry-pdf-review', compact('entry'));
    }

    public function pdf(CustomFormEntry $entry)
    {
        abort_unless(auth()->user()?->registration_type === 'admin', 403);

        $template = $this->findTemplate($entry);
        abort_if(! $template, 404);

        $pdf = app(DocumentRenderer::class)->render($template, $entry);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="entry-' . $entry->id . '.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function approve(CustomFormEntry $entry)
    {
        abort_unless(auth()->user()?->registration_type === 'admin', 403);

        $data = is_array($entry->data) ? $entry->data : [];
        $oldValues = [
            'review_status' => $entry->review_status,
            'registration_status' => data_get($data, 'registration_status'),
        ];
        $data['candidate_status'] = 'pending';
        $data['registration_status'] = 'approved';

        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'review_status' => 'approved',
            'review_note' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'updated_at' => now(),
            'data' => json_encode($data),
        ]);

        $entry->refresh();

        AuditLogger::log(
            action: 'approved',
            auditable: $entry,
            oldValues: $oldValues,
            newValues: [
                'review_status' => 'approved',
                'registration_status' => 'approved',
            ],
            description: 'Application approved from PDF review',
            metadata: ['module' => 'Candidate Requested'],
        );

        return redirect()->back();
    }

    public function reject(Request $request, CustomFormEntry $entry)
    {
        abort_unless(auth()->user()?->registration_type === 'admin', 403);

        $request->validate([
            'review_note' => ['required', 'string'],
        ]);

        $data = is_array($entry->data) ? $entry->data : [];
        $oldValues = [
            'review_status' => $entry->review_status,
            'registration_status' => data_get($data, 'registration_status'),
            'review_note' => $entry->review_note,
        ];
        $data['registration_status'] = 'rejected';

        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'review_status' => 'rejected',
            'review_note' => $request->review_note,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'updated_at' => now(),
            'data' => json_encode($data),
        ]);

        $entry->refresh();

        AuditLogger::log(
            action: 'rejected',
            auditable: $entry,
            oldValues: $oldValues,
            newValues: [
                'review_status' => 'rejected',
                'registration_status' => 'rejected',
                'review_note' => $request->review_note,
            ],
            description: 'Application rejected from PDF review',
            metadata: ['module' => 'Candidate Requested'],
        );

        return redirect()->back();
    }

    protected function findTemplate(CustomFormEntry $entry): ?DocumentTemplate
    {
        $formSelection = strtolower((string) data_get($entry->data, 'form_selection'));

        if (filled($formSelection)) {
            $subForm = CustomForm::query()
                ->where('custom_form_id', $entry->custom_form_id)
                ->where('menu_placement', 'sub_item')
                ->where('sub_item_type', $formSelection)
                ->first();

            if ($subForm) {
                $template = DocumentTemplate::query()
                    ->where('type', 'custom_form_' . $subForm->id)
                    ->first();

                if ($template) {
                    return $template;
                }
            }
        }

        return DocumentTemplate::query()
            ->where('type', 'custom_form_' . $entry->custom_form_id)
            ->first();
    }
}
