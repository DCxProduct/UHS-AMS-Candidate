<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditPayment extends EditRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! Schema::hasColumn('payments', 'exchange_rate')) {
            unset($data['exchange_rate']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return PaymentResource::getUrl('index');
    }
}
