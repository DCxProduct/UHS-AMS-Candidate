<?php

namespace App\Filament\Admin\Resources\CandidateLists\Tables;

use App\Filament\Admin\Resources\CandidateLists\CandidateListResource;
use App\Models\User;
use App\Support\CandidateLatinName;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\NotificationLanguage;
use App\Support\TableFilterOptions;
use App\Support\UserTypeOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CandidateListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('candidate_lists.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('candidate_lists.fields.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('email')
                    ->label(__('candidate_lists.fields.email'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('candidate_lists.fields.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('first_name_latin')
                    ->label(__('app.first_name_latin'))
                    ->state(fn (User $record): string => CandidateLatinName::split($record->name_latin ?: $record->name)[0])
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::searchName($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction)),

                TextColumn::make('last_name_latin')
                    ->label(__('app.last_name_latin'))
                    ->state(fn (User $record): string => CandidateLatinName::split($record->name_latin ?: $record->name)[1])
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::searchName($query, $search)),

                TextColumn::make('candidate_type')
                    ->label(__('candidate_lists.fields.candidate_type'))
                    ->getStateUsing(function (User $record): string {
                        $candidateRole = static::resolveCandidateRole($record);

                        if (! $candidateRole) {
                            return '-';
                        }

                        return UserTypeOptions::formatLabel((string) $candidateRole);
                    })
                    ->badge()
                    ->sortable(query: function ($query, string $direction) {
                        $driver = DB::connection()->getDriverName();

                        if ($driver === 'pgsql') {
                            return $query->orderByRaw("COALESCE(candidate_roles::text, '') {$direction}");
                        }

                        return $query->orderByRaw("COALESCE(CAST(candidate_roles AS CHAR), '') {$direction}");
                    })
                    ->color(function (User $record): string {
                        $candidateRole = static::resolveCandidateRole($record);

                        if (! $candidateRole) {
                            return 'gray';
                        }

                        return UserTypeOptions::colors()[(string) $candidateRole]
                            ?? (in_array(strtolower((string) $candidateRole), ['student', 'candidate'], true)
                                ? UserTypeOptions::normalizeColor('blue')
                                : 'gray');
                    }),

                IconColumn::make('is_active')
                    ->label(__('candidate_lists.fields.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('candidate_lists.fields.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('candidate_lists.fields.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('candidate_lists.fields.candidate_type'))
                    ->options(fn (HasTable $livewire): array => self::roleFilterOptions(TableFilterOptions::query($livewire, 'roles')))
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->query(function ($query, array $data) {
                        $value = trim((string) ($data['value'] ?? ''));

                        if ($value === '') {
                            return $query;
                        }

                        $driver = DB::connection()->getDriverName();

                        if ($driver === 'pgsql') {
                            return $query->whereRaw('LOWER(COALESCE(candidate_roles::text, \'\')) LIKE ?', ['%"'.strtolower($value).'"%']);
                        }

                        return $query->whereRaw('LOWER(COALESCE(CAST(candidate_roles AS CHAR), \'\')) LIKE ?', ['%"'.strtolower($value).'"%']);
                    }),

                SelectFilter::make('is_active')
                    ->label(__('candidate_lists.fields.is_active'))
                    ->options(fn (HasTable $livewire): array => self::activeFilterOptions(TableFilterOptions::query($livewire, 'is_active')))
                    ->native(false)
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if ($value === null || $value === '') {
                            return $query;
                        }

                        return $query->where('is_active', (bool) $value);
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->deferFilters(false)
            ->filtersApplyAction(fn (Action $action): Action => $action->hidden())
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('candidate_lists.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    Action::make('activate_account')
                        ->label(__('candidate_lists.actions.activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => FilamentActionPermissions::canForResource(CandidateListResource::class, 'activate_account')
                            && ! (bool) $record->is_active)
                        ->action(function (User $record): void {
                            FilamentActionPermissions::abortUnlessCanForResource(CandidateListResource::class, 'activate_account');

                            $record->forceFill(['is_active' => true])->save();

                            Notification::make()
                                ->title(NotificationLanguage::trans('candidate_lists.notifications.activated'))
                                ->success()
                                ->send();
                        }),

                    Action::make('deactivate_account')
                        ->label(__('candidate_lists.actions.deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (User $record): bool => FilamentActionPermissions::canForResource(CandidateListResource::class, 'deactivate_account')
                            && (bool) $record->is_active)
                        ->action(function (User $record): void {
                            FilamentActionPermissions::abortUnlessCanForResource(CandidateListResource::class, 'deactivate_account');

                            $record->forceFill(['is_active' => false])->save();

                            Notification::make()
                                ->title(NotificationLanguage::trans('candidate_lists.notifications.deactivated'))
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->label(__('candidate_lists.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(fn (User $record) => $record->forceDelete()),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('candidate_lists.actions.actions')),
            ]);
    }

    protected static function roleFilterOptions(Builder $query): array
    {
        return $query->get()
            ->map(fn (User $record): ?string => self::resolveCandidateRole($record))
            ->filter(fn ($role): bool => filled($role))
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $role): array => [$role => UserTypeOptions::formatLabel($role)])
            ->all();
    }

    protected static function activeFilterOptions(Builder $query): array
    {
        return $query->pluck('is_active')->unique()
            ->mapWithKeys(fn ($active): array => [(string) (int) (bool) $active => (bool) $active
                ? __('candidate_lists.filters.active') : __('candidate_lists.filters.inactive')])
            ->all();
    }

    protected static function resolveCandidateRole(User $record): ?string
    {
        $roles = $record->candidate_roles ?: $record->getRoleNames()->all();

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        if (is_array($roles)) {
            $roleCollection = collect($roles)
                ->filter(fn ($role): bool => filled($role))
                ->map(fn ($role): string => trim((string) $role))
                ->values();

            $candidateRole = $roleCollection
                ->first(fn (string $role): bool => UserTypeOptions::isCandidateManagedRole($role));

            if ($candidateRole) {
                return $candidateRole;
            }
        }

        if ($record->registration_type === 'student') {
            return UserTypeOptions::BASE_ROLE;
        }

        return null;
    }

    /**
     * First and last Latin names are stored together in "name".
     */
    protected static function searchName(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $names): Builder => $names
            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($search)).'%'])
            ->orWhereRaw('LOWER(name_latin) LIKE ?', ['%'.mb_strtolower(trim($search)).'%']));
    }
}
