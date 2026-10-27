<?php

namespace App\Services;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Support\StaticWorkflowMessages;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CandidatePaymentRecorder
{
    public function record(array $data): Payment
    {
        $paymentDate = $data['datetime_pay'] ?? null;

        // Capture the missing clock once, before any transaction retries.
        if (
            is_string($paymentDate) &&
            preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', trim($paymentDate), $parts) &&
            checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            $data['datetime_pay'] = trim($paymentDate).' '.now()->format('H:i:s');
        }

        return DB::transaction(function () use ($data): Payment {
            if (blank($data['custom_form_entry_id'] ?? null)) {
                // Preserve standalone receipt entry for legacy records.
                return Payment::query()->create($data);
            }

            // Serialize payments on the application, including distinct receipts.
            $entry = CustomFormEntry::query()->lockForUpdate()->findOrFail($data['custom_form_entry_id']);
            abort_unless(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Payment), 403);
            $ownerId = $entry->created_by;
            $status = strtolower((string) (data_get($entry->data, 'registration_status') ?: $entry->review_status));
            if (! $ownerId || ! in_array($status, ['approved', 'accepted', 'passed'], true)
                || ! WorkflowStageMessages::requiresPayment($entry)) {
                throw ValidationException::withMessages(['receipt_number' => 'This application is not eligible for payment.']);
            }

            if ((isset($data['users_id']) && (int) $data['users_id'] !== (int) $ownerId)
                || (isset($data['form_id']) && (int) $data['form_id'] !== (int) $entry->custom_form_id)) {
                throw ValidationException::withMessages(['receipt_number' => 'Payment ownership does not match the application.']);
            }

            if (Payment::query()->where('custom_form_entry_id', $entry->getKey())->exists()) {
                throw ValidationException::withMessages(['receipt_number' => 'A payment is already recorded for this application.']);
            }

            $data['users_id'] = $ownerId;
            $data['form_id'] = $entry->custom_form_id;
            $data['status_payt'] = 'paid';
            $payment = Payment::query()->create($data);

            DB::afterCommit(function () use ($entry): void {
                if (WorkflowStageMessages::hasConfiguredWorkflow($entry)) {
                    WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment);
                    WorkflowStageMessages::notifyResponsibleRole($entry);
                } else {
                    StaticWorkflowMessages::notifyPaymentCompleted($entry);
                }
            });

            return $payment;
        }, attempts: 3);
    }
}
