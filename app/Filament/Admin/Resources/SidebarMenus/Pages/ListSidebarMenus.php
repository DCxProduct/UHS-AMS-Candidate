<?php

namespace App\Filament\Admin\Resources\SidebarMenus\Pages;

use App\Filament\Admin\Resources\SidebarMenus\SidebarMenuResource;
use App\Models\SidebarMenu;
use App\Support\SidebarNavigation;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListSidebarMenus extends ListRecords
{
    protected static string $resource = SidebarMenuResource::class;

    public function getTitle(): string
    {
        return app(SidebarNavigation::class)->targetLabel('item:'.static::getResource());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label(__('sidebar_menus.create_group'))
                ->modalHeading(__('sidebar_menus.create_group'))
                ->modalSubmitActionLabel(__('sidebar_menus.create_group'))
                ->visible(fn (): bool => $this->activeTab === 'groups')
                ->authorize(fn (): bool => $this->activeTab === 'groups' && SidebarMenuResource::canCreate())
                ->schema([
                    TextInput::make('label_en')->label(__('sidebar_menus.label_en'))->required()->maxLength(255),
                    TextInput::make('label_km')->label(__('sidebar_menus.label_km'))->required()->maxLength(255),
                    TextInput::make('sort_order')->label(__('sidebar_menus.order'))->required()->integer()->minValue(1)->maxValue(1000000)
                        ->default(fn (): int => SidebarMenuResource::getEloquentQuery()->where('target_key', 'like', 'group:%')->count() + 1),
                ])
                ->successNotificationTitle(__('sidebar_menus.group_created'))
                ->successRedirectUrl(fn (): string => SidebarMenuResource::getUrl('index', ['tab' => 'groups']))
                ->action(function (array $data, Action $action): void {
                    abort_unless($this->activeTab === 'groups', 403);
                    app(SidebarNavigation::class)->createGroup($data);
                    $this->resetTable();
                    $action->success();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'menus' => Tab::make(__('sidebar_menus.tabs.menus'))
                ->icon('heroicon-o-bars-3')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('target_key', 'not like', 'group:%')),
            'groups' => Tab::make(__('sidebar_menus.tabs.groups'))
                ->icon('heroicon-o-folder')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('target_key', 'like', 'group:%')),
        ];
    }

    protected function applySortingToTableQuery(Builder $query): Builder
    {
        return app(SidebarNavigation::class)->orderTableQuery($query);
    }

    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        Gate::authorize('viewAny', SidebarMenu::class);
        Gate::authorize('reorder', SidebarMenu::class);
        abort_unless(count($order) <= 1000 && collect($order)->every(
            fn ($id): bool => (is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/', (string) $id),
        ), 422);
        $ids = array_map('strval', $order);
        abort_unless(count(array_unique($ids)) === count($ids), 422);
        $records = SidebarMenuResource::getEloquentQuery()->whereIn('id', $ids)->get()->keyBy('id');
        abort_unless($records->count() === count($ids), 422);

        if ($draggedRecordKey !== null) {
            abort_unless($records->has($draggedRecordKey), 422);
        }
        app(SidebarNavigation::class)->reorder($ids, $draggedRecordKey);
    }
}
