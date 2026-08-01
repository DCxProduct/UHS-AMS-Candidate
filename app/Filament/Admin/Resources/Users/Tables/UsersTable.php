<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\SystemUser;
use App\Models\User;
use App\Support\UserTypeOptions;
use App\Support\NotificationLanguage;
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

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('users.search'))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('users.fields.no'))
                    ->rowIndex()
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('users.fields.phone'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('username')
                    ->label(__('users.fields.username'))
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('candidate_type')
                    ->label(__('users.fields.candidate_type'))
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
                    ->label(__('users.fields.is_active'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('users.fields.created_at'))
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('users.fields.updated_at'))
                    ->date('M d, Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label(__('users.fields.candidate_type'))
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
                    ->label(__('users.fields.is_active'))
                    ->options([
                        '1' => __('users.filters.active'),
                        '0' => __('users.filters.inactive'),
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
                        ->label(__('users.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    Action::make('activate_account')
                        ->label(__('users.actions.activate'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => ! (bool) $record->is_active)
                        ->action(function ($record): void {
                            $record->activateAccount();

                            Notification::make()
                                ->title(NotificationLanguage::trans('users.notifications.activated'))
                                ->success()
                                ->send();
                        }),

                    Action::make('deactivate_account')
                        ->label(__('users.actions.deactivate'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn ($record): bool => (bool) $record->is_active)
                        ->action(function ($record): void {
                            $record->deactivateAccount();

                            Notification::make()
                                ->title(NotificationLanguage::trans('users.notifications.deactivated'))
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()
                        ->label(__('users.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('users.actions.actions')),
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
        return User::query()
            ->when(filled($record->username), fn ($query) => $query->orWhere('username', $record->username))
            ->when(filled($record->email), fn ($query) => $query->orWhere('email', $record->email))
            ->when(filled($record->phone), fn ($query) => $query->orWhere('phone', $record->phone))
            ->first();
    }
}
