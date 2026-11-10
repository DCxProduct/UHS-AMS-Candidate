<?php

namespace App\Filament\Admin\Resources\GeoLocations\Tables;

use App\Models\GeoLocation;
use App\Support\LocalizedNumber;
use App\Support\TableFilterOptions;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GeoLocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('geo_locations.filters.search_placeholder'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('geo_locations.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('name')
                    ->label(__('geo_locations.table.name'))
                    ->getStateUsing(fn (GeoLocation $record): string => app()->getLocale() === 'km'
                        ? ($record->name_kh ?: $record->name_en ?: '')
                        : ($record->name_en ?: $record->name_kh ?: '')
                    )
                    ->searchable(['name_en', 'name_kh'])
                    ->sortable(),

                TextColumn::make('code')
                    ->label(__('geo_locations.table.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('geo_locations.table.type'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'province' => __('geo_locations.types.province'),
                        'district' => __('geo_locations.types.district'),
                        'commune' => __('geo_locations.types.commune'),
                        'village' => __('geo_locations.types.village'),
                        default => $state ?? '-',
                    })
                    ->sortable(),

                TextColumn::make('parent_id')
                    ->label(__('geo_locations.table.parent'))
                    ->getStateUsing(function (GeoLocation $record): string {
                        if (! $record->parent) {
                            return '-';
                        }

                        return app()->getLocale() === 'km'
                            ? ($record->parent->name_kh ?: $record->parent->name_en ?: '-')
                            : ($record->parent->name_en ?: $record->parent->name_kh ?: '-');
                    }),

                IconColumn::make('is_active')
                    ->label(__('geo_locations.table.active'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('geo_location_filter')
                    ->label(__('geo_locations.filters.filter_location'))
                    ->form([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 5,
                        ])
                            ->schema([
                                Select::make('type')
                                    ->label(__('geo_locations.filters.show_by_type'))
                                    ->placeholder(__('geo_locations.filters.all_types'))
                                    ->options(fn (HasTable $livewire): array => TableFilterOptions::query($livewire, 'geo_location_filter', 'type')
                                        ->pluck('type')->unique()
                                        ->mapWithKeys(fn (string $type): array => [$type => __('geo_locations.types.'.$type)])
                                        ->all())
                                    ->native(false)
                                    ->columnSpan(1),

                                Select::make('province_id')
                                    ->label(__('geo_locations.types.province'))
                                    ->placeholder(__('geo_locations.filters.select_province'))
                                    ->options(fn (HasTable $livewire): array => self::locationOptions('province', query: TableFilterOptions::query($livewire, 'geo_location_filter', 'province_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function (callable $set): void {
                                        $set('district_id', null);
                                        $set('commune_id', null);
                                        $set('village_id', null);
                                    }),

                                Select::make('district_id')
                                    ->label(__('geo_locations.types.district'))
                                    ->placeholder(__('geo_locations.filters.select_district'))
                                    ->options(fn (callable $get, HasTable $livewire): array => self::locationOptions('district', $get('province_id'), TableFilterOptions::query($livewire, 'geo_location_filter', 'district_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function (callable $set): void {
                                        $set('commune_id', null);
                                        $set('village_id', null);
                                    }),

                                Select::make('commune_id')
                                    ->label(__('geo_locations.types.commune'))
                                    ->placeholder(__('geo_locations.filters.select_commune'))
                                    ->options(fn (callable $get, HasTable $livewire): array => self::locationOptions('commune', $get('district_id'), TableFilterOptions::query($livewire, 'geo_location_filter', 'commune_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->columnSpan(1)
                                    ->afterStateUpdated(function (callable $set): void {
                                        $set('village_id', null);
                                    }),

                                Select::make('village_id')
                                    ->label(__('geo_locations.types.village'))
                                    ->placeholder(__('geo_locations.filters.select_village'))
                                    ->options(fn (callable $get, HasTable $livewire): array => self::locationOptions('village', $get('commune_id'), TableFilterOptions::query($livewire, 'geo_location_filter', 'village_id')))
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->columnSpan(1),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $selectedLocationId = $data['village_id']
                            ?? $data['commune_id']
                            ?? $data['district_id']
                            ?? $data['province_id']
                            ?? null;

                        return $query
                            ->when(
                                filled($data['type'] ?? null),
                                fn (Builder $query): Builder => $query->where('type', $data['type'])
                            )
                            ->when(
                                filled($selectedLocationId),
                                fn (Builder $query): Builder => $query->whereIn(
                                    'id',
                                    self::locationTreeIds((int) $selectedLocationId)
                                )
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns([
                'default' => 1,
                'md' => 1,
                'xl' => 1,
            ])
            ->deferFilters(false)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('geo_locations.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('geo_locations.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('geo_locations.actions.actions')),
            ]);
    }

    private static function locationOptions(string $type, mixed $parentId = null, ?Builder $query = null): array
    {
        return GeoLocation::query()
            ->where('type', $type)
            ->when($query !== null, fn (Builder $locations): Builder => $locations->whereKey(self::representedLocationIds($query)))
            ->when(
                filled($parentId),
                fn (Builder $query): Builder => $query->where('parent_id', $parentId)
            )
            ->orderBy(app()->getLocale() === 'km' ? 'name_kh' : 'name_en')
            ->get(['id', 'name_en', 'name_kh'])
            ->mapWithKeys(function (GeoLocation $location): array {
                $name = app()->getLocale() === 'km'
                    ? ($location->name_kh ?: $location->name_en ?: __('geo_locations.form.no_name'))
                    : ($location->name_en ?: $location->name_kh ?: __('geo_locations.form.no_name'));

                return [$location->id => $name];
            })
            ->toArray();
    }

    /** Include ancestors: a province can filter visible villages without being a village row. */
    private static function representedLocationIds(Builder $query): array
    {
        $locations = $query->get(['geo_locations.id', 'geo_locations.parent_id']);
        $ids = $locations->modelKeys();
        $parents = $locations->pluck('parent_id')->filter()->unique()->all();

        for ($level = 0; $level < 4 && $parents !== []; $level++) {
            $ancestors = GeoLocation::query()->whereKey($parents)->get(['id', 'parent_id']);
            $ids = [...$ids, ...$ancestors->modelKeys()];
            $parents = $ancestors->pluck('parent_id')->filter()->unique()->all();
        }

        return array_values(array_unique($ids));
    }

    private static function locationTreeIds(int $locationId): array
    {
        $ids = [$locationId];
        $parentIds = [$locationId];

        for ($i = 0; $i < 5; $i++) {
            $childIds = GeoLocation::query()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->toArray();

            if (empty($childIds)) {
                break;
            }

            $ids = array_merge($ids, $childIds);
            $parentIds = $childIds;
        }

        return array_values(array_unique($ids));
    }
}
