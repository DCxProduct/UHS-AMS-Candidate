<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Services\CandidatePaymentRecorder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['payment_method'] ?? null) === 'cash') {
            $data['type_payment'] = PaymentType::activeKeyForMethod('cash');
        }

        unset($data['payment_method']);
        $data['status_payt'] = 'paid';
        $data['status'] ??= true;

        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            unset($data['exchange_rate']);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CandidatePaymentRecorder::class)->record($data);
        } catch (QueryException $exception) {
            if (Payment::isReceiptNumberUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'receipt_number' => __('payments.validation.receipt_number_unique'),
                ]);
            }

            throw $exception;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('payments.actions.create_payment');
    }

    public function getBreadcrumb(): string
    {
        return __('payments.actions.create_payment');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
