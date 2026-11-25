<?php

namespace App\Support;

use Filament\Navigation\NavigationManager;

class SidebarNavigationManager extends NavigationManager
{
    public function get(): array
    {
        // Filament determines access, visibility, links, badges, and active state first.
        $groups = parent::get();

        return $this->panel->getId() === 'app'
            ? app(SidebarNavigation::class)->apply($groups, auth()->user())
            : $groups;
    }
}
