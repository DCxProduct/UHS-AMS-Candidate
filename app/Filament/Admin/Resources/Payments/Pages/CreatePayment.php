<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status_payt'] = 'paid';
        $data['status'] ??= true;

        return $data;
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
        return static::getResource()::getUrl('index');
    }
}
