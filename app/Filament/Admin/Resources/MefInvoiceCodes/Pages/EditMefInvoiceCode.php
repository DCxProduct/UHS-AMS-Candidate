<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Pages;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMefInvoiceCode extends EditRecord
{
    protected static string $resource = MefInvoiceCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
