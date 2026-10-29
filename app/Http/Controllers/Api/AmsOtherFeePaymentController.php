<?php

namespace App\Http\Controllers\Api;

use App\Enums\WorkflowStageType;
use App\Http\Controllers\Controller;
use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Services\AmsPaymentSync;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmsOtherFeePaymentController extends Controller
{
    public function __invoke(Request $request)
    {
        $token = (string) config('services.ams.callback_token');
        abort_if(! config('services.ams.enabled') || $token === '', 503, 'AMS callback is not configured.');
        abort_unless(is_string($request->bearerToken()) && hash_equals($token, $request->bearerToken()), 401);

        foreach (['application_id', 'ams_request_id', 'payment_id'] as $key) {
            if (is_int($request->input($key))) {
                $request->merge([$key => (string) $request->input($key)]);
            }
        }

        $data = $request->validate([
            'application_id' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'ams_request_id' => ['required', 'string', 'max:100'],
            'payment_id' => ['required', 'string', 'max:100'],
            'event_version' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:paid,unpaid'],
            'receipt_number' => ['required_if:status,paid', 'nullable', 'string', 'max:255'],
            'paid_at' => ['required_if:status,paid', 'nullable', 'date'],
            'amount_usd' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'amount_khr' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'cashier_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['status'] === 'paid' && (float) ($data['amount_usd'] ?? 0) <= 0 && (float) ($data['amount_khr'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount_usd' => 'A paid payment must contain a positive amount.']);
        }

        return DB::transaction(function () use ($data) {
            $entry = CustomFormEntry::query()->lockForUpdate()->find($data['application_id']);
            abort_unless($entry && AmsPaymentSync::usesAms($entry)
                && WorkflowStageMessages::requiresPayment($entry)
                && filled($entry->created_by)
                && in_array(strtolower((string) $entry->review_status), ['approved', 'accepted', 'passed'], true), 409);
            $link = AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->getKey())->lockForUpdate()->first();
            abort_unless($link && filled($link->ams_request_id) && (string) $link->ams_request_id === $data['ams_request_id'], 409);

            if ((int) $data['event_version'] <= $link->last_event_version) {
                return response()->json(['application_id' => (string) $entry->getKey(), 'ignored' => true]);
            }

            $payment = Payment::query()->where('custom_form_entry_id', $entry->getKey())->lockForUpdate()->first();
            abort_if($payment && ($payment->integration_source !== 'ams'
                || ($link->local_payment_id && (int) $payment->id !== (int) $link->local_payment_id)
                || (int) $payment->users_id !== (int) $entry->created_by
                || (int) $payment->form_id !== (int) $entry->custom_form_id), 409);

            if ($data['status'] === 'paid') {
                abort_if($payment && $payment->status_payt === 'paid'
                    && (string) $payment->integration_payment_id !== $data['payment_id'], 409);
                abort_if(Payment::query()->where('integration_source', 'ams')->where('integration_payment_id', $data['payment_id'])
                    ->when($payment, fn ($query) => $query->whereKeyNot($payment->getKey()))->exists(), 409);
                abort_if(Payment::query()->where('receipt_number', trim($data['receipt_number']))
                    ->when($payment, fn ($query) => $query->whereKeyNot($payment->getKey()))->exists(), 409);
                $payment ??= new Payment;
                $payment->forceFill([
                    'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id,
                    'custom_form_entry_id' => $entry->getKey(), 'received_by' => null,
                    'receipt_number' => trim($data['receipt_number']), 'status_payt' => 'paid', 'status' => true,
                    'amount_usd' => (float) ($data['amount_usd'] ?? 0) > 0 ? $data['amount_usd'] : null,
                    'amount_kh' => (float) ($data['amount_khr'] ?? 0) > 0 ? $data['amount_khr'] : null,
                    'exchange_rate' => $data['exchange_rate'] ?? null, 'datetime_pay' => $data['paid_at'],
                    'integration_source' => 'ams', 'integration_payment_id' => $data['payment_id'],
                    'integration_cashier_name' => $data['cashier_name'] ?? null,
                ])->save();
                $link->local_payment_id = $payment->getKey();
                $link->external_payment_id = $data['payment_id'];
                DB::afterCommit(fn () => WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment));
            } elseif ($payment) {
                // A reversal is allowed only for the currently bound AMS receipt.
                abort_unless((string) $payment->integration_payment_id === $data['payment_id'], 409);
                $payment->forceFill(['status_payt' => 'return'])->save();
            }

            $link->last_event_version = (int) $data['event_version'];
            $link->save();

            return response()->json(['application_id' => (string) $entry->getKey(), 'payment_status' => $data['status']]);
        }, attempts: 3);
    }
}
