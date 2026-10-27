<?php

namespace App\Filament\Admin\Resources\PaymentStatusLists\Pages;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Models\Payment;
use App\Services\CandidatePaymentRecorder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CreatePaymentStatusList extends CreateRecord
{
    protected static string $resource = PaymentStatusListResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['status_payt'] = 'paid';
        $data['status'] ??= true;

        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            unset($data['exchange_rate']);
        }

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
        return PaymentStatusListResource::getUrl('index');
    }
}
