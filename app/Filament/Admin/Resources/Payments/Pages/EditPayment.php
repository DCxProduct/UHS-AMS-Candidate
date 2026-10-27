<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['payment_method'] ?? null) === 'cash') {
            $data['type_payment'] = PaymentType::activeKeyForMethod('cash');
        }

        // The disabled date-only picker must not discard the original payment time.
        unset($data['payment_method'], $data['datetime_pay']);

        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            unset($data['exchange_rate']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            $record->update($data);

            return $record;
        } catch (QueryException $exception) {
            if (Payment::isReceiptNumberUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'receipt_number' => __('payments.validation.receipt_number_unique'),
                ]);
            }

            throw $exception;
        }
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('index');
    }
}
