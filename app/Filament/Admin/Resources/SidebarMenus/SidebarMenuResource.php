<?php

namespace App\Filament\Admin\Resources\SidebarMenus;

use App\Filament\Admin\Resources\SidebarMenus\Pages\EditSidebarMenu;
use App\Filament\Admin\Resources\SidebarMenus\Pages\ListSidebarMenus;
use App\Filament\Concerns\AdminOnly;
use App\Models\SidebarMenu;
use App\Support\SidebarMenuIcons;
use App\Support\SidebarNavigation;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class SidebarMenuResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = SidebarMenu::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return __('sidebar_menus.navigation');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('sidebar_menus.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('sidebar_menus.navigation');
    }

    public static function canView(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return str_starts_with($record->target_key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX) && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        // Custom Form menu labels and order are managed by Custom Forms.
        return parent::getEloquentQuery()->navigationOnly()->shared();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('sidebar_menus.form_section'))->columns(2)->schema([
                Select::make('navigation_group')->label(__('sidebar_menus.group'))
                    ->hidden(fn (?SidebarMenu $record): bool => str_starts_with((string) $record?->target_key, 'group:'))
                    ->options(fn (): array => app(SidebarNavigation::class)->groupOptions())
                    ->searchable()->required()
                    ->rules(fn (): array => [Rule::in(array_keys(app(SidebarNavigation::class)->groupOptions()))])
                    ->afterStateHydrated(function (Select $component, ?SidebarMenu $record): void {
                        $component->state($record ? app(SidebarNavigation::class)->groupForRecord($record) : null);
                    }),
                Select::make('target_key')->label(__('sidebar_menus.menu'))
                    ->hidden(fn (?SidebarMenu $record): bool => str_starts_with((string) $record?->target_key, 'group:'))
                    ->options(fn (?SidebarMenu $record): array => app(SidebarNavigation::class)->menuOptions(
                        app(SidebarNavigation::class)->groupForTarget($record?->target_key)))
                    ->disabled()->dehydrated(false),
                TextInput::make('label_en')->label(__('sidebar_menus.label_en'))->maxLength(255)
                    ->required(fn (?SidebarMenu $record): bool => str_starts_with((string) $record?->target_key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX))
                    ->placeholder(fn (?SidebarMenu $record): string => app(SidebarNavigation::class)->catalog()[$record?->target_key]['label_en'] ?? ''),
                TextInput::make('label_km')->label(__('sidebar_menus.label_km'))->maxLength(255)
                    ->required(fn (?SidebarMenu $record): bool => str_starts_with((string) $record?->target_key, SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX))
                    ->placeholder(fn (?SidebarMenu $record): string => app(SidebarNavigation::class)->catalog()[$record?->target_key]['label_km'] ?? ''),
                Select::make('navigation_icon')->label(__('sidebar_menus.icon'))
                    ->hidden(fn (?SidebarMenu $record): bool => str_starts_with((string) $record?->target_key, 'group:'))
                    ->placeholder(__('sidebar_menus.default_icon'))
                    ->options(fn (Get $get, ?SidebarMenu $record): array => SidebarMenuIcons::htmlOptions(
                        $get('navigation_icon') ?: app(SidebarNavigation::class)->defaultIconForTarget($record?->target_key)))
                    ->getOptionLabelUsing(fn (?string $value): ?string => SidebarMenuIcons::optionHtml($value))
                    ->afterStateHydrated(function (Select $component, ?SidebarMenu $record): void {
                        if (blank($component->getState())) {
                            $component->state(app(SidebarNavigation::class)->defaultIconForTarget($record?->target_key));
                        }
                    })
                    ->live()
                    ->afterStateUpdated(function (Select $component, ?string $state, ?SidebarMenu $record): void {
                        if (blank($state)) {
                            $component->state(app(SidebarNavigation::class)->defaultIconForTarget($record?->target_key));
                        }
                    })
                    ->dehydrateStateUsing(fn (?string $state, ?SidebarMenu $record): ?string => $state === app(SidebarNavigation::class)->defaultIconForTarget($record?->target_key) ? null : $state)
                    ->rules(fn (): array => ['nullable', 'string', 'max:255', Rule::in(array_keys(SidebarMenuIcons::all()))])
                    ->allowHtml()->searchable()->searchValues()->native(false)
                    ->optionsLimit(fn (): int => count(SidebarMenuIcons::options())),
                TextInput::make('sort_order')->label(__('sidebar_menus.order'))->required()->integer()->minValue(1)->maxValue(1000000)->default(1),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $navigation = app(SidebarNavigation::class);
        $names = $navigation->names();

        return $table
            ->reorderable('sort_order', fn (): bool => auth()->user()?->can('reorder', SidebarMenu::class) ?? false)
            ->columns([
                TextColumn::make('navigation_group')->label(__('sidebar_menus.group'))
                    ->getStateUsing(fn (SidebarMenu $record): string => $navigation->targetLabel($navigation->groupForRecord($record) ?? '', $names)),
                TextColumn::make('target_key')->label(__('sidebar_menus.menu'))
                    ->visible(fn (ListSidebarMenus $livewire): bool => $livewire->activeTab !== 'groups')
                    ->formatStateUsing(fn (string $state): string => (app(SidebarNavigation::class)->catalog()[$state]['kind'] ?? null) === 'group'
                        ? '—' : $navigation->targetLabel($state, $names)),
                TextColumn::make('sort_order')->label(__('sidebar_menus.order'))
                    ->getStateUsing(fn (SidebarMenu $record): int => (int) $record->getAttribute('sidebar_position')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (SidebarMenu $record, ListSidebarMenus $livewire): bool => $livewire->activeTab === 'groups' && static::canDelete($record))
                    ->disabled(fn (SidebarMenu $record): bool => app(SidebarNavigation::class)->groupHasMenus($record))
                    ->tooltip(fn (SidebarMenu $record): ?string => app(SidebarNavigation::class)->groupHasMenus($record) ? __('sidebar_menus.group_has_menus') : null)
                    ->modalDescription(__('sidebar_menus.delete_group_help'))
                    ->failureNotificationTitle(__('sidebar_menus.group_has_menus'))
                    ->using(fn (SidebarMenu $record): bool => app(SidebarNavigation::class)->deleteGroup($record))
                    ->successRedirectUrl(fn (): string => static::getUrl('index', ['tab' => 'groups'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSidebarMenus::route('/'),
            'edit' => EditSidebarMenu::route('/{record}/edit'),
        ];
    }
}
