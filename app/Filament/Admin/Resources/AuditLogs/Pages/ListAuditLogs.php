<?php

namespace App\Filament\Admin\Resources\AuditLogs\Pages;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Support\FilamentActionPermissions;
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
                ->visible(fn (): bool => FilamentActionPermissions::canForResource(AuditLogResource::class, 'clear_data'))
                ->requiresConfirmation()
                ->modalHeading(__('audit_logs.buttons.clear_data'))
                ->modalDescription(__('audit_logs.messages.clear_confirm'))
                ->modalSubmitActionLabel(__('app.delete'))
                ->modalCancelActionLabel(__('app.cancel'))
                ->action(function (): void {
                    FilamentActionPermissions::abortUnlessCanForResource(AuditLogResource::class, 'clear_data');

                    $this->getFilteredTableQuery()?->delete();
                }),
        ];
    }
}
