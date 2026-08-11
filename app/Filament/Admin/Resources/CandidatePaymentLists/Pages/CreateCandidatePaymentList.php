<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists\Pages;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Models\Payment;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class CreateCandidatePaymentList extends CreateRecord
{
    protected static string $resource = CandidatePaymentListResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $data['status_payt'] = 'paid';
        $data['status'] ??= true;

        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            unset($data['exchange_rate']);
        }

        return Payment::query()->create($data);
    }

    public function getTitle(): string | Htmlable
    {
        return __('payments.actions.create_payment');
    }

    public function getBreadcrumb(): string
    {
        return __('payments.actions.create_payment');
    }

    protected function getRedirectUrl(): string
    {
        return CandidatePaymentListResource::getUrl('index');
    }
}
