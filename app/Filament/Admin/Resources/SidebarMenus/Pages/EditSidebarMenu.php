<?php

namespace App\Filament\Admin\Resources\SidebarMenus\Pages;

use App\Filament\Admin\Resources\SidebarMenus\SidebarMenuResource;
use App\Support\SidebarNavigation;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSidebarMenu extends EditRecord
{
    protected static string $resource = SidebarMenuResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['sort_order'] = app(SidebarNavigation::class)->displayPosition($this->getRecord());

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SidebarNavigation::class)->updateAtPosition($record, $data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Keep the saved role and navigation target even if form data is tampered with.
        $data['role_id'] = $this->getRecord()->getRawOriginal('role_id');
        $data['target_key'] = $this->getRecord()->getRawOriginal('target_key');
        if (str_starts_with($data['target_key'], 'group:')) {
            // A group heading can be renamed/reordered, but cannot become another group.
            $data['navigation_group'] = $this->getRecord()->getRawOriginal('navigation_group');
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index', [
            'tab' => str_starts_with($this->getRecord()->target_key, 'group:') ? 'groups' : 'menus',
        ]);
    }
}
