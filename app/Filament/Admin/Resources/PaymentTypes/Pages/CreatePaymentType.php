<?php

namespace App\Filament\Admin\Resources\PaymentTypes\Pages;

use App\Filament\Admin\Resources\PaymentTypes\PaymentTypeResource;
use App\Models\PaymentType;
use App\Support\ConfigurationKey;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentType extends CreateRecord
{
    protected static string $resource = PaymentTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $label = filled($data['name_en'] ?? null) ? $data['name_en'] : $data['abbreviation'];
        $data['key'] = ConfigurationKey::generate(PaymentType::class, (string) $label, 'payment_type');

        return $data;
    }
}
