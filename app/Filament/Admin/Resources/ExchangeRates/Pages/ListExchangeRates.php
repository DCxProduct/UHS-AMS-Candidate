<?php

namespace App\Filament\Admin\Resources\ExchangeRates\Pages;

use App\Filament\Admin\Resources\ExchangeRates\ExchangeRateResource;
use App\Models\ExchangeRate;
use Filament\Resources\Pages\ListRecords;

class ListExchangeRates extends ListRecords
{
    protected static string $resource = ExchangeRateResource::class;

    public function mount(): void
    {
        $record = ExchangeRate::usdToKhrRecord();

        parent::mount();

        $this->redirect(
            ExchangeRateResource::getUrl('edit', ['record' => $record]),
            navigate: true,
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
