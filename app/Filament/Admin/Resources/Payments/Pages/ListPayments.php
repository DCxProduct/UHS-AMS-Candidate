<?php

namespace App\Filament\Admin\Resources\Payments\Pages;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('payments.actions.new')),
            Action::make('download_excel')
                ->label(__('payments.actions.download_excel'))
                ->color('success')
                ->action(fn () => $this->downloadExcel()),
        ];
    }

    protected function downloadExcel()
    {
        return PaymentsTable::downloadExcel(
            $this->getFilteredTableQuery()
                ->with(['user', 'form'])
                ->get(),
            $this->visibleExportColumnKeys(),
        );
    }

    protected function visibleExportColumnKeys(): array
    {
        $keys = [];

        foreach ($this->tableColumns ?? [] as $item) {
            if (($item['type'] ?? null) === 'column' && ($item['isToggled'] ?? false)) {
                $keys[] = (string) $item['name'];

                continue;
            }

            if (($item['type'] ?? null) !== 'group') {
                continue;
            }

            foreach ($item['columns'] ?? [] as $column) {
                if ($column['isToggled'] ?? false) {
                    $keys[] = (string) $column['name'];
                }
            }
        }

        return $keys;
    }
}
