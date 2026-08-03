<?php

namespace App\Filament\Admin\Resources\PaymentTypes\Pages;

use App\Filament\Admin\Resources\PaymentTypes\PaymentTypeResource;
use Filament\Resources\Pages\EditRecord;

class EditPaymentType extends EditRecord
{
    protected static string $resource = PaymentTypeResource::class;
}
