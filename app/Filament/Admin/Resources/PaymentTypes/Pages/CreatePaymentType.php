<?php

namespace App\Filament\Admin\Resources\PaymentTypes\Pages;

use App\Filament\Admin\Resources\PaymentTypes\PaymentTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentType extends CreateRecord
{
    protected static string $resource = PaymentTypeResource::class;
}
