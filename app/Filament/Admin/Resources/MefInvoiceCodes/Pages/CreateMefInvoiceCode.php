<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMefInvoiceCode extends CreateRecord
{
    protected static string $resource = MefInvoiceCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return MefInvoiceCodeResource::getUrl('index');
    }
}
