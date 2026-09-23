<?php

namespace App\Filament\Livewire;

use Filament\Enums\DatabaseNotificationsPosition;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as FilamentDatabaseNotifications;
use Illuminate\Contracts\View\View;

class DatabaseNotifications extends FilamentDatabaseNotifications
{
    public function clearNotificationsAction(): Action
    {
        return parent::clearNotificationsAction()->hidden();
    }

    public function getTrigger(): View
    {
        return view('filament.components.database-notifications-trigger', [
            'isTopbar' => ($this->position ?? filament()->getDatabaseNotificationsPosition()) === DatabaseNotificationsPosition::Topbar,
            'unreadNotificationsCount' => $this->getUnreadNotificationsCount(),
        ]);
    }
}
