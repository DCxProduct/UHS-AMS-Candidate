<?php

namespace App\Filament\Admin\Resources\PaymentTypes\Pages;

use App\Filament\Admin\Resources\PaymentTypes\PaymentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentTypes extends ListRecords
{
    protected static string $resource = PaymentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('payment_types.actions.create_payment_type')),
        ];
    }
}
