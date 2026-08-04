<?php

namespace App\Filament\Admin\Resources\AuditLogs\Pages;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearAuditLogs')
                ->label(__('audit_logs.buttons.clear_data'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('audit_logs.buttons.clear_data'))
                ->modalDescription(__('audit_logs.messages.clear_confirm'))
                ->action(function (): void {
                    $this->getFilteredTableQuery()?->delete();
                }),
        ];
    }
}
