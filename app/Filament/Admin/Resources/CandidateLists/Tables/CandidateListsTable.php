<?php

namespace App\Filament\Admin\Resources\CandidateLists\Tables;

use App\Models\SystemUser;
use App\Models\User;
use App\Support\LocalizedDate;
use App\Support\NotificationLanguage;
use App\Support\LocalizedNumber;
use App\Support\UserTypeOptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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

                TextColumn::make('username')
                    ->label(__('candidate_lists.fields.username'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('candidate_type')
                    ->label(__('candidate_lists.fields.candidate_type'))
                    ->getStateUsing(function (SystemUser $record): string {
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
                            return $query->orderByRaw("COALESCE(roles::text, '') {$direction}");
                        }

                        return $query->orderByRaw("COALESCE(CAST(roles AS CHAR), '') {$direction}");
                    })
                    ->color(function (SystemUser $record): string {
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
                    ->options(fn (): array => UserTypeOptions::options())
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
                            return $query->whereRaw('LOWER(COALESCE(roles::text, \'\')) LIKE ?', ['%"' . strtolower($value) . '"%']);
                        }

                        return $query->whereRaw('LOWER(COALESCE(CAST(roles AS CHAR), \'\')) LIKE ?', ['%"' . strtolower($value) . '"%']);
                    }),

                SelectFilter::make('is_active')
                    ->label(__('candidate_lists.fields.is_active'))
                    ->options([
                        '1' => __('candidate_lists.filters.active'),
                        '0' => __('candidate_lists.filters.inactive'),
                    ])
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
                        ->visible(fn (SystemUser $record): bool => ! (bool) $record->is_active)
                        ->action(function (SystemUser $record): void {
                            $record->activateAccount();

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
                        ->visible(fn (SystemUser $record): bool => (bool) $record->is_active)
                        ->action(function (SystemUser $record): void {
                            $record->deactivateAccount();

                            Notification::make()
                                ->title(NotificationLanguage::trans('candidate_lists.notifications.deactivated'))
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->label(__('candidate_lists.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(fn (SystemUser $record) => $record->deleteWithLinkedLoginUsers()),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('candidate_lists.actions.actions')),
            ]);
    }

    protected static function resolveCandidateRole(SystemUser $record): ?string
    {
        $roles = $record->roles;

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

        $loginUser = static::findLinkedLoginUser($record);

        if ($loginUser?->registration_type === 'student') {
            return UserTypeOptions::BASE_ROLE;
        }

        return null;
    }

    protected static function findLinkedLoginUser(SystemUser $record): ?User
    {
        return $record->findLinkedLoginUser();
    }
}
