<?php

namespace App\Filament\Admin\Resources\NewStudentAccounts\Pages;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListNewStudentAccounts extends ListRecords
{
    protected static string $resource = NewStudentAccountResource::class;

    public function getTabs(): array
    {
        return [
            'current' => Tab::make(__('new_student_accounts.tabs.current'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('new_student_accounts.archived_at')),
            'archived' => Tab::make(__('new_student_accounts.tabs.archived'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('new_student_accounts.archived_at')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'current';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
